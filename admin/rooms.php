<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = $_GET['site'] ?? '';
$site = require_admin($slug);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    csrf_verify();
    $name = trim((string) ($_POST['name'] ?? 'חדר חדש'));
    $roomSlug = slugify($name);
    $exists = db_one('SELECT id FROM rooms WHERE site_id = ? AND slug = ?', [$site['id'], $roomSlug]);
    if ($exists) $roomSlug .= '-' . substr(uniqid(), -4);
    db_run(
        'INSERT INTO rooms (site_id, name, slug, description, price_per_hour, price_per_day, price_3h, price_extra_hour, min_hours, capacity) VALUES (?, ?, ?, "", 100, 400, 300, 80, 3, 2)',
        [(int) $site['id'], $name, $roomSlug]
    );
    $newId = db_insert_id();
    // seed a default weekly schedule so the room isn't invisible on the booking calendar
    for ($w = 0; $w <= 6; $w++) {
        db_run('INSERT INTO availability_schedule (room_id, weekday, is_closed, open_time, close_time) VALUES (?, ?, 0, "00:00:00", "23:59:00")', [$newId, $w]);
    }
    header('Location: room_edit.php?site=' . urlencode($slug) . '&id=' . $newId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'duplicate') {
    csrf_verify();
    $sourceId = (int) ($_POST['room_id'] ?? 0);
    $newId = duplicate_room($sourceId, (int) $site['id']);
    if ($newId) {
        header('Location: room_edit.php?site=' . urlencode($slug) . '&id=' . $newId);
        exit;
    }
    $flash = 'שכפול החדר נכשל.';
    $flashType = 'error';
}

$rooms = db_all('SELECT * FROM rooms WHERE site_id = ? ORDER BY sort_order, id', [$site['id']]);

$active = 'rooms';
require __DIR__ . '/_layout_top.php';
?>

<?php if (!empty($flash)): ?><div class="a-alert <?= h($flashType ?? 'ok') ?>"><?= h($flash) ?></div><?php endif; ?>

<div class="a-card">
  <h2>הוספת חדר</h2>
  <form method="post" class="a-field-row" style="align-items:flex-end;">
    <input type="hidden" name="action" value="create">
    <?= csrf_field() ?>
    <div style="flex:2;"><label>שם החדר</label><input type="text" name="name" required placeholder="לדוגמה: חדר הענבר"></div>
    <div style="flex:0 0 auto;"><button class="a-btn" type="submit">הוספה</button></div>
  </form>
</div>

<div class="a-card">
  <h2>החדרים שלך</h2>
  <?php if (!$rooms): ?>
    <p style="color:var(--a-faint);font-size:13.5px;">עדיין לא נוספו חדרים.</p>
  <?php else: ?>
  <div class="a-table-wrap">
    <table>
      <thead><tr><th>שם</th><th>מחיר ל-3 שעות</th><th>שעה נוספת</th><th>תפוסה</th><th>סטטוס</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rooms as $r): ?>
        <tr>
          <td><?= h($r['name']) ?></td>
          <td>₪<?= (int) $r['price_3h'] ?></td>
          <td>₪<?= (int) $r['price_extra_hour'] ?></td>
          <td><?= (int) $r['capacity'] ?></td>
          <td><span class="a-pill <?= $r['active'] ? 'approved' : 'cancelled' ?>"><?= $r['active'] ? 'פעיל' : 'לא פעיל' ?></span></td>
          <td style="white-space:nowrap;">
            <a class="a-btn secondary small" href="room_edit.php?site=<?= h($slug) ?>&id=<?= (int) $r['id'] ?>">עריכה</a>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="room_id" value="<?= (int) $r['id'] ?>">
              <button class="a-btn secondary small" type="submit">שכפול</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
