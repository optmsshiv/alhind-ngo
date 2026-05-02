<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'u699609112_alhind');
define('DB_USER', 'u699609112_alhind');
define('DB_PASS', '123@Alhindtrust'); // ← put your real password here

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS
    );
    echo json_encode([
        "status"  => "DB connected ✅",
        "db"      => DB_NAME,
        "tables"  => $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "DB FAILED ❌",
        "error"  => $e->getMessage()
    ]);
}