<?php
/** Expects $site (array) and optional $active nav key and $pendingCount */
$slug = $site['slug'];
$pendingCount = $pendingCount ?? count_unread_notifications((int) $site['id']);
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>ניהול — <?= h($site['name']) ?></title>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/admin.css">
</head>
<body>
<header class="a-header">
  <div class="a-container">
    <div class="a-header-inner">
      <div class="a-brand"><?= h($site['name']) ?><small>לוח ניהול</small></div>
      <div style="display:flex;align-items:center;gap:10px;">
        <?php if (defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== ''): ?>
        <button class="a-btn secondary small" type="button" id="pushToggle" hidden>הפעלת התראות</button>
        <?php endif; ?>
        <a class="a-logout" href="logout.php?site=<?= h($slug) ?>">התנתקות</a>
      </div>
    </div>
    <nav class="a-nav">
      <a href="index.php?site=<?= h($slug) ?>" class="<?= ($active ?? '') === 'dashboard' ? 'is-active' : '' ?>">לוח בקרה<?php if ($pendingCount): ?><span class="a-badge"><?= $pendingCount ?></span><?php endif; ?></a>
      <a href="rooms.php?site=<?= h($slug) ?>" class="<?= ($active ?? '') === 'rooms' ? 'is-active' : '' ?>">חדרים</a>
      <a href="bookings.php?site=<?= h($slug) ?>" class="<?= ($active ?? '') === 'bookings' ? 'is-active' : '' ?>">הזמנות</a>
      <a href="settings.php?site=<?= h($slug) ?>" class="<?= ($active ?? '') === 'settings' ? 'is-active' : '' ?>">הגדרות</a>
      <a href="<?= APP_BASE_URL . '/' . h($slug) . '/' ?>" target="_blank">צפייה באתר ↗</a>
    </nav>
  </div>
</header>
<main class="a-container" style="padding-top:22px;">
