<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = $_GET['site'] ?? '';
$site = require_admin($slug);

$flash = '';
$flashType = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $booking = db_one('SELECT * FROM bookings WHERE id = ? AND site_id = ?', [$bookingId, $site['id']]);
    if ($booking) {
        if ($action === 'approve') {
            db_run("UPDATE bookings SET status = 'approved' WHERE id = ?", [$bookingId]);
            $flash = 'ההזמנה אושרה.';
        } elseif ($action === 'reject') {
            db_run("UPDATE bookings SET status = 'rejected' WHERE id = ?", [$bookingId]);
            $flash = 'ההזמנה נדחתה.';
        } elseif ($action === 'cancel') {
            db_run("UPDATE bookings SET status = 'cancelled' WHERE id = ?", [$bookingId]);
            $flash = 'ההזמנה בוטלה.';
        } elseif ($action === 'move') {
            $newRoomId = (int) ($_POST['new_room_id'] ?? 0);
            $newRoom = get_room($newRoomId, (int) $site['id']);
            $isFree = $newRoom && ($booking['booking_type'] === 'daily'
                ? is_daily_range_free($newRoomId, $booking['date_start'], (new DateTime($booking['date_end']))->modify('+1 day')->format('Y-m-d'))
                : is_range_free($newRoomId, $booking['date_start'], $booking['time_start'], $booking['time_end']));
            if ($isFree) {
                db_run('UPDATE bookings SET room_id = ? WHERE id = ?', [$newRoomId, $bookingId]);
                $flash = 'ההזמנה הועברה לחדר אחר.';
            } else {
                $flash = 'לא ניתן להעביר — החדר החדש תפוס באותו מועד.';
                $flashType = 'error';
            }
        }
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT b.*, r.name AS room_name FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.site_id = ?";
$params = [$site['id']];
if (in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
    $sql .= ' AND b.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY b.date_start DESC, b.time_start DESC';
$bookings = db_all($sql, $params);

$rooms = db_all('SELECT id, name FROM rooms WHERE site_id = ? AND active = 1 ORDER BY sort_order', [$site['id']]);

$active = 'bookings';
require __DIR__ . '/_layout_top.php';
?>

<?php if ($flash): ?><div class="a-alert <?= h($flashType) ?>"><?= h($flash) ?></div><?php endif; ?>

<div class="a-card">
  <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach (['all' => 'הכל', 'pending' => 'ממתין', 'approved' => 'מאושר', 'rejected' => 'נדחה', 'cancelled' => 'מבוטל'] as $key => $label): ?>
      <a class="a-btn <?= $statusFilter === $key ? '' : 'secondary' ?> small" href="?site=<?= h($slug) ?>&status=<?= $key ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$bookings): ?>
    <p style="color:var(--a-faint);font-size:13.5px;">אין הזמנות להצגה.</p>
  <?php else: ?>
  <div class="a-table-wrap">
    <table>
      <thead><tr><th>חדר</th><th>אורח</th><th>מועד</th><th>סוג</th><th>סטטוס</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td><?= h($b['room_name']) ?></td>
          <td><?= h($b['guest_name']) ?> · <a href="tel:<?= h($b['guest_phone']) ?>"><?= h($b['guest_phone']) ?></a></td>
          <td><?= h(format_booking_range($b)) ?></td>
          <td><?= $b['booking_type'] === 'daily' ? 'יום מלא' : 'שעתי' ?></td>
          <td><span class="a-pill <?= $b['status'] ?>"><?php
            echo ['pending'=>'ממתין','approved'=>'מאושר','rejected'=>'נדחה','cancelled'=>'מבוטל'][$b['status']];
          ?></span></td>
          <td style="white-space:nowrap;">
            <?php if ($b['status'] === 'pending'): ?>
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="action" value="approve"><button class="a-btn ok small" type="submit">אשר</button></form>
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="action" value="reject"><button class="a-btn danger small" type="submit">דחה</button></form>
            <?php elseif ($b['status'] === 'approved'): ?>
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="action" value="cancel"><button class="a-btn secondary small" type="submit">ביטול</button></form>
            <?php endif; ?>
            <?php if (in_array($b['status'], ['pending','approved'], true) && count($rooms) > 1): ?>
              <form method="post" style="display:inline-flex;gap:4px;align-items:center;">
                <?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                <input type="hidden" name="action" value="move">
                <select name="new_room_id" style="margin:0;width:auto;padding:5px 8px;">
                  <?php foreach ($rooms as $r): if ((int) $r['id'] === (int) $b['room_id']) continue; ?>
                    <option value="<?= (int) $r['id'] ?>"><?= h($r['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="a-btn secondary small" type="submit">העבר</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
