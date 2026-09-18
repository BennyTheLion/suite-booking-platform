<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$slug = $_GET['site'] ?? '';
$site = $slug ? get_site_by_slug($slug) : null;
if (!$site) { http_response_code(404); echo json_encode(['error' => 'site_not_found']); exit; }

$bookingId = (int) ($_GET['id'] ?? 0);
$booking = db_one('SELECT status FROM bookings WHERE id = ? AND site_id = ?', [$bookingId, $site['id']]);
if (!$booking) { http_response_code(404); echo json_encode(['error' => 'booking_not_found']); exit; }

echo json_encode(['status' => $booking['status']]);
