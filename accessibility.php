<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$slug = $_GET['site'] ?? '';
$site = $slug ? get_site_by_slug($slug) : null;
if (!$site) { http_response_code(404); echo 'האתר לא נמצא.'; exit; }
$L = load_lang($site['default_lang'] ?: 'he');

$pageTitle = 'הצהרת נגישות — ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding-block:26px 40px;max-width:720px;">
  <h1 style="font-family:var(--font-display);font-weight:500;">הצהרת נגישות</h1>
  <div style="font-size:14px;line-height:1.9;color:var(--ink-dim);">
    <p>אנו פועלים להנגיש את האתר לכלל המשתמשים, לרבות אנשים עם מוגבלות, בהתאם לתקנות שוויון זכויות לאנשים עם מוגבלות (התאמות נגישות לשירות), התשע"ג-2013.</p>
    <p>באתר מותקן תפריט נגישות (הסמל הצף בפינת המסך) המאפשר, בין היתר: הגדלה והקטנה של גודל הטקסט, הצגת ניגודיות גבוהה, הדגשת קישורים, ועצירת אנימציות נעות.</p>
    <p>האתר תוכנן לתמוך בניווט מקלדת, טקסט חלופי לתמונות מרכזיות, ותאימות לטכנולוגיות מסייעות מקובלות.</p>
    <p>במידה ונתקלתם בבעיית נגישות באתר, נשמח שתפנו אלינו בטלפון או בוואטסאפ המופיעים בעמוד הבית ונטפל בכך בהקדם.</p>
    <p>עדכון אחרון: <?= date('d.m.Y') ?></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
