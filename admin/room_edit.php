<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = $_GET['site'] ?? '';
$site = require_admin($slug);

$roomId = (int) ($_GET['id'] ?? 0);
$room = get_room($roomId, (int) $site['id']);
if (!$room) { http_response_code(404); echo 'החדר לא נמצא.'; exit; }

$flash = '';
$flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_details') {
        db_run(
            'UPDATE rooms SET name=?, description=?, price_per_hour=?, show_price_per_hour=?, price_per_day=?, price_3h=?, price_extra_hour=?, min_hours=?, capacity=?, size_sqm=?, bed_type=?, house_rules_text=?, active=? WHERE id=?',
            [
                trim((string) $_POST['name']), trim((string) $_POST['description']),
                (float) $_POST['price_per_hour'], isset($_POST['show_price_per_hour']) ? 1 : 0, (float) $_POST['price_per_day'],
                (float) $_POST['price_3h'], (float) $_POST['price_extra_hour'],
                max(3, (int) $_POST['min_hours']), max(1, (int) $_POST['capacity']),
                $_POST['size_sqm'] !== '' ? (int) $_POST['size_sqm'] : null,
                trim((string) $_POST['bed_type']), trim((string) $_POST['house_rules_text']),
                isset($_POST['active']) ? 1 : 0, $roomId,
            ]
        );
        $flash = 'פרטי החדר נשמרו.';
    } elseif ($action === 'update_facilities') {
        db_run('DELETE FROM room_facilities WHERE room_id = ?', [$roomId]);
        foreach ((array) ($_POST['facilities'] ?? []) as $fid) {
            db_run('INSERT INTO room_facilities (room_id, facility_id) VALUES (?, ?)', [$roomId, (int) $fid]);
        }
        $flash = 'המתקנים עודכנו.';
    } elseif ($action === 'update_schedule') {
        for ($w = 0; $w <= 6; $w++) {
            $isClosed = isset($_POST["closed_$w"]) ? 1 : 0;
            $open = $_POST["open_$w"] ?? '00:00';
            $close = $_POST["close_$w"] ?? '23:59';
            $existing = db_one('SELECT id FROM availability_schedule WHERE room_id = ? AND weekday = ?', [$roomId, $w]);
            if ($existing) {
                db_run('UPDATE availability_schedule SET is_closed=?, open_time=?, close_time=? WHERE id=?', [$isClosed, $open . ':00', $close . ':00', $existing['id']]);
            } else {
                db_run('INSERT INTO availability_schedule (room_id, weekday, is_closed, open_time, close_time) VALUES (?, ?, ?, ?, ?)', [$roomId, $w, $isClosed, $open . ':00', $close . ':00']);
            }
        }
        $flash = 'שעות הזמינות עודכנו.';
    } elseif ($action === 'add_block') {
        $blockDate = $_POST['block_date'] ?? '';
        $startT = $_POST['block_start'] ?? '';
        $endT = $_POST['block_end'] ?? '';
        if ($blockDate && $startT && $endT) {
            db_run('INSERT INTO availability_blocks (room_id, block_date, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?)',
                [$roomId, $blockDate, $startT . ':00', $endT . ':00', trim((string) ($_POST['reason'] ?? ''))]);
            $flash = 'החסימה נוספה.';
        }
    } elseif ($action === 'delete_block') {
        db_run('DELETE FROM availability_blocks WHERE id = ? AND room_id = ?', [(int) $_POST['block_id'], $roomId]);
        $flash = 'החסימה הוסרה.';
    } elseif ($action === 'upload_media' && !empty($_FILES['media']['name'][0])) {
        $hasCover = (bool) db_one('SELECT id FROM room_media WHERE room_id = ? AND is_cover = 1', [$roomId]);
        $count = count($_FILES['media']['name']);
        $okCount = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['media']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $file = [
                'name' => $_FILES['media']['name'][$i],
                'type' => $_FILES['media']['type'][$i],
                'tmp_name' => $_FILES['media']['tmp_name'][$i],
                'error' => $_FILES['media']['error'][$i],
                'size' => $_FILES['media']['size'][$i],
            ];
            $uploaded = upload_room_media((int) $site['id'], $roomId, $file);
            if ($uploaded) {
                db_run('INSERT INTO room_media (room_id, type, path, is_cover) VALUES (?, ?, ?, ?)', [$roomId, $uploaded['type'], $uploaded['path'], $hasCover ? 0 : 1]);
                $hasCover = true;
                $okCount++;
            }
        }
        if ($okCount > 0) {
            $flash = $okCount === $count ? 'הקבצים הועלו.' : "הועלו {$okCount} מתוך {$count} קבצים (חלק נכשלו — בדוק סוג/גודל).";
            $flashType = $okCount === $count ? 'ok' : 'error';
        } else {
            $flash = 'שגיאה בהעלאת הקבצים (בדוק סוג/גודל).';
            $flashType = 'error';
        }
    } elseif ($action === 'delete_media') {
        db_run('DELETE FROM room_media WHERE id = ? AND room_id = ?', [(int) $_POST['media_id'], $roomId]);
        $flash = 'המדיה נמחקה.';
    } elseif ($action === 'set_cover') {
        db_run('UPDATE room_media SET is_cover = 0 WHERE room_id = ?', [$roomId]);
        db_run('UPDATE room_media SET is_cover = 1 WHERE id = ? AND room_id = ?', [(int) $_POST['media_id'], $roomId]);
        $flash = 'תמונת השער עודכנה.';
    } elseif ($action === 'move_media') {
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $direction = ($_POST['direction'] ?? '') === 'later' ? 'later' : 'earlier';
        $ids = array_column(get_room_media($roomId), 'id');
        $idx = array_search($mediaId, $ids, true);
        if ($idx !== false) {
            $swapIdx = $direction === 'earlier' ? $idx - 1 : $idx + 1;
            if ($swapIdx >= 0 && $swapIdx < count($ids)) {
                [$ids[$idx], $ids[$swapIdx]] = [$ids[$swapIdx], $ids[$idx]];
                foreach ($ids as $i => $id) {
                    db_run('UPDATE room_media SET sort_order = ? WHERE id = ?', [$i, $id]);
                }
            }
        }
    } elseif ($action === 'delete_room') {
        $bookingCount = db_one('SELECT COUNT(*) AS c FROM bookings WHERE room_id = ?', [$roomId])['c'];
        if ((int) $bookingCount > 0) {
            $flash = 'לא ניתן למחוק חדר עם היסטוריית הזמנות — ניתן להשבית אותו במקום (לבטל את הסימון "חדר פעיל").';
            $flashType = 'error';
        } else {
            db_run('DELETE FROM rooms WHERE id = ? AND site_id = ?', [$roomId, $site['id']]);
            header('Location: rooms.php?site=' . urlencode($slug));
            exit;
        }
    }

    $room = get_room($roomId, (int) $site['id']);
}

$allFacilities = db_all('SELECT * FROM facilities ORDER BY id');
$roomFacilityIds = array_column(get_room_facilities($roomId), 'id');
$schedule = get_room_schedule($roomId);
$media = get_room_media($roomId);
$blocks = db_all('SELECT * FROM availability_blocks WHERE room_id = ? AND block_date >= CURDATE() ORDER BY block_date, start_time', [$roomId]);
$weekdayLabels = ['ראשון','שני','שלישי','רביעי','חמישי','שישי','שבת'];

$active = 'rooms';
require __DIR__ . '/_layout_top.php';
?>

<?php if ($flash): ?><div class="a-alert <?= h($flashType) ?>"><?= h($flash) ?></div><?php endif; ?>

<div class="a-card">
  <h2>פרטי החדר — <?= h($room['name']) ?></h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_details">
    <div class="a-field-row">
      <div><label>שם החדר</label><input type="text" name="name" value="<?= h($room['name']) ?>" required></div>
      <div><label>תפוסה (אורחים)</label><input type="number" name="capacity" value="<?= (int) $room['capacity'] ?>" min="1"></div>
    </div>
    <label>תיאור</label>
    <textarea name="description"><?= h((string) $room['description']) ?></textarea>
    <div class="a-field-row">
      <div>
        <label>מחיר לשעה (₪, תצוגה בלבד)</label>
        <input type="number" step="1" name="price_per_hour" value="<?= (float) $room['price_per_hour'] ?>">
        <label style="margin-top:4px;"><input type="checkbox" name="show_price_per_hour" <?= $room['show_price_per_hour'] ? 'checked' : '' ?> style="width:auto;display:inline-block;"> הצג מחיר לשעה בכרטיס החדר</label>
      </div>
      <div><label>מחיר ליום (₪)</label><input type="number" step="1" name="price_per_day" value="<?= (float) $room['price_per_day'] ?>"></div>
    </div>
    <div class="a-field-row">
      <div><label>מחיר ל-3 שעות (₪)</label><input type="number" step="1" name="price_3h" value="<?= (float) $room['price_3h'] ?>" required></div>
      <div><label>מחיר לכל שעה נוספת (₪)</label><input type="number" step="1" name="price_extra_hour" value="<?= (float) $room['price_extra_hour'] ?>" required></div>
      <div><label>מינימום שעות</label><input type="number" name="min_hours" value="<?= (int) $room['min_hours'] ?>" min="3"></div>
    </div>
    <div class="a-field-row">
      <div><label>גודל (מ״ר)</label><input type="number" name="size_sqm" value="<?= h((string) $room['size_sqm']) ?>"></div>
      <div><label>סוג מיטה</label><input type="text" name="bed_type" value="<?= h((string) $room['bed_type']) ?>" placeholder="לדוגמה: מיטה זוגית"></div>
    </div>
    <label>הערת נהלים (מוצגת בעת ההזמנה)</label>
    <input type="text" name="house_rules_text" value="<?= h((string) $room['house_rules_text']) ?>" placeholder="לדוגמה: עד 2 אורחים, ללא רעש לאחר 23:00">
    <label><input type="checkbox" name="active" <?= $room['active'] ? 'checked' : '' ?> style="width:auto;display:inline-block;"> חדר פעיל ומוצג באתר</label>
    <button class="a-btn" type="submit" style="margin-top:6px;">שמירה</button>
  </form>
  <form method="post" style="margin-top:10px;" onsubmit="return confirm('למחוק את החדר לצמיתות? פעולה זו אינה הפיכה.');">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_room">
    <button class="a-btn danger small" type="submit">מחיקת חדר</button>
  </form>
</div>

<div class="a-card">
  <h2>מתקנים</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_facilities">
    <div class="a-swatch-row">
      <?php foreach ($allFacilities as $f): $checked = in_array($f['id'], $roomFacilityIds, true); ?>
      <label class="a-swatch <?= $checked ? 'is-checked' : '' ?>">
        <input type="checkbox" name="facilities[]" value="<?= (int) $f['id'] ?>" <?= $checked ? 'checked' : '' ?>>
        <?= h($f['label_he']) ?>
      </label>
      <?php endforeach; ?>
    </div>
    <button class="a-btn" type="submit" style="margin-top:14px;">שמירה</button>
  </form>
</div>

<div class="a-card">
  <h2>גלריה</h2>
  <form method="post" enctype="multipart/form-data" class="a-field-row" style="align-items:flex-end;margin-bottom:16px;">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload_media">
    <div style="flex:1;"><label>העלאת תמונות או וידאו (ניתן לבחור כמה קבצים יחד)</label><input type="file" name="media[]" accept="image/*,video/*" multiple required></div>
    <div><button class="a-btn" type="submit">העלאה</button></div>
  </form>
  <?php if (!$media): ?>
    <p style="color:var(--a-faint);font-size:13.5px;">עדיין לא הועלו תמונות.</p>
  <?php else: ?>
  <div class="a-media-grid">
    <?php foreach ($media as $i => $m): ?>
      <div>
        <div class="a-media-item">
          <?php if ($m['is_cover']): ?><span class="cover-badge">ראשי</span><?php endif; ?>
          <?php if ($m['type'] === 'video'): ?>
            <video src="<?= APP_BASE_URL . '/' . h($m['path']) ?>" muted></video>
          <?php else: ?>
            <img src="<?= APP_BASE_URL . '/' . h($m['path']) ?>" alt="">
          <?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_media"><input type="hidden" name="media_id" value="<?= (int) $m['id'] ?>"><button class="del-btn" type="submit" title="מחיקה">✕</button></form>
        </div>
        <div style="display:flex;gap:4px;margin-top:6px;">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move_media"><input type="hidden" name="media_id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="direction" value="earlier"><button class="a-btn secondary small" type="submit" <?= $i === 0 ? 'disabled' : '' ?> title="הזזה קודם">‹</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move_media"><input type="hidden" name="media_id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="direction" value="later"><button class="a-btn secondary small" type="submit" <?= $i === count($media) - 1 ? 'disabled' : '' ?> title="הזזה הבא">›</button></form>
          <?php if (!$m['is_cover']): ?>
          <form method="post" style="flex:1;"><?= csrf_field() ?><input type="hidden" name="action" value="set_cover"><input type="hidden" name="media_id" value="<?= (int) $m['id'] ?>"><button class="a-btn secondary small" type="submit" style="width:100%;">הגדר כראשי</button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="a-card">
  <h2>זמינות שבועית</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_schedule">
    <?php for ($w = 0; $w <= 6; $w++): $day = $schedule[$w] ?? ['is_closed' => 0, 'open_time' => '00:00:00', 'close_time' => '23:59:00']; ?>
    <div class="a-weekday-row">
      <strong><?= h($weekdayLabels[$w]) ?></strong>
      <label class="a-closed-label" style="margin:0;font-weight:400;"><input type="checkbox" name="closed_<?= $w ?>" <?= $day['is_closed'] ? 'checked' : '' ?> style="width:auto;display:inline-block;"> סגור</label>
      <input type="time" name="open_<?= $w ?>" value="<?= substr($day['open_time'], 0, 5) ?>" style="margin:0;">
      <input type="time" name="close_<?= $w ?>" value="<?= substr($day['close_time'], 0, 5) ?>" style="margin:0;">
    </div>
    <?php endfor; ?>
    <button class="a-btn" type="submit" style="margin-top:12px;">שמירה</button>
  </form>
</div>

<div class="a-card">
  <h2>חסימות ידניות</h2>
  <form method="post" class="a-field-row" style="align-items:flex-end;">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_block">
    <div><label>תאריך</label><input type="date" name="block_date" required></div>
    <div><label>משעה</label><input type="time" name="block_start" required></div>
    <div><label>עד שעה</label><input type="time" name="block_end" required></div>
    <div style="flex:2;"><label>סיבה (אופציונלי)</label><input type="text" name="reason" placeholder="לדוגמה: תחזוקה"></div>
    <div><button class="a-btn" type="submit">חסימה</button></div>
  </form>
  <?php if ($blocks): ?>
  <div class="a-table-wrap" style="margin-top:14px;">
    <table>
      <thead><tr><th>תאריך</th><th>שעות</th><th>סיבה</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($blocks as $blk): ?>
        <tr>
          <td><?= h((new DateTime($blk['block_date']))->format('d.m.Y')) ?></td>
          <td><?= substr($blk['start_time'], 0, 5) ?>–<?= substr($blk['end_time'], 0, 5) ?></td>
          <td><?= h($blk['reason']) ?></td>
          <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_block"><input type="hidden" name="block_id" value="<?= (int) $blk['id'] ?>"><button class="a-btn secondary small" type="submit">הסרה</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
