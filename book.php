<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/push.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$slug = $_POST['site'] ?? '';
$site = $slug ? get_site_by_slug($slug) : null;
if (!$site) { http_response_code(404); echo 'האתר לא נמצא.'; exit; }

$roomId = (int) ($_POST['room_id'] ?? 0);
$room = get_room($roomId, (int) $site['id']);
if (!$room) { http_response_code(404); echo 'החדר לא נמצא.'; exit; }

$bookingType = ($_POST['booking_type'] ?? 'hourly') === 'daily' ? 'daily' : 'hourly';
$guestName = trim((string) ($_POST['guest_name'] ?? ''));
$guestPhone = trim((string) ($_POST['guest_phone'] ?? ''));

if ($guestName === '' || $guestPhone === '') {
    header('Location: ' . APP_BASE_URL . '/' . $site['slug'] . '/room/' . $room['slug'] . '?error=missing');
    exit;
}

if ($bookingType === 'hourly') {
    $date = $_POST['date'] ?? '';
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';

    if (!$date || !$start || !$end) {
        header('Location: ' . APP_BASE_URL . '/' . $site['slug'] . '/room/' . $room['slug'] . '?error=missing');
        exit;
    }
    if (!is_range_free($roomId, $date, $start . ':00', $end . ':00')) {
        header('Location: ' . APP_BASE_URL . '/' . $site['slug'] . '/room/' . $room['slug'] . '?date=' . urlencode($date) . '&unavailable=1');
        exit;
    }

    db_run(
        'INSERT INTO bookings (site_id, room_id, guest_name, guest_phone, booking_type, date_start, date_end, time_start, time_end, status)
         VALUES (?, ?, ?, ?, "hourly", ?, ?, ?, ?, "pending")',
        [(int) $site['id'], $roomId, $guestName, $guestPhone, $date, $date, $start . ':00', $end . ':00']
    );
    $bookingId = db_insert_id();

    push_admin_notification((int) $site['id'], $bookingId, "בקשת הזמנה חדשה: {$room['name']}, {$date} {$start}-{$end}");
    notify_admin_email(
        $site,
        'בקשת הזמנה חדשה — ' . $site['name'],
        "התקבלה בקשת הזמנה חדשה.\n\nחדר: {$room['name']}\nתאריך: {$date}\nשעות: {$start} - {$end}\nשם: {$guestName}\nטלפון: {$guestPhone}\n\nלאישור/דחייה, היכנס ללוח הניהול."
    );
    send_push_notifications(
        (int) $site['id'],
        'בקשת הזמנה חדשה',
        "{$room['name']} · {$date} {$start}-{$end}",
        APP_BASE_URL . '/admin/index.php?site=' . urlencode($site['slug'])
    );
} else {
    $checkIn = $_POST['checkin'] ?? '';
    $checkOut = $_POST['checkout'] ?? '';

    if (!$checkIn || !$checkOut || (float) $room['price_per_day'] <= 0) {
        header('Location: ' . APP_BASE_URL . '/' . $site['slug'] . '/room/' . $room['slug'] . '?error=missing');
        exit;
    }
    if (!is_daily_range_free($roomId, $checkIn, $checkOut)) {
        header('Location: ' . APP_BASE_URL . '/' . $site['slug'] . '/room/' . $room['slug'] . '?mode=daily&checkin=' . urlencode($checkIn) . '&unavailable=1');
        exit;
    }

    // date_end is stored inclusive of the last occupied night for display/reporting consistency with hourly rows.
    $lastNight = (new DateTime($checkOut))->modify('-1 day')->format('Y-m-d');
    db_run(
        'INSERT INTO bookings (site_id, room_id, guest_name, guest_phone, booking_type, date_start, date_end, time_start, time_end, status)
         VALUES (?, ?, ?, ?, "daily", ?, ?, NULL, NULL, "pending")',
        [(int) $site['id'], $roomId, $guestName, $guestPhone, $checkIn, $lastNight]
    );
    $bookingId = db_insert_id();

    push_admin_notification((int) $site['id'], $bookingId, "בקשת הזמנה חדשה: {$room['name']}, {$checkIn} עד {$checkOut}");
    notify_admin_email(
        $site,
        'בקשת הזמנה חדשה — ' . $site['name'],
        "התקבלה בקשת הזמנה חדשה (הזמנה ליום מלא).\n\nחדר: {$room['name']}\nמתאריך: {$checkIn}\nעד תאריך: {$checkOut}\nשם: {$guestName}\nטלפון: {$guestPhone}\n\nלאישור/דחייה, היכנס ללוח הניהול."
    );
    send_push_notifications(
        (int) $site['id'],
        'בקשת הזמנה חדשה',
        "{$room['name']} · {$checkIn} עד {$checkOut}",
        APP_BASE_URL . '/admin/index.php?site=' . urlencode($site['slug'])
    );
}

header('Location: ' . APP_BASE_URL . '/confirm.php?site=' . urlencode($site['slug']) . '&id=' . $bookingId);
exit;
