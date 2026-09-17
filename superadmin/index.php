<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_site') {
    csrf_verify();
    $name = trim((string) ($_POST['name'] ?? 'עסק חדש'));
    $slug = slugify($_POST['slug'] ?: $name);
    $exists = db_one('SELECT id FROM sites WHERE slug = ?', [$slug]);
    if ($exists) {
        $flash = 'כתובת (slug) זו כבר תפוסה — נא לבחור אחרת.';
    } else {
        db_run('INSERT INTO sites (slug, name) VALUES (?, ?)', [$slug, $name]);
        $siteId = db_insert_id();

        $adminUser = trim((string) ($_POST['admin_username'] ?? 'admin'));
        $adminPass = (string) ($_POST['admin_password'] ?? '');
        if ($adminUser && $adminPass) {
            db_run('INSERT INTO admins (site_id, username, password_hash) VALUES (?, ?, ?)', [$siteId, $adminUser, password_hash($adminPass, PASSWORD_DEFAULT)]);
        }

        // seed one demo room so the new site isn't empty
        db_run('INSERT INTO rooms (site_id, name, slug, description, price_per_hour, price_per_day, price_3h, price_extra_hour, min_hours, capacity) VALUES (?, "חדר 1", "room-1", "", 150, 600, 400, 100, 3, 2)', [$siteId]);
        $roomId = db_insert_id();
        for ($w = 0; $w <= 6; $w++) {
            db_run('INSERT INTO availability_schedule (room_id, weekday, is_closed, open_time, close_time) VALUES (?, ?, 0, "00:00:00", "23:59:00")', [$roomId, $w]);
        }

        header('Location: site_edit.php?id=' . $siteId);
        exit;
    }
}

$sites = db_all('SELECT * FROM sites ORDER BY created_at DESC');
require __DIR__ . '/_layout_top.php';
?>

<?php if ($flash): ?><div class="a-alert error"><?= h($flash) ?></div><?php endif; ?>

<div class="a-card">
  <h2>הוספת אתר חדש</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="create_site">
    <div class="a-field-row">
      <div><label>שם העסק</label><input type="text" name="name" required></div>
      <div><label>כתובת (slug)</label><input type="text" name="slug" placeholder="נוצר אוטומטית משם העסק אם ריק"></div>
    </div>
    <div class="a-field-row">
      <div><label>שם משתמש למנהל האתר</label><input type="text" name="admin_username" value="admin"></div>
      <div><label>סיסמת מנהל האתר</label><input type="text" name="admin_password" required></div>
    </div>
    <button class="a-btn" type="submit">יצירת אתר</button>
  </form>
</div>

<div class="a-card">
  <h2>אתרים קיימים</h2>
  <?php if (!$sites): ?>
    <p style="color:var(--a-faint);font-size:13.5px;">אין אתרים עדיין.</p>
  <?php else: ?>
  <div class="a-table-wrap">
    <table>
      <thead><tr><th>שם</th><th>כתובת</th><th>פלטה</th><th>סטטוס</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($sites as $s): ?>
        <tr>
          <td><?= h($s['name']) ?></td>
          <td><code><?= h($s['slug']) ?></code></td>
          <td><?= h($s['palette_key'] ?: 'ברירת מחדל') ?></td>
          <td><span class="a-pill <?= $s['active'] ? 'approved' : 'cancelled' ?>"><?= $s['active'] ? 'פעיל' : 'מושבת' ?></span></td>
          <td style="white-space:nowrap;">
            <a class="a-btn secondary small" href="site_edit.php?id=<?= (int) $s['id'] ?>">ניהול</a>
            <a class="a-btn secondary small" target="_blank" href="<?= APP_BASE_URL . '/' . h($s['slug']) . '/' ?>">צפייה ↗</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
