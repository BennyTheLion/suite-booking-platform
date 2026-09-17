<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

$siteId = (int) ($_GET['id'] ?? 0);
$site = get_site($siteId);
if (!$site) { http_response_code(404); echo 'האתר לא נמצא.'; exit; }

$flash = '';
$flashType = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_site') {
        $newSlug = slugify($_POST['slug'] ?: $site['slug']);
        $clash = db_one('SELECT id FROM sites WHERE slug = ? AND id != ?', [$newSlug, $siteId]);
        if ($clash) {
            $flash = 'כתובת (slug) זו כבר תפוסה.';
            $flashType = 'error';
        } else {
            db_run(
                'UPDATE sites SET name=?, slug=?, palette_key=?, font_key=?, active=? WHERE id=?',
                [trim((string) $_POST['name']), $newSlug, $_POST['palette_key'] ?? '', $_POST['font_key'] ?? '', isset($_POST['active']) ? 1 : 0, $siteId]
            );
            $flash = 'האתר עודכן.';
        }
    } elseif ($action === 'add_admin') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($username && $password) {
            $exists = db_one('SELECT id FROM admins WHERE site_id = ? AND username = ?', [$siteId, $username]);
            if ($exists) {
                $flash = 'שם המשתמש כבר קיים עבור אתר זה.';
                $flashType = 'error';
            } else {
                db_run('INSERT INTO admins (site_id, username, password_hash) VALUES (?, ?, ?)', [$siteId, $username, password_hash($password, PASSWORD_DEFAULT)]);
                $flash = 'מנהל אתר נוסף.';
            }
        }
    } elseif ($action === 'delete_admin') {
        db_run('DELETE FROM admins WHERE id = ? AND site_id = ?', [(int) $_POST['admin_id'], $siteId]);
        $flash = 'מנהל האתר הוסר.';
    } elseif ($action === 'reset_password') {
        $newPass = (string) ($_POST['new_password'] ?? '');
        if ($newPass) {
            db_run('UPDATE admins SET password_hash = ? WHERE id = ? AND site_id = ?', [password_hash($newPass, PASSWORD_DEFAULT), (int) $_POST['admin_id'], $siteId]);
            $flash = 'הסיסמה אופסה.';
        }
    } elseif ($action === 'delete_site') {
        if (trim((string) ($_POST['confirm_slug'] ?? '')) === $site['slug']) {
            db_run('DELETE FROM sites WHERE id = ?', [$siteId]);
            header('Location: index.php');
            exit;
        }
        $flash = 'הכתובת שהוקלדה לאישור אינה תואמת — האתר לא נמחק.';
        $flashType = 'error';
    }

    $site = get_site($siteId);
}

$admins = db_all('SELECT * FROM admins WHERE site_id = ? ORDER BY created_at', [$siteId]);
$palettes = palette_options();
$fonts = font_options();

require __DIR__ . '/_layout_top.php';
?>

<?php if ($flash): ?><div class="a-alert <?= h($flashType) ?>"><?= h($flash) ?></div><?php endif; ?>

<div class="a-card">
  <h2>הגדרות אתר — <?= h($site['name']) ?></h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_site">
    <div class="a-field-row">
      <div><label>שם העסק</label><input type="text" name="name" value="<?= h($site['name']) ?>" required></div>
      <div><label>כתובת (slug)</label><input type="text" name="slug" value="<?= h($site['slug']) ?>" required></div>
    </div>
    <label><input type="checkbox" name="active" <?= $site['active'] ? 'checked' : '' ?> style="width:auto;display:inline-block;"> אתר פעיל</label>

    <label style="margin-top:14px;">פלטת צבעים (רקע, טקסט ודגש)</label>
    <div class="a-swatch-row">
      <?php foreach ($palettes as $key => $p): $checked = ($site['palette_key'] ?: '') === $key; ?>
      <label class="a-swatch <?= $checked ? 'is-checked' : '' ?>">
        <input type="radio" name="palette_key" value="<?= h($key) ?>" <?= $checked ? 'checked' : '' ?>>
        <span class="dot" style="background:conic-gradient(<?= h($p['accent']) ?> 0 50%, <?= h($p['ground']) ?> 50% 100%);border:1px solid #ddd;"></span>
        <?= h($p['label']) ?>
      </label>
      <?php endforeach; ?>
    </div>

    <label style="margin-top:14px;">גופן</label>
    <div class="a-swatch-row">
      <?php foreach ($fonts as $key => $label): $checked = ($site['font_key'] ?: '') === $key; ?>
      <label class="a-swatch <?= $checked ? 'is-checked' : '' ?>">
        <input type="radio" name="font_key" value="<?= h($key) ?>" <?= $checked ? 'checked' : '' ?>>
        <?= h($label) ?>
      </label>
      <?php endforeach; ?>
    </div>

    <button class="a-btn" type="submit" style="margin-top:16px;">שמירה</button>
  </form>
</div>

<div class="a-card">
  <h2>מנהלי האתר</h2>
  <?php if ($admins): ?>
  <div class="a-table-wrap" style="margin-bottom:16px;">
    <table>
      <thead><tr><th>שם משתמש</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): ?>
      <tr>
        <td><?= h($a['username']) ?></td>
        <td style="white-space:nowrap;">
          <form method="post" style="display:inline-flex;gap:4px;">
            <?= csrf_field() ?><input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="admin_id" value="<?= (int) $a['id'] ?>">
            <input type="text" name="new_password" placeholder="סיסמה חדשה" style="margin:0;width:130px;">
            <button class="a-btn secondary small" type="submit">איפוס סיסמה</button>
          </form>
          <form method="post" style="display:inline;" onsubmit="return confirm('להסיר מנהל זה?');">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_admin">
            <input type="hidden" name="admin_id" value="<?= (int) $a['id'] ?>">
            <button class="a-btn danger small" type="submit">הסרה</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <form method="post" class="a-field-row" style="align-items:flex-end;">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_admin">
    <div><label>שם משתמש</label><input type="text" name="username" required></div>
    <div><label>סיסמה</label><input type="text" name="password" required></div>
    <div><button class="a-btn" type="submit">הוספת מנהל</button></div>
  </form>
</div>

<a class="a-btn secondary" target="_blank" href="<?= APP_BASE_URL . '/admin/login.php?site=' . h($site['slug']) ?>">כניסה ללוח הניהול של האתר ↗</a>

<div class="a-card" style="margin-top:18px;border-color:var(--a-danger);">
  <h2 style="color:var(--a-danger);">אזור מסוכן</h2>
  <p style="font-size:12.5px;color:var(--a-faint);">מחיקת אתר מוחקת לצמיתות את כל החדרים, ההזמנות, המנהלים והמדיה שלו. פעולה זו אינה הפיכה.</p>
  <form method="post" onsubmit="return confirm('בטוח שברצונך למחוק את האתר לצמיתות?');" class="a-field-row" style="align-items:flex-end;">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_site">
    <div><label>הקלד/י את כתובת האתר (<code><?= h($site['slug']) ?></code>) לאישור</label><input type="text" name="confirm_slug" required></div>
    <div><button class="a-btn danger" type="submit">מחיקת האתר לצמיתות</button></div>
  </form>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
