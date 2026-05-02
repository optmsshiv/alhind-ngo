<?php
// middleware/response.php

function ok(mixed $data = null, string $message = 'OK', int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ]);
    exit;
}

function error(string $message, int $code = 400, mixed $data = null): void {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error'   => $message,
        'data'    => $data,
    ]);
    exit;
}

function notFound(): void {
    error('Endpoint not found', 404);
}

function body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}