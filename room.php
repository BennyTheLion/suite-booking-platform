<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$slug = $_GET['site'] ?? '';
$site = $slug ? get_site_by_slug($slug) : null;
if (!$site) { http_response_code(404); echo 'האתר לא נמצא.'; exit; }

$L = load_lang($site['default_lang'] ?: 'he');

$roomSlug = $_GET['room'] ?? '';
$room = db_one('SELECT * FROM rooms WHERE site_id = ? AND slug = ? AND active = 1', [$site['id'], $roomSlug]);
if (!$room) { http_response_code(404); echo 'החדר לא נמצא.'; exit; }

$facilities = get_room_facilities($room['id']);
$media = get_room_media($room['id']);
if (!$media) {
    $media = [['type' => 'placeholder', 'path' => '', 'is_cover' => 1]];
}

// ---- date/time selection state, driven by query string, re-rendered server-side ----
$today = new DateTime('today');
$dates = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $today)->modify("+{$i} day");
    $dates[] = $d;
}
$selectedDate = $_GET['date'] ?? $today->format('Y-m-d');
$validDates = array_map(fn($d) => $d->format('Y-m-d'), $dates);
if (!in_array($selectedDate, $validDates, true)) $selectedDate = $today->format('Y-m-d');

$minHours = max(3, (int) $room['min_hours']);
$hours = max($minHours, (int) ($_GET['hours'] ?? $minHours));
$hours = min($hours, 8);

$slots = get_hourly_slots((int) $room['id'], $selectedDate, 60);
$firstFree = null;
foreach ($slots as $slot) { if ($slot['available']) { $firstFree = $slot['time']; break; } }
$startTime = $_GET['start'] ?? ($firstFree ? substr($firstFree, 0, 2) : '10');
$startTime = str_pad(preg_replace('/[^0-9]/', '', (string) $startTime), 2, '0', STR_PAD_LEFT);
$startHour = (int) $startTime;
$endHour = ($startHour + $hours) % 24;
$endLabel = sprintf('%02d:00', $endHour);
$startLabel = sprintf('%02d:00', $startHour);

$rangeFree = is_range_free((int) $room['id'], $selectedDate, $startLabel . ':00', $endLabel . ':00');
$extraHours = max(0, $hours - 3);
$total = (float) $room['price_3h'] + $extraHours * (float) $room['price_extra_hour'];

// ---- optional full-day booking mode ----
$offersDaily = (float) $room['price_per_day'] > 0;
$mode = ($offersDaily && ($_GET['mode'] ?? '') === 'daily') ? 'daily' : 'hourly';

$checkIn = $_GET['checkin'] ?? $today->format('Y-m-d');
if ((new DateTime($checkIn)) < $today) $checkIn = $today->format('Y-m-d');
$checkOut = $_GET['checkout'] ?? (new DateTime($checkIn))->modify('+1 day')->format('Y-m-d');
if ((new DateTime($checkOut)) <= (new DateTime($checkIn))) $checkOut = (new DateTime($checkIn))->modify('+1 day')->format('Y-m-d');
$nights = (new DateTime($checkIn))->diff(new DateTime($checkOut))->days;
$dailyTotal = $nights * (float) $room['price_per_day'];
$dailyFree = $offersDaily ? is_daily_range_free((int) $room['id'], $checkIn, $checkOut) : false;

$weekdayNames = [0=>'weekday_short_0',1=>'weekday_short_1',2=>'weekday_short_2',3=>'weekday_short_3',4=>'weekday_short_4',5=>'weekday_short_5',6=>'weekday_short_6'];

$pageTitle = $room['name'] . ' — ' . $site['name'];
$backLink = ['href' => APP_BASE_URL . '/' . $site['slug'] . '/', 'label' => t($L, 'our_rooms')];
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <button class="back-link" onclick="location.href='<?= h($backLink['href']) ?>'" type="button">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M9 5l7 7-7 7"/></svg>
    <?= h($backLink['label']) ?>
  </button>

  <div class="detail-layout">
    <div class="detail-main">
      <div class="gallery-main" id="galleryMain">
        <?php $cover = $media[0]; ?>
        <?php if ($cover['type'] === 'video'): ?>
          <video class="scene" src="<?= h(APP_BASE_URL . '/' . $cover['path']) ?>" autoplay muted loop playsinline></video>
        <?php elseif ($cover['type'] === 'image'): ?>
          <div class="scene" style="background-image:url('<?= h(APP_BASE_URL . '/' . $cover['path']) ?>')"></div>
        <?php else: ?>
          <div class="scene scene-1"></div>
        <?php endif; ?>
        <div class="tag"><?= h($room['name']) ?></div>
      </div>
      <?php if (count($media) > 1): ?>
      <div class="thumbs-row">
        <button type="button" class="thumbs-arrow thumbs-arrow-prev" id="thumbsPrev" aria-label="הקודם">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M15 5l-7 7 7 7"/></svg>
        </button>
        <div class="thumbs" id="thumbs">
          <?php foreach ($media as $i => $m):
            if ($m['type'] === 'video') {
              $html = '<video class="scene" src="' . h(APP_BASE_URL . '/' . $m['path']) . '" autoplay muted loop playsinline></video>';
              $style = '';
            } else {
              $html = '<div class="scene" style="background-image:url(\'' . h(APP_BASE_URL . '/' . $m['path']) . '\')"></div>';
              $style = " style=\"background-image:url('" . h(APP_BASE_URL . '/' . $m['path']) . "')\"";
            }
          ?>
          <div class="thumb<?= $i === 0 ? ' is-active' : '' ?>"<?= $style ?> data-scene-html="<?= h($html) ?>"></div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="thumbs-arrow thumbs-arrow-next" id="thumbsNext" aria-label="הבא">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>
      <?php endif; ?>

      <div class="room-head">
        <h1><?= h($room['name']) ?></h1>
        <div class="room-price">
          <?php if ($mode === 'daily'): ?>
            ₪<?= (int) $room['price_per_day'] ?>
            <small> / <?= h(t($L, 'per_day')) ?></small>
          <?php else: ?>
            ₪<?= (int) $room['price_3h'] ?>
            <small> / 3 <?= h(t($L, 'hour_plural')) ?></small>
          <?php endif; ?>
        </div>
        <?php if ($mode !== 'daily' && (float) $room['price_extra_hour'] > 0): ?>
          <div class="room-price-extra" style="font-size:12.5px;color:var(--ink-faint);">+₪<?= (int) $room['price_extra_hour'] ?> לכל שעה נוספת</div>
        <?php endif; ?>
        <?php if ($mode !== 'daily' && $room['show_price_per_hour'] && (float) $room['price_per_hour'] > 0): ?>
          <div class="room-price-hourly" style="font-size:12.5px;color:var(--ink-faint);">₪<?= (int) $room['price_per_hour'] ?> / <?= h(t($L, 'per_hour')) ?></div>
        <?php endif; ?>
      </div>
      <div class="spec-row">
        <?php if ($room['size_sqm']): ?><span class="stat"><strong><?= (int) $room['size_sqm'] ?></strong> מ״ר</span><?php endif; ?>
        <?php if ($room['bed_type']): ?><span class="stat"><?= h((string) $room['bed_type']) ?></span><?php endif; ?>
        <span class="stat"><?= h(t($L, 'capacity')) ?> <strong><?= (int) $room['capacity'] ?></strong></span>
        <span class="stat"><?= h(t($L, 'min_hours')) ?>: <strong><?= (int) $room['min_hours'] ?></strong></span>
      </div>
      <p class="room-desc"><?= nl2br(h((string) $room['description'])) ?></p>

      <?php if ($facilities): ?>
      <div class="facilities">
        <?php foreach ($facilities as $f): ?>
        <div class="facility"><?= facility_icon_svg($f['fkey']) ?><?= h($L['lang_code'] === 'he' ? $f['label_he'] : $f['label_en']) ?></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="detail-side">
      <?php if (isset($_GET['unavailable'])): ?>
        <div class="field-error">התאריך והשעה שנבחרו כבר לא פנויים — נא לבחור שעה אחרת.</div>
      <?php endif; ?>

      <?php if ($offersDaily): ?>
      <div class="mode-tabs" role="tablist">
        <a role="tab" class="mode-tab<?= $mode === 'hourly' ? ' is-active' : '' ?>" href="?site=<?= h($slug) ?>&room=<?= h($roomSlug) ?>&mode=hourly"><?= h(t($L, 'hourly')) ?></a>
        <a role="tab" class="mode-tab<?= $mode === 'daily' ? ' is-active' : '' ?>" href="?site=<?= h($slug) ?>&room=<?= h($roomSlug) ?>&mode=daily"><?= h(t($L, 'daily')) ?></a>
      </div>
      <?php endif; ?>

      <?php if ($mode === 'hourly'): ?>
      <div class="panel">
        <div class="field-label"><?= h(t($L, 'select_date')) ?></div>
        <div class="days" id="days">
          <?php foreach ($dates as $d):
            $dStr = $d->format('Y-m-d');
            $isSel = $dStr === $selectedDate;
          ?>
          <div class="day<?= $isSel ? ' is-selected' : '' ?>" data-date="<?= $dStr ?>">
            <span><?= h(t($L, $weekdayNames[(int) $d->format('w')])) ?></span>
            <strong><?= $d->format('j') ?></strong>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="field-label"><?= h(t($L, 'start_time')) ?></div>
        <div class="hours" id="hours">
          <?php foreach ($slots as $slot):
            $slotHour = substr($slot['time'], 0, 2);
            $isSel = $slotHour === $startTime;
            $cls = !$slot['available'] ? ' is-taken' : ($isSel ? ' is-selected' : '');
          ?>
          <div class="hour<?= $cls ?>" data-h="<?= $slotHour ?>"><?= h($slot['time']) ?></div>
          <?php endforeach; ?>
          <?php if (!$slots): ?><p style="font-size:12.5px;color:var(--ink-faint);"><?= h(t($L, 'closed_label')) ?></p><?php endif; ?>
        </div>

        <?php if ($slots): ?>
        <div class="time-readout">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
          <span><?= h($startLabel) ?> – <?= h($endLabel) ?></span>
        </div>

        <div class="field-label"><?= h(t($L, 'duration_hours')) ?></div>
        <div class="duration-row">
          <div class="stepper">
            <button type="button" id="minus" data-min="<?= $minHours ?>" data-max="8" data-current="<?= $hours ?>">−</button>
            <strong><?= $hours ?> <?= h($hours === 1 ? t($L, 'hour_singular') : t($L, 'hour_plural')) ?></strong>
            <button type="button" id="plus" data-min="<?= $minHours ?>" data-max="8" data-current="<?= $hours ?>">+</button>
          </div>
          <div style="font-family:var(--font-display);color:var(--accent);font-size:15px;"><?= h(t($L, 'total')) ?> ₪<?= (int) $total ?></div>
        </div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="panel">
        <div class="a-field-row" style="display:flex;gap:10px;">
          <div style="flex:1;">
            <div class="field-label"><?= h(t($L, 'check_in')) ?></div>
            <input type="date" id="checkinInput" value="<?= h($checkIn) ?>" min="<?= $today->format('Y-m-d') ?>" style="width:100%;padding:9px 10px;border-radius:10px;border:1px solid var(--line-strong);background:var(--ground);color:var(--ink);">
          </div>
          <div style="flex:1;">
            <div class="field-label"><?= h(t($L, 'check_out')) ?></div>
            <input type="date" id="checkoutInput" value="<?= h($checkOut) ?>" min="<?= (new DateTime($checkIn))->modify('+1 day')->format('Y-m-d') ?>" style="width:100%;padding:9px 10px;border-radius:10px;border:1px solid var(--line-strong);background:var(--ground);color:var(--ink);">
          </div>
        </div>
        <div class="time-readout" style="margin-top:14px;">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
          <span><?= $nights ?> <?= h($nights === 1 ? t($L, 'night_singular') : t($L, 'night_plural')) ?></span>
        </div>
        <div style="font-family:var(--font-display);color:var(--accent);font-size:15px;margin-top:10px;"><?= h(t($L, 'total')) ?> ₪<?= (int) $dailyTotal ?></div>
      </div>
      <?php endif; ?>

      <?php if ($room['house_rules_text']): ?>
      <div class="house-note">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M12 9v4M12 16.5h.01"/><path d="M10.3 3.9 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <span><?= h((string) $room['house_rules_text']) ?></span>
      </div>
      <?php endif; ?>

      <?php $canBook = $mode === 'hourly' ? ($slots && $rangeFree) : $dailyFree; ?>
      <?php if ($canBook): ?>
      <form method="post" action="<?= APP_BASE_URL ?>/book.php" id="bookingForm">
        <input type="hidden" name="site" value="<?= h($site['slug']) ?>">
        <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>">
        <input type="hidden" name="booking_type" value="<?= $mode ?>">
        <?php if ($mode === 'hourly'): ?>
          <input type="hidden" name="date" value="<?= h($selectedDate) ?>">
          <input type="hidden" name="start" value="<?= h($startLabel) ?>">
          <input type="hidden" name="end" value="<?= h($endLabel) ?>">
        <?php else: ?>
          <input type="hidden" name="checkin" value="<?= h($checkIn) ?>">
          <input type="hidden" name="checkout" value="<?= h($checkOut) ?>">
        <?php endif; ?>
        <div class="field-label" style="margin-top:16px;"><?= h(t($L, 'full_name')) ?> / <?= h(t($L, 'phone')) ?></div>
        <input required name="guest_name" placeholder="<?= h(t($L, 'full_name')) ?>" style="width:100%;padding:11px 14px;border-radius:10px;border:1px solid var(--line-strong);margin-bottom:8px;font-size:14px;background:var(--ground);color:var(--ink);">
        <input required name="guest_phone" type="tel" placeholder="<?= h(t($L, 'phone')) ?>" style="width:100%;padding:11px 14px;border-radius:10px;border:1px solid var(--line-strong);margin-bottom:8px;font-size:14px;background:var(--ground);color:var(--ink);">
        <div class="cta-row">
          <button class="btn btn-primary" type="submit" id="submitBtn" data-loading-text="<?= h(t($L, 'sending')) ?>"><?= h(t($L, 'submit_request')) ?></button>
          <?php if ($site['whatsapp']):
            $waMsg = $mode === 'hourly'
              ? "שלום, מעוניין/ת להזמין את {$room['name']} בתאריך {$selectedDate} בין {$startLabel} ל-{$endLabel}."
              : "שלום, מעוניין/ת להזמין את {$room['name']} מתאריך {$checkIn} עד {$checkOut}.";
          ?>
          <a class="btn btn-outline" target="_blank" rel="noopener" href="<?= h(whatsapp_link($site['whatsapp'], $waMsg)) ?>">
            <svg viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-3.2-6.4M20 12l-1-4-4 1"/></svg>
            <span><?= h(t($L, 'book_via_whatsapp')) ?></span>
          </a>
          <?php endif; ?>
        </div>
      </form>
      <?php else: ?>
        <p style="font-size:12.5px;color:var(--ink-faint);margin-top:14px;"><?= h(t($L, 'unavailable')) ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
