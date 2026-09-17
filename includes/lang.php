<?php

function load_lang(string $default = 'he'): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $code = $_GET['lang'] ?? $_SESSION['lang'] ?? $default;
    if (!in_array($code, ['he', 'en'], true)) $code = 'he';
    $_SESSION['lang'] = $code;
    return require __DIR__ . "/../lang/{$code}.php";
}

function t(array $L, string $key): string {
    return $L[$key] ?? $key;
}
