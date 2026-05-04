<?php
// ============================================================
//  create-order.php — Razorpay Order Creation
//  AL Hind Educational and Charitable Trust
//  Called by donate.js before opening Razorpay checkout
// ============================================================

header('Content-Type: application/json');

// ── CORS ─────────────────────────────────────────────────────
$allowed = ['https://alhindtrust.com', 'https://www.alhindtrust.com'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Only allow POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// ── Dependencies ──────────────────────────────────────────────
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Razorpay\Api\Api;

// ── Read & validate input ─────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$name   = trim($data['name']   ?? '');
$email  = trim($data['email']  ?? '');
$amount = (float)($data['amount'] ?? 0);

if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid donor details']);
    exit;
}

if ($amount < 10 || $amount > 500000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Amount must be between ₹10 and ₹5,00,000']);
    exit;
}

$amountPaise = (int)round($amount * 100); // Razorpay needs paise (integer)

// ── Razorpay credentials ──────────────────────────────────────
// Replace with your live keys when going live:
//   Test  → rzp_test_XXXXXXXXXXXX
//   Live  → rzp_live_XXXXXXXXXXXX
define('RZP_KEY_ID',     'rzp_test_Sl9Q7wSNM1qyuf');
define('RZP_KEY_SECRET', 'zQkpzkYxlWfYDyesrj2IMB6g');

// ── Create Razorpay order ─────────────────────────────────────
try {
    $api = new Api(RZP_KEY_ID, RZP_KEY_SECRET);

    $order = $api->order->create([
        'amount'          => $amountPaise,
        'currency'        => 'INR',
        'receipt'         => 'don_' . time() . '_' . rand(100, 999),
        'payment_capture' => 1,
        'notes'           => [
            'donor_name'  => $name,
            'donor_email' => $email,
        ],
    ]);

    $orderId = $order['id'];

} catch (Exception $e) {
    error_log('[AL Hind] Razorpay order creation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Order creation failed. Please try again.']);
    exit;
}

// ── Save pending donation to DB ───────────────────────────────
try {
    $pdo = getDB();

    // Create the donations table if it doesn't exist yet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS donations (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            donor_name          VARCHAR(200)   NOT NULL,
            donor_email         VARCHAR(200)   NOT NULL,
            amount              DECIMAL(10,2)  NOT NULL,
            payment_method      VARCHAR(50)    DEFAULT 'Razorpay',
            payment_status      ENUM('pending','paid','failed') DEFAULT 'pending',
            razorpay_order_id   VARCHAR(100)   DEFAULT NULL,
            razorpay_payment_id VARCHAR(100)   DEFAULT NULL,
            razorpay_signature  VARCHAR(256)   DEFAULT NULL,
            created_at          DATETIME       DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_order_id  (razorpay_order_id),
            INDEX idx_status    (payment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $pdo->prepare("
        INSERT INTO donations
            (donor_name, donor_email, amount, payment_method, payment_status, razorpay_order_id, created_at)
        VALUES
            (:name, :email, :amount, 'Razorpay', 'pending', :order_id, NOW())
    ");
    $stmt->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':amount'   => $amount,
        ':order_id' => $orderId,
    ]);

} catch (PDOException $e) {
    error_log('[AL Hind] DB insert failed: ' . $e->getMessage());
    // Still return the order — payment can proceed, we'll capture on verify
}

// ── Return order details to frontend ─────────────────────────
echo json_encode([
    'status'   => 'success',
    'order_id' => $orderId,
    'amount'   => $amountPaise,
    'currency' => 'INR',
    'key_id'   => RZP_KEY_ID,
]);