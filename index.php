<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$slug = $_GET['site'] ?? '';
$site = $slug ? get_site_by_slug($slug) : null;

if (!$site) {
    http_response_code(404);
    echo 'האתר לא נמצא.';
    exit;
}

$L = load_lang($site['default_lang'] ?: 'he');

$rooms = db_all('SELECT * FROM rooms WHERE site_id = ? AND active = 1 ORDER BY sort_order, id', [$site['id']]);
$roomMedia = [];
foreach ($rooms as $room) {
    $cover = db_one('SELECT * FROM room_media WHERE room_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC LIMIT 1', [$room['id']]);
    $roomMedia[$room['id']] = $cover;
}

$trustItems = get_site_trust_items($site);

$pageTitle = $site['name'];
$headerOverlay = true;
require __DIR__ . '/includes/header.php';
?>

<section id="screen-home">
  <div class="hero-full">
    <div class="hero-media<?= $site['hero_image'] ? ' has-image' : '' ?>"<?php if ($site['hero_image']): ?> style="--hero-img:url('<?= APP_BASE_URL . '/' . h($site['hero_image']) ?>');--hero-pos:<?= h($site['hero_position'] ?: 'center') ?>"<?php endif; ?>></div>
    <div class="hero-scrim"></div>
    <div class="hero-copy container">
      <p class="kicker"><?= h($site['tagline']) ?></p>
      <h1><?= h($site['name']) ?></h1>
      <?php if (!empty($site['address'])): ?>
        <p class="sub"><?= h($site['address']) ?></p>
      <?php endif; ?>

      <div class="hero-cta-row">
        <a href="#rooms" class="pill-btn pill-btn-primary"><?= h(t($L, 'hero_cta_primary')) ?></a>
        <?php if ($site['phone']): ?>
        <a href="tel:<?= h(preg_replace('/\s+/', '', $site['phone'])) ?>" class="pill-btn pill-btn-ghost">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <?= h($site['phone']) ?>
        </a>
        <?php endif; ?>
        <?php if ($site['whatsapp']): ?>
        <a href="<?= h(whatsapp_link($site['whatsapp'], 'שלום, אשמח לפרטים נוספים על ' . $site['name'] . '.')) ?>" target="_blank" rel="noopener" class="pill-btn pill-btn-ghost">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 12a8 8 0 1 1-3.2-6.4M20 12l-1-4-4 1"/></svg>
          וואטסאפ
        </a>
        <?php endif; ?>
      </div>

      <?php if ($trustItems): ?>
      <div class="hero-pills">
        <?php foreach ($trustItems as $item): ?>
        <span class="hero-pill"><span class="check">✓</span> <?= h($item['title']) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <a href="#rooms" class="scroll-cue"><?= h(t($L, 'scroll_down')) ?></a>
  </div>

  <div class="container" id="rooms">
    <div class="suites-heading">
      <h2><?= h(t($L, 'our_rooms')) ?></h2>
      <p><?= h(t($L, 'rooms_subtitle')) ?></p>
    </div>

    <?php if (!$rooms): ?>
      <p><?= h(t($L, 'no_rooms')) ?></p>
    <?php else: ?>
      <div class="rooms-grid">
        <?php foreach ($rooms as $i => $room):
          $media = $roomMedia[$room['id']];
          $sceneClass = 'scene-' . (($i % 4) + 1);
          $thumbStyle = $media ? " style=\"background-image:url('" . h(APP_BASE_URL . '/' . $media['path']) . "')\"" : '';
        ?>
        <a class="room-card" href="<?= APP_BASE_URL . '/' . h($site['slug']) . '/room/' . h($room['slug']) ?>">
          <div class="card-thumb <?= $media ? '' : $sceneClass ?>"<?= $thumbStyle ?>>
            <?php if ($room['size_sqm']): ?><span class="badge"><?= (int) $room['size_sqm'] ?> מ״ר</span><?php endif; ?>
          </div>
          <div class="body">
            <h3><?= h($room['name']) ?></h3>
            <div class="specs">
              <?php if ($room['bed_type']): ?><span><?= h((string) $room['bed_type']) ?></span><?php endif; ?>
              <span><?= (int) $room['capacity'] ?> <?= h(t($L, 'guests')) ?></span>
            </div>
            <div class="foot">
              <div class="price">
                <?php if ($room['show_price_per_hour'] && (float) $room['price_per_hour'] > 0): ?>
                  ₪<?= (int) $room['price_per_hour'] ?><small> / <?= h(t($L, 'per_hour')) ?></small>
                <?php else: ?>
                  ₪<?= (int) $room['price_3h'] ?><small> / 3 <?= h(t($L, 'hour_plural')) ?></small>
                <?php endif; ?>
              </div>
              <div class="cta"><?= h(t($L, 'view_room')) ?></div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($trustItems): ?>
  <div class="home-band home-band--alt" id="features">
    <div class="container">
      <div class="band-header">
        <h2><?= h(t($L, 'features_title')) ?></h2>
        <p><?= h(t($L, 'features_subtitle')) ?></p>
      </div>
      <div class="features-grid">
        <?php foreach ($trustItems as $item): ?>
        <div class="feature-card">
          <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div>
          <h3><?= h($item['title']) ?></h3>
          <p><?= h($item['text']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="home-band" id="how">
    <div class="container">
      <div class="band-header">
        <h2><?= h(t($L, 'how_title')) ?></h2>
        <p><?= h(t($L, 'how_subtitle')) ?></p>
      </div>
      <div class="steps-grid">
        <div class="step-card"><span class="step-num">1</span><h3><?= h(t($L, 'how_step1_title')) ?></h3><p><?= h(t($L, 'how_step1_text')) ?></p></div>
        <div class="step-card"><span class="step-num">2</span><h3><?= h(t($L, 'how_step2_title')) ?></h3><p><?= h(t($L, 'how_step2_text')) ?></p></div>
        <div class="step-card"><span class="step-num">3</span><h3><?= h(t($L, 'how_step3_title')) ?></h3><p><?= h(t($L, 'how_step3_text')) ?></p></div>
      </div>
    </div>
  </div>

  <div class="home-band home-band--alt">
    <div class="container cta-band">
      <h2><?= h(t($L, 'cta_title')) ?></h2>
      <p><?= h(t($L, 'cta_subtitle')) ?></p>
      <div class="cta-band-actions">
        <a href="#rooms" class="pill-btn pill-btn-primary"><?= h(t($L, 'hero_cta_primary')) ?></a>
        <?php if ($site['phone']): ?>
        <a href="tel:<?= h(preg_replace('/\s+/', '', $site['phone'])) ?>" class="pill-btn pill-btn-outline">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <?= h(t($L, 'call_now')) ?>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="container">
    <?php if (!empty($site['address']) || !empty($site['location_lat'])): ?>
    <div class="section-label"><?= h(t($L, 'address')) ?></div>
    <div class="location">
      <div class="map-box">
        <?php if ($site['location_lat'] && $site['location_lng']): ?>
          <iframe loading="lazy" src="https://maps.google.com/maps?q=<?= $site['location_lat'] ?>,<?= $site['location_lng'] ?>&z=15&output=embed"></iframe>
        <?php else: ?>
          <div class="pin"></div>
        <?php endif; ?>
      </div>
      <p class="location-text"><strong><?= h($site['address']) ?></strong> <?= $site['location_text'] ? '— ' . h($site['location_text']) : '' ?></p>
      <?php
        $hasLatLng = $site['location_lat'] && $site['location_lng'];
        $dest = $hasLatLng ? $site['location_lat'] . ',' . $site['location_lng'] : (string) $site['address'];
        $googleUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($dest);
        $wazeUrl = $hasLatLng
          ? 'https://waze.com/ul?ll=' . rawurlencode($dest) . '&navigate=yes'
          : 'https://waze.com/ul?q=' . rawurlencode($dest) . '&navigate=yes';
      ?>
      <?php if ($dest !== ''): ?>
      <div class="nav-links">
        <a href="<?= h($googleUrl) ?>" target="_blank" rel="noopener" class="pill-btn pill-btn-outline nav-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-7.2 7-12a7 7 0 1 0-14 0c0 4.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
          <?= h(t($L, 'nav_google_maps')) ?>
        </a>
        <a href="<?= h($wazeUrl) ?>" target="_blank" rel="noopener" class="pill-btn pill-btn-outline nav-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/><path d="M8 15c1 1 2.5 1.5 4 1.5s3-.5 4-1.5"/></svg>
          <?= h(t($L, 'nav_waze')) ?>
        </a>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php $hasSocial = $site['instagram_url'] || $site['facebook_url'] || $site['tiktok_url']; ?>
  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-col footer-brand">
          <div class="footer-logo">
            <div class="footer-logo-mark">
              <?php if (!empty($site['logo_path'])): ?>
                <img src="<?= APP_BASE_URL . '/' . h($site['logo_path']) ?>" alt="">
              <?php else: ?>
                <?= h(mb_substr($site['name'], 0, 1)) ?>
              <?php endif; ?>
            </div>
            <span><?= h($site['name']) ?></span>
          </div>
          <?php if ($site['tagline']): ?><p class="footer-tagline"><?= h($site['tagline']) ?></p><?php endif; ?>
        </div>

        <div class="footer-col">
          <h4><?= h(t($L, 'footer_nav_title')) ?></h4>
          <ul>
            <li><a href="#rooms"><?= h(t($L, 'our_rooms')) ?></a></li>
            <li><a href="#features"><?= h(t($L, 'features_title')) ?></a></li>
            <li><a href="#how"><?= h(t($L, 'how_title')) ?></a></li>
          </ul>
        </div>

        <?php if ($site['phone'] || $site['whatsapp'] || $site['email'] || $site['address']): ?>
        <div class="footer-col">
          <h4><?= h(t($L, 'footer_contact_title')) ?></h4>
          <ul>
            <?php if ($site['phone']): ?><li><a href="tel:<?= h(preg_replace('/\s+/', '', $site['phone'])) ?>">📞 <?= h($site['phone']) ?></a></li><?php endif; ?>
            <?php if ($site['whatsapp']): ?><li><a href="<?= h(whatsapp_link($site['whatsapp'], 'שלום, אשמח לפרטים נוספים על ' . $site['name'] . '.')) ?>" target="_blank" rel="noopener">💬 <?= h(t($L, 'send_via_whatsapp')) ?></a></li><?php endif; ?>
            <?php if ($site['email']): ?><li><a href="mailto:<?= h($site['email']) ?>">✉️ <?= h($site['email']) ?></a></li><?php endif; ?>
            <?php if ($site['address']): ?><li><?= h($site['address']) ?></li><?php endif; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if ($hasSocial): ?>
        <div class="footer-col">
          <h4><?= h(t($L, 'footer_follow_title')) ?></h4>
          <div class="foot-social">
            <?php if ($site['instagram_url']): ?><a href="<?= h($site['instagram_url']) ?>" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="3.6"/><circle cx="17.2" cy="6.8" r="0.6" fill="currentColor"/></svg></a><?php endif; ?>
            <?php if ($site['facebook_url']): ?><a href="<?= h($site['facebook_url']) ?>" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M14 21v-7h2.5l.5-3H14V9c0-.9.3-1.5 1.7-1.5H17V4.8c-.3 0-1.3-.1-2.4-.1-2.4 0-4.1 1.5-4.1 4.2V11H8v3h2.5v7"/></svg></a><?php endif; ?>
            <?php if ($site['tiktok_url']): ?><a href="<?= h($site['tiktok_url']) ?>" target="_blank" rel="noopener" aria-label="TikTok"><svg viewBox="0 0 24 24"><path d="M15 3v10.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M15 3c0 2.5 2 4.5 4.5 4.5"/></svg></a><?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="footer-bottom">
        <p>
          © <?= date('Y') ?> <?= h($site['name']) ?>. <?= h(t($L, 'footer_rights')) ?>
          <a href="<?= APP_BASE_URL . '/privacy.php?site=' . h($site['slug']) ?>"><?= h(t($L, 'privacy_policy')) ?></a>
          · <a href="<?= APP_BASE_URL . '/terms.php?site=' . h($site['slug']) ?>"><?= h(t($L, 'terms_of_use')) ?></a>
          · <a href="<?= APP_BASE_URL . '/accessibility.php?site=' . h($site['slug']) ?>"><?= h(t($L, 'accessibility_statement')) ?></a>
        </p>
      </div>
    </div>
  </footer>
</section>

<script>
(function () {
  var header = document.getElementById('siteHeader');
  if (!header) return;
  var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 40); };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
