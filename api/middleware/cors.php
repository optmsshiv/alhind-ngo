<?php
function applyCors(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    $allowed = [
        'https://alhindtrust.com',
        'https://www.alhindtrust.com',
        'https://admin.alhindtrust.com',
        'https://api.alhindtrust.com',
        'http://localhost',
        'http://127.0.0.1',
    ];

    if (in_array($origin, $allowed, true)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: *");
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json; charset=UTF-8');
}