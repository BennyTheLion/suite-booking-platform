<?php
// One-time setup: creates the first super-admin account. Works only while none exists.
// Delete this file after use.
require_once __DIR__ . '/includes/functions.php';

$existing = db_one('SELECT id FROM super_admins LIMIT 1');
if ($existing) {
    http_response_code(403);
    echo 'כבר קיים מנהל מערכת. יש למחוק את setup_superadmin.php.';
    exit;
}

$done = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || strlen($password) < 6) {
        $error = 'נא להזין שם משתמש וסיסמה של לפחות 6 תווים.';
    } else {
        db_run('INSERT INTO super_admins (username, password_hash) VALUES (?, ?)', [$username, password_hash($password, PASSWORD_DEFAULT)]);
        $done = true;
    }
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>הקמת מנהל מערכת</title>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/admin.css">
</head>
<body>
<div class="a-container" style="max-width:400px;padding-top:80px;">
  <div class="a-card">
    <?php if ($done): ?>
      <h2>מנהל המערכת נוצר בהצלחה</h2>
      <p>כעת יש למחוק את הקובץ <code>setup_superadmin.php</code> מהשרת מטעמי אבטחה.</p>
      <a class="a-btn" href="superadmin/login.php">כניסה ללוח מנהל המערכת</a>
    <?php else: ?>
      <h2>הקמת מנהל מערכת ראשון</h2>
      <?php if ($error): ?><div class="a-alert error"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <label>שם משתמש</label>
        <input type="text" name="username" required autofocus>
        <label>סיסמה</label>
        <div class="pwd-wrap"><input type="password" name="password" required minlength="6"><?= pwd_toggle_button() ?></div>
        <button class="a-btn" type="submit" style="width:100%;">יצירה</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<script src="<?= APP_BASE_URL ?>/assets/js/pwd-toggle.js"></script>
</body>
</html>
