<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = $_GET['site'] ?? '';
$site = require_admin($slug);

$pendingCount = count_unread_notifications((int) $site['id']);
$pending = db_all(
    "SELECT b.*, r.name AS room_name FROM bookings b JOIN rooms r ON r.id = b.room_id
     WHERE b.site_id = ? AND b.status = 'pending' ORDER BY b.created_at DESC",
    [$site['id']]
);
$roomsCount = db_one('SELECT COUNT(*) AS c FROM rooms WHERE site_id = ? AND active = 1', [$site['id']])['c'];
$todayCount = db_one(
    "SELECT COUNT(*) AS c FROM bookings WHERE site_id = ? AND status = 'approved' AND ? BETWEEN date_start AND date_end",
    [$site['id'], date('Y-m-d')]
)['c'];

// mark notifications read once viewed on the dashboard
db_run('UPDATE admin_notifications SET is_read = 1 WHERE site_id = ?', [$site['id']]);

$active = 'dashboard';
require __DIR__ . '/_layout_top.php';
?>

<div class="a-grid cols-3" style="margin-bottom:20px;">
  <div class="a-stat"><div class="num"><?= (int) count($pending) ?></div><div class="label">בקשות ממתינות</div></div>
  <div class="a-stat"><div class="num"><?= (int) $roomsCount ?></div><div class="label">חדרים פעילים</div></div>
  <div class="a-stat"><div class="num"><?= (int) $todayCount ?></div><div class="label">הזמנות מאושרות היום</div></div>
</div>

<div class="a-card">
  <h2>בקשות ממתינות לאישור</h2>
  <?php if (!$pending): ?>
    <p style="color:var(--a-faint);font-size:13.5px;">אין בקשות חדשות כרגע.</p>
  <?php else: ?>
  <div class="a-table-wrap">
    <table>
      <thead><tr><th>חדר</th><th>אורח</th><th>טלפון</th><th>מועד</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pending as $b): ?>
        <tr>
          <td><?= h($b['room_name']) ?></td>
          <td><?= h($b['guest_name']) ?></td>
          <td><a href="tel:<?= h($b['guest_phone']) ?>"><?= h($b['guest_phone']) ?></a></td>
          <td><?= h(format_booking_range($b)) ?></td>
          <td style="white-space:nowrap;">
            <form method="post" action="bookings.php?site=<?= h($slug) ?>" style="display:inline;">
              <?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button class="a-btn ok small" type="submit">אשר</button>
            </form>
            <form method="post" action="bookings.php?site=<?= h($slug) ?>" style="display:inline;">
              <?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <button class="a-btn danger small" type="submit">דחה</button>
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
