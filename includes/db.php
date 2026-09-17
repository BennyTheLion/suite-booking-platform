<?php
require_once __DIR__ . '/../config.php';

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
