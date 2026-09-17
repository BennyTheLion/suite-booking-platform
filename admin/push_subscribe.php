<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push.php';

$slug = $_GET['site'] ?? '';
$site = require_admin($slug);

start_session();
$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $sentToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || empty($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

save_push_subscription((int) $site['id'], (int) $_SESSION['admin_id'], $data);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
