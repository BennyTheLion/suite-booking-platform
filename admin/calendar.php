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
            $items[] = ['label' => $r['guest_name'], 'sub' => 'יום מלא', 'pending' => $r['status'] === 'pending'];
        } else {
            $items[] = [
                'label' => $r['guest_name'],
                'sub' => substr($r['time_start'], 0, 5) . '–' . substr($r['time_end'], 0, 5),
                'pending' => $r['status'] === 'pending',
            ];
        }
    }
    foreach ($blocks as $b) {
        $items[] = ['label' => 'חסום', 'sub' => substr($b['time_start'], 0, 5) . '–' . substr($b['time_end'], 0, 5) . ($b['reason'] ? ' · ' . $b['reason'] : ''), 'pending' => false, 'block' => true];
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
$weekdayNames = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
$monthNames = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];
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
      <div class="cal-cell status-<?= $info['status'] ?><?= $dateStr === $today ? ' is-today' : '' ?>">
        <div class="cal-daynum"><?= $d ?></div>
        <?php foreach (array_slice($info['items'], 0, 3) as $it): ?>
          <div class="cal-item<?= !empty($it['pending']) ? ' is-pending' : '' ?><?= !empty($it['block']) ? ' is-block' : '' ?>" title="<?= h($it['label'] . ' · ' . $it['sub']) ?>">
            <?= h($it['sub']) ?><?php if (!empty($it['label']) && empty($it['block'])): ?> · <?= h(mb_substr($it['label'], 0, 10)) ?><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (count($info['items']) > 3): ?><div class="cal-item cal-more">+<?= count($info['items']) - 3 ?> נוספים</div><?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
