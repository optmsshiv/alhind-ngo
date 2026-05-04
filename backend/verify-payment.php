<?php
// ============================================================
//  verify-payment.php — Razorpay Payment Verification
//  AL Hind Educational and Charitable Trust
//  Called by donate.js after Razorpay handler fires
// ============================================================
date_default_timezone_set('Asia/Kolkata');
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

    // Try to UPDATE existing pending record
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
        LIMIT 1
    ");
    $stmt->execute([
        ':method'     => $method,
        ':payment_id' => $razorpayPaymentId,
        ':signature'  => $razorpaySignature,
        ':order_id'   => $razorpayOrderId,
    ]);

    // If no row was matched (order_id not found) — insert a new record
    // This handles cases where create-order.php DB insert failed
    if ($stmt->rowCount() === 0) {
        // Fetch donor details from Razorpay order notes
        $chOrder = curl_init("https://api.razorpay.com/v1/orders/{$razorpayOrderId}");
        curl_setopt_array($chOrder, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => RZP_KEY_ID . ':' . RZP_KEY_SECRET,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $orderResp = curl_exec($chOrder);
        curl_close($chOrder);
        $orderData  = json_decode($orderResp, true);
        $donorName  = $orderData['notes']['donor_name']  ?? 'Unknown';
        $donorEmail = $orderData['notes']['donor_email'] ?? '';
        $amountINR  = ($orderData['amount'] ?? 0) / 100;

        $ins = $pdo->prepare("
            INSERT INTO donations
                (donor_name, donor_email, amount, payment_method, payment_status,
                 razorpay_order_id, razorpay_payment_id, razorpay_signature, created_at, updated_at)
            VALUES
                (:name, :email, :amount, :method, 'paid',
                 :order_id, :payment_id, :signature, NOW(), NOW())
        ");
        $ins->execute([
            ':name'       => $donorName,
            ':email'      => $donorEmail,
            ':amount'     => $amountINR,
            ':method'     => $method,
            ':order_id'   => $razorpayOrderId,
            ':payment_id' => $razorpayPaymentId,
            ':signature'  => $razorpaySignature,
        ]);
    }

    // Fetch the final record to return to frontend
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