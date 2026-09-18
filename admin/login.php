<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = $_GET['site'] ?? '';
$site = $slug ? get_site_by_slug($slug) : null;
if (!$site) { http_response_code(404); echo 'האתר לא נמצא.'; exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $identifier = login_identifier($username);
    if (is_login_rate_limited('admin', $identifier)) {
        $error = 'יותר מדי ניסיונות התחברות. נא לנסות שוב בעוד כמה דקות.';
    } elseif (admin_login((int) $site['id'], $username, $password)) {
        clear_login_attempts('admin', $identifier);
        header('Location: index.php?site=' . urlencode($slug));
        exit;
    } else {
        record_login_attempt('admin', $identifier);
        $error = 'שם משתמש או סיסמה שגויים.';
    }
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>כניסת מנהל — <?= h($site['name']) ?></title>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/admin.css">
</head>
<body>
<div class="a-container" style="max-width:380px;padding-top:80px;">
  <div class="a-card">
    <h2><?= h($site['name']) ?> — כניסת מנהל</h2>
    <?php if ($error): ?><div class="a-alert error"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <label>שם משתמש</label>
      <input type="text" name="username" required autofocus>
      <label>סיסמה</label>
      <div class="pwd-wrap"><input type="password" name="password" required><?= pwd_toggle_button() ?></div>
      <?= csrf_field() ?>
      <button class="a-btn" type="submit" style="width:100%;">התחברות</button>
    </form>
  </div>
</div>
<script src="<?= APP_BASE_URL ?>/assets/js/pwd-toggle.js"></script>
</body>
</html>
