<?php
// Looks in the project root first (local dev), then one directory above it —
// e.g. Hostinger's public_html sibling — so config.php can be kept outside the web root in production.
$configCandidates = [__DIR__ . '/../config.php', __DIR__ . '/../../config.php'];
$configFound = false;
foreach ($configCandidates as $configPath) {
    if (is_file($configPath)) {
        require_once $configPath;
        $configFound = true;
        break;
    }
}
if (!$configFound) {
    throw new RuntimeException('config.php not found. Copy config.example.php to config.php, either in the project root or one directory above it, and fill in your values.');
}
unset($configCandidates, $configFound, $configPath);

function db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function db_one(string $sql, array $params = []): ?array {
    $stmt = db_prepare($sql, $params);
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function db_all(string $sql, array $params = []): array {
    $stmt = db_prepare($sql, $params);
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function db_run(string $sql, array $params = []): mysqli_stmt {
    return db_prepare($sql, $params);
}

function db_prepare(string $sql, array $params = []): mysqli_stmt {
    $stmt = db()->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('DB prepare failed: ' . db()->error);
    }
    if ($params) {
        $types = '';
        $vals = [];
        foreach ($params as $p) {
            if (is_int($p)) $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else $types .= 's';
            $vals[] = $p;
        }
        $stmt->bind_param($types, ...$vals);
    }
    $stmt->execute();
    return $stmt;
}

function db_insert_id(): int {
    return (int) db()->insert_id;
}
