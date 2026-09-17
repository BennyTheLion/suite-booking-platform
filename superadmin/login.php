<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $identifier = login_identifier($username);
    if (is_login_rate_limited('superadmin', $identifier)) {
        $error = 'יותר מדי ניסיונות התחברות. נא לנסות שוב בעוד כמה דקות.';
    } elseif (super_admin_login($username, $password)) {
        clear_login_attempts('superadmin', $identifier);
        header('Location: index.php');
        exit;
    } else {
        record_login_attempt('superadmin', $identifier);
        $error = 'שם משתמש או סיסמה שגויים.';
    }
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>כניסת מנהל מערכת</title>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/admin.css">
</head>
<body>
<div class="a-container" style="max-width:380px;padding-top:80px;">
  <div class="a-card">
    <h2>כניסת מנהל מערכת</h2>
    <?php if ($error): ?><div class="a-alert error"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <label>שם משתמש</label>
      <input type="text" name="username" required autofocus>
      <label>סיסמה</label>
      <input type="password" name="password" required>
      <?= csrf_field() ?>
      <button class="a-btn" type="submit" style="width:100%;">התחברות</button>
    </form>
  </div>
</div>
</body>
</html>
