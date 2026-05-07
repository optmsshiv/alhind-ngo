<?php
// ============================================================
//  verify-payment.php — Razorpay Payment Verification
//  AL Hind Educational and Charitable Trust
//  FIXED: Status update, payment_id storage, IST datetime
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

// ── Razorpay Keys ─────────────────────────────────────────────
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

// ── Fetch payment method from Razorpay ───────────────────────
$method = 'Razorpay';
try {
    $ch = curl_init("https://api.razorpay.com/v1/payments/{$razorpayPaymentId}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => RZP_KEY_ID . ':' . RZP_KEY_SECRET,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp  = curl_exec($ch);
    curl_close($ch);
    $pdata = json_decode($resp, true);
    if (!empty($pdata['method'])) {
        $method = ucfirst($pdata['method']);
    }
} catch (Exception $e) {
    error_log('[AL Hind] Could not fetch payment method: ' . $e->getMessage());
}

// ── FIX: Use PHP-computed IST timestamp (bypasses MySQL timezone issue) ──
$nowIST = date('Y-m-d H:i:s'); // PHP already set to Asia/Kolkata above

// ── Update donation record in DB ──────────────────────────────
try {
    $pdo = getDB();

    // FIX: Set MySQL session timezone to IST so any MySQL NOW() calls also use IST
    $pdo->exec("SET time_zone = '+05:30'");

    // FIX: Use PHP timestamp instead of MySQL NOW() to ensure correct IST time
    // Also removed LIMIT 1 — PDO UPDATE doesn't always respect it
    $stmt = $pdo->prepare("
        UPDATE donations
        SET
            payment_status      = 'paid',
            payment_method      = :method,
            razorpay_payment_id = :payment_id,
            razorpay_signature  = :signature,
            updated_at          = :now
        WHERE razorpay_order_id = :order_id
          AND payment_status    = 'pending'
    ");
    $stmt->execute([
        ':method'     => $method,
        ':payment_id' => $razorpayPaymentId,
        ':signature'  => $razorpaySignature,
        ':now'        => $nowIST,
        ':order_id'   => $razorpayOrderId,
    ]);

    $updated = $stmt->rowCount();
    error_log("[AL Hind] Updated rows for order {$razorpayOrderId}: {$updated}");

    // If no pending row matched — insert a fresh paid record
    if ($updated === 0) {
        // Check if already paid (duplicate callback)
        $check = $pdo->prepare("SELECT id, payment_status FROM donations WHERE razorpay_order_id = :order_id");
        $check->execute([':order_id' => $razorpayOrderId]);
        $existing = $check->fetch();

        if ($existing && $existing['payment_status'] === 'paid') {
            // Already verified — just return the record
            error_log("[AL Hind] Order {$razorpayOrderId} already marked paid, returning existing record");
        } else {
            // No record at all — fetch from Razorpay and insert
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
                     razorpay_order_id, razorpay_payment_id, razorpay_signature,
                     created_at, updated_at)
                VALUES
                    (:name, :email, :amount, :method, 'paid',
                     :order_id, :payment_id, :signature,
                     :now, :now)
            ");
            $ins->execute([
                ':name'       => $donorName,
                ':email'      => $donorEmail,
                ':amount'     => $amountINR,
                ':method'     => $method,
                ':order_id'   => $razorpayOrderId,
                ':payment_id' => $razorpayPaymentId,
                ':signature'  => $razorpaySignature,
                ':now'        => $nowIST,
            ]);
        }
    }

    // Fetch final record to return to frontend
    $sel = $pdo->prepare("
        SELECT donor_name, donor_email, amount
        FROM donations
        WHERE razorpay_order_id = :order_id
    ");
    $sel->execute([':order_id' => $razorpayOrderId]);
    $donation = $sel->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[AL Hind] DB update failed: ' . $e->getMessage());
    // Payment is valid — return success even if DB write failed (webhook is fallback)
    echo json_encode([
        'status'  => 'success',
        'message' => 'Payment verified (DB write failed — check logs)',
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