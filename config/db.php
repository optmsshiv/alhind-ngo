<?php
// ============================================================
//  config/db.php — Database connection
//  AL Hind Educational and Charitable Trust
// ============================================================

define('DB_HOST', '127.0.0.1');        // Usually 'localhost' on shared hosting
define('DB_NAME', 'u699609112_alhind');        // Your cPanel database name (e.g. user_alhind_db)
define('DB_USER', 'u699609112_alhind');      // Your cPanel database username
define('DB_PASS', '123@Alhindtrust'); // ← Change this

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'alhind2024'); // ← Change this (plain text compared to hashed)
define('JWT_SECRET',     'alhind_jwt_secret_change_this_32chars'); // ← Change this
 
// ── CORS Origins ────────────────────────────────────────────
// Add your domains here
define('ALLOWED_ORIGINS', [
    'https://alhindtrust.com',
    'https://www.alhindtrust.com',
    'https://admin.alhindtrust.com',
    'https://api.alhindtrust.com',
    'http://localhost',
]);
 
// ── Create PDO connection ────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}