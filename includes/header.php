<?php
/**
 * Expects $site (array) and $L (lang array) to be set by the including page.
 * Optional: $pageTitle, $backLink (['href'=>.., 'label'=>..])
 */
$paletteAttr = $site['palette_key'] ? ' data-palette="' . h($site['palette_key']) . '"' : '';
$fontAttr = $site['font_key'] ? ' data-font="' . h($site['font_key']) . '"' : '';
$headerClass = 'site-header' . (!empty($headerOverlay) ? ' site-header--overlay' : '');
?>
<!doctype html>
<html lang="<?= h($L['lang_code']) ?>" dir="<?= h($L['dir']) ?>"<?= $paletteAttr . $fontAttr ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($pageTitle ?? $site['name']) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700&family=Assistant:wght@400;500;600;700&family=David+Libre:wght@400;500;700&family=Heebo:wght@300;400;500;600;700&family=Suez+One&family=Rubik:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div dir="<?= h($L['dir']) ?>">
  <header class="<?= $headerClass ?>" id="siteHeader">
    <div class="container">
      <div class="topbar">
        <a class="brand" href="<?= APP_BASE_URL . '/' . h($site['slug']) . '/' ?>">
          <div class="brand-mark">
            <?php if (!empty($site['logo_path'])): ?>
              <img src="<?= APP_BASE_URL . '/' . h($site['logo_path']) ?>" alt="">
            <?php else: ?>
              <?= h(mb_substr($site['name'], 0, 1)) ?>
            <?php endif; ?>
          </div>
          <div class="brand-name"><?= h($site['name']) ?></div>
        </a>
        <div class="topbar-actions">
          <details class="legal-menu">
            <summary aria-label="מידע משפטי">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M12 3 4 6.5v5c0 5 3.4 8.4 8 9.5 4.6-1.1 8-4.5 8-9.5v-5L12 3Z"/></svg>
            </summary>
            <div class="legal-menu-panel">
              <a href="<?= APP_BASE_URL . '/privacy.php?site=' . h($site['slug']) ?>">מדיניות פרטיות</a>
              <a href="<?= APP_BASE_URL . '/terms.php?site=' . h($site['slug']) ?>">תנאי שימוש</a>
              <a href="<?= APP_BASE_URL . '/accessibility.php?site=' . h($site['slug']) ?>">הצהרת נגישות</a>
            </div>
          </details>
          <a class="lang-toggle" href="?lang=<?= $L['lang_code'] === 'he' ? 'en' : 'he' ?>"><?= h(t($L, 'lang_switch')) ?></a>
        </div>
      </div>
    </div>
  </header>
