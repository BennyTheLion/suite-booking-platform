<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = $_GET['site'] ?? '';
$site = require_admin($slug);

$flash = '';
$flashType = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        db_run(
            'UPDATE sites SET name=?, tagline=?, phone=?, whatsapp=?, email=?, address=?, location_text=?, location_lat=?, location_lng=?,
             instagram_url=?, facebook_url=?, tiktok_url=?, cancellation_hours=?,
             trust1_title=?, trust1_text=?, trust2_title=?, trust2_text=?, trust3_title=?, trust3_text=? WHERE id=?',
            [
                trim((string) $_POST['name']), trim((string) $_POST['tagline']),
                trim((string) $_POST['phone']), trim((string) $_POST['whatsapp']), trim((string) $_POST['email']),
                trim((string) $_POST['address']), trim((string) $_POST['location_text']),
                $_POST['location_lat'] !== '' ? (float) $_POST['location_lat'] : null,
                $_POST['location_lng'] !== '' ? (float) $_POST['location_lng'] : null,
                trim((string) $_POST['instagram_url']), trim((string) $_POST['facebook_url']), trim((string) $_POST['tiktok_url']),
                max(0, (int) $_POST['cancellation_hours']),
                trim((string) $_POST['trust1_title']), trim((string) $_POST['trust1_text']),
                trim((string) $_POST['trust2_title']), trim((string) $_POST['trust2_text']),
                trim((string) $_POST['trust3_title']), trim((string) $_POST['trust3_text']),
                (int) $site['id'],
            ]
        );
        $flash = 'ההגדרות נשמרו.';
    } elseif ($action === 'upload_logo' && !empty($_FILES['logo']['name'])) {
        $path = upload_site_image((int) $site['id'], $_FILES['logo'], 'logo');
        if ($path) { db_run('UPDATE sites SET logo_path = ? WHERE id = ?', [$path, $site['id']]); $flash = 'הלוגו הועלה.'; }
        else { $flash = 'שגיאה בהעלאת הלוגו.'; $flashType = 'error'; }
    } elseif ($action === 'upload_hero') {
        $position = in_array($_POST['hero_position'] ?? '', ['top', 'center', 'bottom'], true) ? $_POST['hero_position'] : 'center';
        if (!empty($_FILES['hero']['name'])) {
            $path = upload_site_image((int) $site['id'], $_FILES['hero'], 'hero');
            if ($path) { db_run('UPDATE sites SET hero_image = ?, hero_position = ? WHERE id = ?', [$path, $position, $site['id']]); $flash = 'תמונת הכותרת הועלתה.'; }
            else { $flash = 'שגיאה בהעלאת התמונה.'; $flashType = 'error'; }
        } else {
            db_run('UPDATE sites SET hero_position = ? WHERE id = ?', [$position, $site['id']]);
            $flash = 'מיקום התמונה עודכן.';
        }
    }

    $site = get_site((int) $site['id']);
}

$active = 'settings';
require __DIR__ . '/_layout_top.php';
?>

<?php if ($flash): ?><div class="a-alert <?= h($flashType) ?>"><?= h($flash) ?></div><?php endif; ?>

<div class="a-card">
  <h2>מיתוג</h2>
  <div class="a-field-row">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload_logo">
      <label>לוגו</label>
      <?php if ($site['logo_path']): ?><img src="<?= APP_BASE_URL . '/' . h($site['logo_path']) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px;margin-bottom:8px;display:block;"><?php endif; ?>
      <input type="file" name="logo" accept="image/*">
      <button class="a-btn secondary small" type="submit">העלאה</button>
    </form>
    <form method="post" enctype="multipart/form-data" id="heroForm">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload_hero">
      <label>תמונת רקע לכותרת הראשית (Hero)</label>
      <?php if ($site['hero_image']): ?><img src="<?= APP_BASE_URL . '/' . h($site['hero_image']) ?>" style="width:100%;max-width:220px;aspect-ratio:12/5;object-fit:cover;border-radius:8px;margin-bottom:8px;display:block;"><?php endif; ?>
      <input type="file" name="hero" id="heroFileInput" accept="image/*">
      <p style="font-size:12px;color:var(--a-faint);margin:6px 0 0;">לאחר בחירת תמונה ניתן לגרור ולהתאים את מסגרת החיתוך בחופשיות ליחס הרצוי (הכותרת באתר תופסת את כל גובה המסך).</p>
      <label style="margin-top:8px;">מיקום התמונה (לתצוגות שבהן היחס שונה מהחיתוך שנבחר)</label>
      <select name="hero_position">
        <option value="top" <?= ($site['hero_position'] ?? 'center') === 'top' ? 'selected' : '' ?>>למעלה</option>
        <option value="center" <?= ($site['hero_position'] ?? 'center') === 'center' ? 'selected' : '' ?>>מרכז</option>
        <option value="bottom" <?= ($site['hero_position'] ?? 'center') === 'bottom' ? 'selected' : '' ?>>למטה</option>
      </select>
      <button class="a-btn secondary small" type="submit" style="margin-top:8px;" id="heroSaveBtn">שמירה</button>
    </form>
  </div>
  <p style="font-size:12px;color:var(--a-faint);margin-top:10px;">
    ערכת הצבעים והגופן של האתר (<?= $site['palette_key'] ?: 'ברירת מחדל' ?> / <?= $site['font_key'] ?: 'ברירת מחדל' ?>) מוגדרים על ידי מנהל המערכת.
  </p>
</div>

<div class="a-card" id="heroCropCard" hidden>
  <h2>חיתוך תמונת הכותרת</h2>
  <div id="heroCropWrap" style="height:60vh;background:#111;border-radius:8px;">
    <img id="heroCropImg" style="max-width:100%;display:block;">
  </div>
  <div style="display:flex;gap:8px;margin-top:12px;">
    <button class="a-btn small" type="button" id="heroCropConfirm">אישור חיתוך ושמירה</button>
    <button class="a-btn secondary small" type="button" id="heroCropCancel">ביטול</button>
  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
(function () {
  var fileInput = document.getElementById('heroFileInput');
  var form = document.getElementById('heroForm');
  var cropCard = document.getElementById('heroCropCard');
  var cropImg = document.getElementById('heroCropImg');
  var saveBtn = document.getElementById('heroSaveBtn');
  var cropper = null;

  function resetCropper() {
    if (cropper) { cropper.destroy(); cropper = null; }
    cropCard.hidden = true;
  }

  fileInput.addEventListener('change', function () {
    var file = fileInput.files[0];
    if (!file) return;
    saveBtn.hidden = true;
    var reader = new FileReader();
    reader.onload = function (e) {
      cropImg.src = e.target.result;
      cropCard.hidden = false;
      cropCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (cropper) cropper.destroy();
      cropper = new Cropper(cropImg, { viewMode: 1, autoCropArea: 1, background: false });
    };
    reader.readAsDataURL(file);
  });

  document.getElementById('heroCropCancel').addEventListener('click', function () {
    fileInput.value = '';
    saveBtn.hidden = false;
    resetCropper();
  });

  document.getElementById('heroCropConfirm').addEventListener('click', function () {
    if (!cropper) return;
    var data = cropper.getData();
    var canvasOpts = data.width > 2400 ? { width: 2400, height: Math.round(2400 * data.height / data.width) } : {};
    cropper.getCroppedCanvas(canvasOpts).toBlob(function (blob) {
      var croppedFile = new File([blob], 'hero.jpg', { type: 'image/jpeg' });
      var dt = new DataTransfer();
      dt.items.add(croppedFile);
      fileInput.files = dt.files;
      resetCropper();
      saveBtn.hidden = false;
      form.submit();
    }, 'image/jpeg', 0.9);
  });
})();
</script>

<div class="a-card">
  <h2>פרטי העסק</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_info">
    <div class="a-field-row">
      <div><label>שם העסק</label><input type="text" name="name" value="<?= h($site['name']) ?>" required></div>
      <div><label>תיאור קצר (Tagline)</label><input type="text" name="tagline" value="<?= h($site['tagline']) ?>"></div>
    </div>
    <div class="a-field-row">
      <div><label>טלפון</label><input type="tel" name="phone" value="<?= h($site['phone']) ?>"></div>
      <div><label>וואטסאפ (למשל 972501234567)</label><input type="tel" name="whatsapp" value="<?= h($site['whatsapp']) ?>"></div>
      <div><label>אימייל להתראות</label><input type="email" name="email" value="<?= h($site['email']) ?>"></div>
    </div>
    <div class="a-field-row">
      <div><label>כתובת</label><input type="text" name="address" value="<?= h($site['address']) ?>"></div>
      <div><label>הנחיות הגעה</label><input type="text" name="location_text" value="<?= h($site['location_text']) ?>" placeholder="לדוגמה: כניסה מהחצר האחורית"></div>
    </div>
    <div class="a-field-row">
      <div><label>קו רוחב (Lat)</label><input type="text" name="location_lat" value="<?= h((string) $site['location_lat']) ?>"></div>
      <div><label>קו אורך (Lng)</label><input type="text" name="location_lng" value="<?= h((string) $site['location_lng']) ?>"></div>
      <div><label>שעות ביטול חינם מראש</label><input type="number" name="cancellation_hours" value="<?= (int) $site['cancellation_hours'] ?>"></div>
    </div>
    <div class="a-field-row">
      <div><label>אינסטגרם</label><input type="url" name="instagram_url" value="<?= h($site['instagram_url']) ?>"></div>
      <div><label>פייסבוק</label><input type="url" name="facebook_url" value="<?= h($site['facebook_url']) ?>"></div>
      <div><label>טיקטוק</label><input type="url" name="tiktok_url" value="<?= h($site['tiktok_url']) ?>"></div>
    </div>

    <h2 style="margin-top:22px;">רצועת אמון (בעמוד הבית)</h2>
    <?php for ($i = 1; $i <= 3; $i++): ?>
    <div class="a-field-row">
      <div><label>כותרת <?= $i ?></label><input type="text" name="trust<?= $i ?>_title" value="<?= h($site["trust{$i}_title"]) ?>"></div>
      <div style="flex:2;"><label>טקסט <?= $i ?></label><input type="text" name="trust<?= $i ?>_text" value="<?= h($site["trust{$i}_text"]) ?>"></div>
    </div>
    <?php endfor; ?>

    <button class="a-btn" type="submit" style="margin-top:6px;">שמירה</button>
  </form>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
