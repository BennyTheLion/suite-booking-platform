<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$slug = $_GET['site'] ?? '';
$site = $slug ? get_site_by_slug($slug) : null;
if (!$site) { http_response_code(404); echo 'האתר לא נמצא.'; exit; }

$L = load_lang($site['default_lang'] ?: 'he');

$bookingId = (int) ($_GET['id'] ?? 0);
$booking = db_one('SELECT b.*, r.name AS room_name FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ? AND b.site_id = ?', [$bookingId, $site['id']]);
if (!$booking) { http_response_code(404); echo 'ההזמנה לא נמצאה.'; exit; }

$statusLabel = t($L, $booking['status']);
$statusClass = $booking['status'] === 'approved' ? ' is-approved' : '';

$dateFmt = (new DateTime($booking['date_start']))->format('d.m.Y');
if ($booking['booking_type'] === 'daily') {
    $checkOutFmt = (new DateTime($booking['date_end']))->modify('+1 day')->format('d.m.Y');
    $dateFmt = $dateFmt . ' – ' . $checkOutFmt;
    $whatsappMsg = "שלום, בעניין ההזמנה שלי ל{$booking['room_name']} מתאריך {$dateFmt}.";
} else {
    $whatsappMsg = "שלום, בעניין ההזמנה שלי ל{$booking['room_name']} בתאריך {$dateFmt} בין " . substr($booking['time_start'], 0, 5) . ' ל-' . substr($booking['time_end'], 0, 5) . '.';
}

$pageTitle = t($L, 'request_sent_title');
require __DIR__ . '/includes/header.php';
?>

<div class="confirm-wrap">
  <div class="confirm-card">
    <div class="confirm-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
    </div>
    <h1><?= h(t($L, 'request_sent_title')) ?></h1>
    <p class="lead"><?= h(t($L, 'request_sent_body')) ?></p>

    <div class="status-pill<?= $statusClass ?>"><span class="dot"></span><?= h($statusLabel) ?></div>

    <div class="confirm-summary">
      <div class="confirm-row"><span><?= h(t($L, 'suite')) ?></span><strong><?= h($booking['room_name']) ?></strong></div>
      <div class="confirm-row"><span><?= h(t($L, 'date')) ?></span><strong><?= h($dateFmt) ?></strong></div>
      <?php if ($booking['time_start']): ?>
      <div class="confirm-row"><span><?= h(t($L, 'hours_label')) ?></span><strong><?= h(substr($booking['time_start'], 0, 5)) ?> – <?= h(substr($booking['time_end'], 0, 5)) ?></strong></div>
      <?php endif; ?>
    </div>

    <div class="confirm-actions">
      <?php if ($site['whatsapp']): ?>
      <a class="btn btn-primary" style="border-radius:12px;display:block;text-align:center;" target="_blank" rel="noopener" href="<?= h(whatsapp_link($site['whatsapp'], $whatsappMsg)) ?>"><?= h(t($L, 'send_via_whatsapp')) ?></a>
      <?php endif; ?>
      <button class="btn-secondary" type="button" onclick="location.href='<?= APP_BASE_URL . '/' . h($site['slug']) . '/' ?>'"><?= h(t($L, 'home')) ?></button>
    </div>
    <p class="confirm-note"><?= h(t($L, 'request_number')) ?>: #<?= (int) $booking['id'] ?></p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
