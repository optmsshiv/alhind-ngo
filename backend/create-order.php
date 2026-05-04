<?php
// ============================================================
//  create-order.php — DEBUG VERSION
//  Remove this debug block once working
// ============================================================

// Show ALL errors so we can see what's crashing
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$debug = [];

// ── STEP 1: Check db.php path ─────────────────────────────────
$dbPath = __DIR__ . '/../config/db.php';
$debug['db_path']        = $dbPath;
$debug['db_path_exists'] = file_exists($dbPath);

// ── STEP 2: Check vendor/autoload.php path ────────────────────
$vendorPath = __DIR__ . '/../vendor/autoload.php';
$debug['vendor_path']        = $vendorPath;
$debug['vendor_path_exists'] = file_exists($vendorPath);

// ── STEP 3: Try loading db.php ────────────────────────────────
if ($debug['db_path_exists']) {
    try {
        require_once $dbPath;
        $debug['db_loaded'] = true;
    } catch (Throwable $e) {
        $debug['db_loaded'] = false;
        $debug['db_error']  = $e->getMessage();
    }
} else {
    $debug['db_loaded'] = false;
    $debug['db_error']  = 'File not found at: ' . $dbPath;
}

// ── STEP 4: Try loading vendor/autoload.php ───────────────────
if ($debug['vendor_path_exists']) {
    try {
        require_once $vendorPath;
        $debug['vendor_loaded'] = true;
    } catch (Throwable $e) {
        $debug['vendor_loaded'] = false;
        $debug['vendor_error']  = $e->getMessage();
    }
} else {
    $debug['vendor_loaded'] = false;
    $debug['vendor_error']  = 'File not found at: ' . $vendorPath;
}

// ── STEP 5: Check Razorpay class exists ───────────────────────
$debug['razorpay_class_exists'] = class_exists('Razorpay\Api\Api');

// ── STEP 6: Try DB connection ─────────────────────────────────
if ($debug['db_loaded'] && function_exists('getDB')) {
    try {
        $pdo = getDB();
        $debug['db_connected'] = true;
    } catch (Throwable $e) {
        $debug['db_connected'] = false;
        $debug['db_conn_error'] = $e->getMessage();
    }
} else {
    $debug['db_connected']  = false;
    $debug['getDB_exists']  = function_exists('getDB');
}

// ── STEP 7: Try creating Razorpay order ───────────────────────
if ($debug['razorpay_class_exists']) {
    try {
        $api   = new \Razorpay\Api\Api('rzp_test_REPLACE_YOUR_KEY_ID', 'REPLACE_YOUR_KEY_SECRET');
        $order = $api->order->create([
            'amount'          => 100, // ₹1 test
            'currency'        => 'INR',
            'receipt'         => 'debug_' . time(),
            'payment_capture' => 1,
        ]);
        $debug['razorpay_order_created'] = true;
        $debug['order_id']               = $order['id'];
    } catch (Throwable $e) {
        $debug['razorpay_order_created'] = false;
        $debug['razorpay_error']         = $e->getMessage();
    }
}

// ── OUTPUT ────────────────────────────────────────────────────
echo json_encode([
    'status' => 'debug',
    'debug'  => $debug,
    '__dir__' => __DIR__,
], JSON_PRETTY_PRINT);