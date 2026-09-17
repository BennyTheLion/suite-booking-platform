<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$slug = $_GET['site'] ?? '';
$site = $slug ? get_site_by_slug($slug) : null;
if (!$site) { http_response_code(404); echo 'האתר לא נמצא.'; exit; }
$L = load_lang($site['default_lang'] ?: 'he');

$cancelHours = (int) ($site['cancellation_hours'] ?: 3);
$pageTitle = 'תנאי שימוש — ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding-block:26px 40px;max-width:720px;">
  <h1 style="font-family:var(--font-display);font-weight:500;">תנאי שימוש והזמנה</h1>
  <div style="font-size:14px;line-height:1.9;color:var(--ink-dim);">
    <p>הזמנה באתר זה היא בקשה בלבד, הממתינה לאישור בעלי העסק. ההזמנה תיחשב סופית רק לאחר קבלת אישור בוואטסאפ או בטלפון.</p>
    <p>ניתן לבטל או לשנות הזמנה ללא עלות עד <?= $cancelHours ?> שעות לפני מועד ההגעה. ביטול בסמוך יותר למועד עשוי לחייב תשלום, בהתאם לשיקול דעת בעלי העסק.</p>
    <p>יש להגיע בהתאם למספר האורחים שצוין בעת ההזמנה. חריגה ממספר האורחים המרבי לחדר עשויה להוביל לביטול ההזמנה במקום.</p>
    <p>העסק שומר לעצמו את הזכות לסרב להזמנה או לבטלה מכל סיבה סבירה, לרבות תחזוקה, חוסר זמינות בפועל או חשש להפרת נהלי הבית.</p>
    <p>המחירים המוצגים באתר כוללים מע"מ אלא אם צוין אחרת, ועשויים להשתנות ללא הודעה מוקדמת.</p>
    <p>עדכון אחרון: <?= date('d.m.Y') ?></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
