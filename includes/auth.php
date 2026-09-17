<?php
require_once __DIR__ . '/db.php';

function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

// ---------- CSRF ----------
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): void {
    start_session();
    $sent = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(403);
        echo 'הבקשה פגה תוקף — נא לרענן את הדף ולנסות שוב.';
        exit;
    }
}

// ---------- Login rate limiting ----------
function login_identifier(string $username): string {
    return ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . strtolower($username);
}

function is_login_rate_limited(string $scope, string $identifier): bool {
    $row = db_one(
        "SELECT COUNT(*) AS c FROM login_attempts WHERE scope = ? AND identifier = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE",
        [$scope, $identifier]
    );
    return (int) ($row['c'] ?? 0) >= 5;
}

function record_login_attempt(string $scope, string $identifier): void {
    db_run('INSERT INTO login_attempts (scope, identifier) VALUES (?, ?)', [$scope, $identifier]);
}

function clear_login_attempts(string $scope, string $identifier): void {
    db_run('DELETE FROM login_attempts WHERE scope = ? AND identifier = ?', [$scope, $identifier]);
}

// ---------- Super admin ----------
function super_admin_login(string $username, string $password): bool {
    $row = db_one('SELECT * FROM super_admins WHERE username = ?', [$username]);
    if ($row && password_verify($password, $row['password_hash'])) {
        start_session();
        session_regenerate_id(true);
        $_SESSION['super_admin_id'] = $row['id'];
        return true;
    }
    return false;
}

function require_super_admin(): void {
    start_session();
    if (empty($_SESSION['super_admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function super_admin_logout(): void {
    start_session();
    unset($_SESSION['super_admin_id']);
}

// ---------- Site admin ----------
function admin_login(int $site_id, string $username, string $password): bool {
    $row = db_one('SELECT * FROM admins WHERE site_id = ? AND username = ?', [$site_id, $username]);
    if ($row && password_verify($password, $row['password_hash'])) {
        start_session();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_site_id'] = $row['site_id'];
        return true;
    }
    return false;
}

// Verifies the logged-in admin belongs to the site named in the URL (?site=slug).
// Returns the site row so callers don't need a second lookup.
function require_admin(string $slug): array {
    require_once __DIR__ . '/functions.php';
    start_session();
    $site = get_site_by_slug($slug);
    if (!$site) { http_response_code(404); echo 'האתר לא נמצא.'; exit; }
    if (empty($_SESSION['admin_id']) || (int) ($_SESSION['admin_site_id'] ?? 0) !== (int) $site['id']) {
        header('Location: login.php?site=' . urlencode($slug));
        exit;
    }
    return $site;
}

function admin_logout(): void {
    start_session();
    unset($_SESSION['admin_id'], $_SESSION['admin_site_id']);
}
