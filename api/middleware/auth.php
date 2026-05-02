<?php
// middleware/auth.php — Lightweight JWT (no external library needed)

function requireAuth(): void {
    $token = getBearerToken();
    if (!$token || !verifyToken($token)) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized — invalid or missing token']));
    }
}

function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

// ── Minimal JWT (HS256) ──────────────────────────────────────
function createToken(array $payload): string {
    $header  = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + 86400 * 7; // 7 days
    $body    = base64url(json_encode($payload));
    $sig     = base64url(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function verifyToken(string $token): bool {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$header, $payload, $sig] = $parts;
    $expected = base64url(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return false;
    $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    if (!$data || $data['exp'] < time()) return false;
    return true;
}

function base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}