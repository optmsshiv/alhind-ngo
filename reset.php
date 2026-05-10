<?php
// reset_pass.php — upload to server, run once, then DELETE immediately
require_once __DIR__ . '/config/db.php';
$db   = getDB();
$hash = password_hash('alhind2024', PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE admin_users SET password_hash = ?, is_active = 1 WHERE role = 'superadmin' LIMIT 1");
$stmt->execute([$hash]);
echo "Done. Hash: " . $hash;