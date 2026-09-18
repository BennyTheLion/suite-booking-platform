<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = $_GET['site'] ?? '';
$site = require_admin($slug);

$rooms = db_all('SELECT id, name FROM rooms WHERE site_id = ? AND active = 1 ORDER BY sort_order', [$site['id']]);
if (!$rooms) {
    $active = 'calendar';
    require __DIR__ . '/_layout_top.php';
    echo '<div class="a-card"><p style="color:var(--a-faint);font-size:13.5px;">אין חדרים פעילים להצגה בלוח.</p></div>';
    require __DIR__ . '/_layout_bottom.php';
    exit;
}

$roomId = (int) ($_GET['room'] ?? $rooms[0]['id']);
if (!in_array($roomId, array_column($rooms, 'id'), true)) $roomId = (int) $rooms[0]['id'];

$view = ($_GET['view'] ?? 'month') === 'day' ? 'day' : 'month';
$weekdayNames = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
$monthNames = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];

// Places overlapping [start,end] items into side-by-side columns (like a calendar app),
// so two bookings that clash in time never render on top of one another.
function assign_columns(array $items): array {
    usort($items, fn($a, $b) => $a['start'] <=> $b['start']);
    $columnsEnd = [];
    foreach ($items as $i => $it) {
        $placed = false;
        foreach ($columnsEnd as $idx => $end) {
            if ($it['start'] >= $end) {
                $items[$i]['col'] = $idx;
                $columnsEnd[$idx] = $it['end'];
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            $items[$i]['col'] = count($columnsEnd);
            $columnsEnd[] = $it['end'];
        }
    }
    $totalCols = max(1, count($columnsEnd));
    foreach ($items as $i => $it) $items[$i]['totalCols'] = $totalCols;
    return $items;
}

if ($view === 'day') {
    $dateParam = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam) || !strtotime($dateParam)) $dateParam = date('Y-m-d');
    $dateObj = new DateTime($dateParam);
    $prevDate = (clone $dateObj)->modify('-1 day')->format('Y-m-d');
    $nextDate = (clone $dateObj)->modify('+1 day')->format('Y-m-d');
    $weekday = (int) $dateObj->format('w');
    $dayLabel = $weekdayNames[$weekday] . ', ' . $dateObj->format('d.m.Y');

    $schedule = get_room_schedule($roomId);
    $daySchedule = $schedule[$weekday] ?? null;
    $isClosed = !$daySchedule || $daySchedule['is_closed'];
    $openMin = $isClosed ? null : time_to_minutes($daySchedule['open_time']);
    $closeMin = $isClosed ? null : time_to_minutes($daySchedule['close_time']);

    $rows = db_all(
        "SELECT guest_name, guest_phone, booking_type, time_start, time_end, status FROM bookings
         WHERE room_id = ?
           AND (status = 'approved' OR (status = 'pending' AND created_at > NOW() - INTERVAL 15 MINUTE))
           AND ? BETWEEN date_start AND date_end
         ORDER BY time_start",
        [$roomId, $dateParam]
    );
    $blocks = db_all('SELECT reason, start_time, end_time FROM availability_blocks WHERE room_id = ? AND block_date = ? ORDER BY start_time', [$roomId, $dateParam]);

    $fullDayBooking = null;
    $timedItems = [];
    foreach ($rows as $r) {
        if ($r['booking_type'] === 'daily') {
            $fullDayBooking = $r;
            continue;
        }
        $timedItems[] = [
            'start' => time_to_minutes($r['time_start']),
            'end' => time_to_minutes($r['time_end']),
            'label' => $r['guest_name'],
            'sub' => $r['guest_phone'],
            'pending' => $r['status'] === 'pending',
            'block' => false,
        ];
    }
    foreach ($blocks as $b) {
        $timedItems[] = [
            'start' => time_to_minutes($b['start_time']),
            'end' => time_to_minutes($b['end_time']),
            'label' => 'חסום',
            'sub' => $b['reason'],
            'pending' => false,
            'block' => true,
        ];
    }
    $timedItems = $timedItems ? assign_columns($timedItems) : [];

    $active = 'calendar';
    require __DIR__ . '/_layout_top.php';
    ?>

    <div class="a-card">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <a class="a-btn secondary small" href="?site=<?= h($slug) ?>&room=<?= $roomId ?>&month=<?= h($dateObj->format('Y-m')) ?>">‹ ללוח חודשי</a>
          <a class="a-btn secondary small" href="?site=<?= h($slug) ?>&room=<?= $roomId ?>&view=day&date=<?= h($prevDate) ?>">‹ יום קודם</a>
          <strong style="font-size:15px;"><?= h($dayLabel) ?></strong>
          <a class="a-btn secondary small" href="?site=<?= h($slug) ?>&room=<?= $roomId ?>&view=day&date=<?= h($nextDate) ?>">יום הבא ›</a>
        </div>
        <form method="get" style="display:flex;align-items:center;gap:8px;">
          <input type="hidden" name="site" value="<?= h($slug) ?>">
          <input type="hidden" name="view" value="day">
          <input type="hidden" name="date" value="<?= h($dateParam) ?>">
          <select name="room" onchange="this.form.submit()">
            <?php foreach ($rooms as $r): ?>
              <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === $roomId ? 'selected' : '' ?>><?= h($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <?php if ($fullDayBooking): ?>
        <div class="a-alert <?= $fullDayBooking['status'] === 'pending' ? 'error' : 'ok' ?>">
          החדר תפוס ליום שלם — <?= h($fullDayBooking['guest_name']) ?> (<?= h($fullDayBooking['guest_phone']) ?>)<?= $fullDayBooking['status'] === 'pending' ? ' · ממתין לאישור' : '' ?>
        </div>
      <?php elseif ($isClosed): ?>
        <div class="a-alert error">החדר סגור ביום זה לפי לוח הזמינות השבועי.</div>
      <?php else: ?>
        <div class="cal-legend">
          <span><i class="cal-dot full"></i> תפוס</span>
          <span><i class="cal-dot partial"></i> ממתין לאישור</span>
          <span><i class="cal-dot closed"></i> חסום ידנית</span>
          <span>שעות פתיחה: <span dir="ltr"><?= substr($daySchedule['open_time'], 0, 5) ?>–<?= substr($daySchedule['close_time'], 0, 5) ?></span></span>
        </div>

        <div class="day-timeline-wrap">
          <div class="day-timeline" style="height:calc(24 * var(--hour-h));">
            <?php for ($h = 0; $h <= 24; $h++): ?>
              <div class="day-hour-row" style="top:calc(<?= $h ?> * var(--hour-h));">
                <span class="day-hour-label" dir="ltr"><?= sprintf('%02d:00', $h) ?></span>
              </div>
            <?php endfor; ?>

            <?php if ($openMin > 0): ?>
              <div class="day-shade" style="top:0;height:calc(<?= $openMin ?> / 60 * var(--hour-h));"></div>
            <?php endif; ?>
            <?php if ($closeMin < 1440): ?>
              <div class="day-shade" style="top:calc(<?= $closeMin ?> / 60 * var(--hour-h));height:calc((1440 - <?= $closeMin ?>) / 60 * var(--hour-h));"></div>
            <?php endif; ?>

            <?php foreach ($timedItems as $it):
              $widthPct = 100 / $it['totalCols'];
              $leftPct = $it['col'] * $widthPct;
              $durMin = max(15, $it['end'] - $it['start']);
              $cls = $it['block'] ? 'is-block' : ($it['pending'] ? 'is-pending' : 'is-approved');
            ?>
              <div class="day-item <?= $cls ?>" style="
                top:calc(<?= $it['start'] ?> / 60 * var(--hour-h));
                height:calc(<?= $durMin ?> / 60 * var(--hour-h));
                inset-inline-start:calc(<?= $leftPct ?>% + 54px);
                width:calc(<?= $widthPct ?>% - 58px);
              ">
                <strong dir="ltr"><?= sprintf('%02d:%02d', intdiv($it['start'], 60), $it['start'] % 60) ?>–<?= sprintf('%02d:%02d', intdiv($it['end'], 60), $it['end'] % 60) ?></strong>
                <span><?= h($it['label']) ?><?= $it['sub'] ? ' · ' . h($it['sub']) : '' ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php
    require __DIR__ . '/_layout_bottom.php';
    exit;
}

// ---------- Month view ----------
$monthParam = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) $monthParam = date('Y-m');
$monthStart = DateTime::createFromFormat('Y-m-d', $monthParam . '-01');
$monthEnd = (clone $monthStart)->modify('first day of next month');
$prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');
$daysInMonth = (int) $monthStart->format('t');
$leadingBlanks = (int) $monthStart->format('w'); // 0=Sunday

$schedule = get_room_schedule($roomId);
$today = date('Y-m-d');

$days = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = $monthStart->format('Y-m') . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
    $weekday = (int) date('w', strtotime($dateStr));
    $daySchedule = $schedule[$weekday] ?? null;

    if (!$daySchedule || $daySchedule['is_closed']) {
        $days[$d] = ['status' => 'closed', 'items' => []];
        continue;
    }

    $rows = db_all(
        "SELECT guest_name, booking_type, time_start, time_end, status FROM bookings
         WHERE room_id = ?
           AND (status = 'approved' OR (status = 'pending' AND created_at > NOW() - INTERVAL 15 MINUTE))
           AND ? BETWEEN date_start AND date_end
         ORDER BY time_start",
        [$roomId, $dateStr]
    );
    $blocks = db_all('SELECT reason, start_time AS time_start, end_time AS time_end FROM availability_blocks WHERE room_id = ? AND block_date = ? ORDER BY start_time', [$roomId, $dateStr]);

    $items = [];
    $isFullDay = false;
    foreach ($rows as $r) {
        if ($r['booking_type'] === 'daily') {
            $isFullDay = true;
            $items[] = ['label' => $r['guest_name'], 'sub' => 'יום מלא', 'time' => null, 'pending' => $r['status'] === 'pending'];
        } else {
            $items[] = [
                'label' => $r['guest_name'],
                'sub' => '',
                'time' => substr($r['time_start'], 0, 5) . '–' . substr($r['time_end'], 0, 5),
                'pending' => $r['status'] === 'pending',
            ];
        }
    }
    foreach ($blocks as $b) {
        $items[] = ['label' => 'חסום', 'sub' => $b['reason'] ? ' · ' . $b['reason'] : '', 'time' => substr($b['start_time'], 0, 5) . '–' . substr($b['end_time'], 0, 5), 'pending' => false, 'block' => true];
    }

    if ($isFullDay) {
        $status = 'full';
    } else {
        $slots = get_hourly_slots($roomId, $dateStr, 30);
        if (!$slots) {
            $status = 'closed';
        } else {
            $freeCount = count(array_filter($slots, fn($s) => $s['available']));
            $status = $freeCount === 0 ? 'full' : ($freeCount === count($slots) ? 'free' : 'partial');
        }
    }

    $days[$d] = ['status' => $status, 'items' => $items];
}

$statusLabels = ['free' => 'פנוי', 'partial' => 'תפוס חלקית', 'full' => 'תפוס', 'closed' => 'סגור'];
$monthLabel = $monthNames[(int) $monthStart->format('n') - 1] . ' ' . $monthStart->format('Y');

$active = 'calendar';
require __DIR__ . '/_layout_top.php';
?>

<div class="a-card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <a class="a-btn secondary small" href="?site=<?= h($slug) ?>&room=<?= $roomId ?>&month=<?= h($prevMonth) ?>">‹ הקודם</a>
      <strong style="font-size:15px;"><?= h($monthLabel) ?></strong>
      <a class="a-btn secondary small" href="?site=<?= h($slug) ?>&room=<?= $roomId ?>&month=<?= h($nextMonth) ?>">הבא ›</a>
    </div>
    <form method="get" style="display:flex;align-items:center;gap:8px;">
      <input type="hidden" name="site" value="<?= h($slug) ?>">
      <input type="hidden" name="month" value="<?= h($monthParam) ?>">
      <select name="room" onchange="this.form.submit()">
        <?php foreach ($rooms as $r): ?>
          <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === $roomId ? 'selected' : '' ?>><?= h($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="cal-legend">
    <span><i class="cal-dot free"></i> פנוי</span>
    <span><i class="cal-dot partial"></i> תפוס חלקית</span>
    <span><i class="cal-dot full"></i> תפוס</span>
    <span><i class="cal-dot closed"></i> סגור</span>
    <span style="color:var(--a-faint);">לחיצה על יום פותחת תצוגת שעות מפורטת</span>
  </div>

  <div class="cal-grid cal-weekdays">
    <?php foreach ($weekdayNames as $wn): ?><div><?= h($wn) ?></div><?php endforeach; ?>
  </div>
  <div class="cal-grid">
    <?php for ($i = 0; $i < $leadingBlanks; $i++): ?><div class="cal-cell is-blank"></div><?php endfor; ?>
    <?php for ($d = 1; $d <= $daysInMonth; $d++):
      $dateStr = $monthStart->format('Y-m') . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
      $info = $days[$d];
    ?>
      <a class="cal-cell status-<?= $info['status'] ?><?= $dateStr === $today ? ' is-today' : '' ?>" href="?site=<?= h($slug) ?>&room=<?= $roomId ?>&view=day&date=<?= h($dateStr) ?>">
        <div class="cal-daynum"><?= $d ?></div>
        <?php foreach (array_slice($info['items'], 0, 3) as $it): ?>
          <div class="cal-item<?= !empty($it['pending']) ? ' is-pending' : '' ?><?= !empty($it['block']) ? ' is-block' : '' ?>" title="<?= h($it['label'] . ' · ' . ($it['time'] ?? $it['sub'])) ?>">
            <?php if ($it['time']): ?><span dir="ltr"><?= h($it['time']) ?></span><?= h($it['sub']) ?><?php else: ?><?= h($it['sub']) ?><?php endif; ?><?php if (!empty($it['label']) && empty($it['block'])): ?> · <?= h(mb_substr($it['label'], 0, 10)) ?><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (count($info['items']) > 3): ?><div class="cal-item cal-more">+<?= count($info['items']) - 3 ?> נוספים</div><?php endif; ?>
      </a>
    <?php endfor; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
