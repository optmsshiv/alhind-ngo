<?php
// ============================================================
//  verify-payment.php — Razorpay Payment Verification
//  AL Hind Educational and Charitable Trust
//  Called by donate.js after Razorpay handler fires
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// ── Dependencies ──────────────────────────────────────────────
require_once __DIR__ . '/../config/db.php';
// No Composer/SDK needed — signature verified with native hash_hmac

// ── Razorpay Keys (must match create-order.php) ───────────────
define('RZP_KEY_ID',     'rzp_test_SlDNLCDQLwY9Ck');
define('RZP_KEY_SECRET', 'TRBmnePDq3zxJ5JQB60HU2lL');

// ── Read input ────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$razorpayOrderId   = trim($data['razorpay_order_id']   ?? '');
$razorpayPaymentId = trim($data['razorpay_payment_id'] ?? '');
$razorpaySignature = trim($data['razorpay_signature']  ?? '');

if (!$razorpayOrderId || !$razorpayPaymentId || !$razorpaySignature) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing payment details']);
    exit;
}

// ── Verify Razorpay signature (HMAC SHA256) ───────────────────
// This is the critical security step — prevents fake payment callbacks
$expectedSignature = hash_hmac(
    'sha256',
    $razorpayOrderId . '|' . $razorpayPaymentId,
    RZP_KEY_SECRET
);

if (!hash_equals($expectedSignature, $razorpaySignature)) {
    error_log('[AL Hind] Signature mismatch for order: ' . $razorpayOrderId);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payment verification failed']);
    exit;
}

// ── Fetch payment method from Razorpay via cURL ───────────────
try {
    $ch = curl_init("https://api.razorpay.com/v1/payments/{$razorpayPaymentId}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => RZP_KEY_ID . ':' . RZP_KEY_SECRET,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp   = curl_exec($ch);
    curl_close($ch);
    $pdata  = json_decode($resp, true);
    $method = ucfirst($pdata['method'] ?? 'online');
} catch (Exception $e) {
    $method = 'Razorpay';
}

// ── Update donation record in DB ──────────────────────────────
try {
    $pdo = getDB();

    // Update the pending record to paid
    $stmt = $pdo->prepare("
        UPDATE donations
        SET
            payment_status      = 'paid',
            payment_method      = :method,
            razorpay_payment_id = :payment_id,
            razorpay_signature  = :signature,
            updated_at          = NOW()
        WHERE
            razorpay_order_id   = :order_id
            AND payment_status  = 'pending'
        LIMIT 1
    ");
    $stmt->execute([
        ':method'     => ucfirst($method),
        ':payment_id' => $razorpayPaymentId,
        ':signature'  => $razorpaySignature,
        ':order_id'   => $razorpayOrderId,
    ]);

    // Fetch the updated record to return donor info
    $sel = $pdo->prepare("
        SELECT donor_name, donor_email, amount
        FROM donations
        WHERE razorpay_order_id = :order_id
        LIMIT 1
    ");
    $sel->execute([':order_id' => $razorpayOrderId]);
    $donation = $sel->fetch();

} catch (PDOException $e) {
    error_log('[AL Hind] DB update failed: ' . $e->getMessage());
    // Payment is verified — still return success even if DB update glitched
    echo json_encode([
        'status'  => 'success',
        'message' => 'Payment verified',
        'name'    => '',
        'amount'  => '',
    ]);
    exit;
}

// ── Return success ────────────────────────────────────────────
echo json_encode([
    'status'     => 'success',
    'message'    => 'Payment verified and recorded',
    'name'       => $donation['donor_name']  ?? '',
    'email'      => $donation['donor_email'] ?? '',
    'amount'     => $donation['amount']      ?? '',
    'payment_id' => $razorpayPaymentId,
]);