<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$slug = $_GET['site'] ?? '';
$site = $slug ? get_site_by_slug($slug) : null;
if (!$site) { http_response_code(404); echo 'האתר לא נמצא.'; exit; }
$L = load_lang($site['default_lang'] ?: 'he');

$pageTitle = 'מדיניות פרטיות — ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding-block:26px 40px;max-width:720px;">
  <h1 style="font-family:var(--font-display);font-weight:500;"><?= h(t($L,'privacy_title') ?: 'מדיניות פרטיות') ?></h1>
  <div style="font-size:14px;line-height:1.9;color:var(--ink-dim);">
    <p>אנו אוספים רק את הפרטים המינימליים הנדרשים להשלמת הזמנה: שם מלא ומספר טלפון. לא נבקש פרטי זהות, לא נשמור פרטי אשראי באתר, ולא נשתף את פרטיכם עם צד שלישי כלשהו.</p>
    <p>פרטי ההזמנה גלויים אך ורק לבעלי העסק, ומשמשים לצורך תיאום מועד ההגעה בלבד. אין תיעוד ציבורי של אורחים, ואין הצגת פרטי הזמנה לאורחים אחרים.</p>
    <p>הודעות בנוגע להזמנה (אישור, שינוי, ביטול) נשלחות בוואטסאפ ובדוא"ל לבעלי העסק בלבד, בנוסח ניטרלי שאינו חושף פרטים מעבר לנדרש.</p>
    <p>ניתן לבקש בכל עת את מחיקת פרטי ההזמנה שלכם על ידי פנייה ישירה בוואטסאפ או בטלפון המופיעים בעמוד הבית.</p>
    <p>עדכון אחרון: <?= date('d.m.Y') ?></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
