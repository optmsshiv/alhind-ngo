<?php
// ============================================================
//  verify-payment.php — v4 (with 80G receipt email)
//  AL Hind Educational and Charitable Trust
// ============================================================
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ── CORS ─────────────────────────────────────────────────────
$allowedOrigins = ['https://alhindtrust.com', 'https://www.alhindtrust.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
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

// ── Inline DB ────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=u699609112_alhind;charset=utf8mb4",
        'u699609112_alhind', '123@Alhindtrust',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    error_log('[AL Hind verify] DB connect failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed']);
    exit;
}

// ── Razorpay Keys ─────────────────────────────────────────────
$rzpKeyId     = 'rzp_test_SlDNLCDQLwY9Ck';
$rzpKeySecret = 'TRBmnePDq3zxJ5JQB60HU2lL';

// ── Read input ────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$razorpayOrderId   = trim($data['razorpay_order_id']   ?? '');
$razorpayPaymentId = trim($data['razorpay_payment_id'] ?? '');
$razorpaySignature = trim($data['razorpay_signature']  ?? '');

error_log("[AL Hind verify] order:{$razorpayOrderId} | payment:{$razorpayPaymentId}");

if (!$razorpayOrderId || !$razorpayPaymentId || !$razorpaySignature) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing payment details']);
    exit;
}

// ── Verify HMAC signature ─────────────────────────────────────
$expectedSig = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $rzpKeySecret);
if (!hash_equals($expectedSig, $razorpaySignature)) {
    error_log("[AL Hind verify] Signature MISMATCH: $razorpayOrderId");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payment verification failed']);
    exit;
}

// ── Fetch payment method ──────────────────────────────────────
$method = 'Razorpay';
try {
    $ch = curl_init("https://api.razorpay.com/v1/payments/{$razorpayPaymentId}");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => "$rzpKeyId:$rzpKeySecret", CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true]);
    $resp = curl_exec($ch); curl_close($ch);
    $pdata = json_decode($resp, true);
    if (!empty($pdata['method'])) $method = ucfirst($pdata['method']);
} catch (Exception $e) { /* keep default */ }

$nowIST = date('Y-m-d H:i:s');

// ── UPDATE pending → paid ─────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        UPDATE donations
        SET payment_status      = 'paid',
            payment_method      = :method,
            razorpay_payment_id = :payment_id,
            razorpay_signature  = :signature,
            updated_at          = :now
        WHERE razorpay_order_id = :order_id
          AND payment_status    = 'pending'
    ");
    $stmt->execute([':method' => $method, ':payment_id' => $razorpayPaymentId,
                    ':signature' => $razorpaySignature, ':now' => $nowIST,
                    ':order_id' => $razorpayOrderId]);
    $updated = $stmt->rowCount();
    error_log("[AL Hind verify] Rows updated: $updated");

    if ($updated === 0) {
        $check = $pdo->prepare("SELECT * FROM donations WHERE razorpay_order_id = ?");
        $check->execute([$razorpayOrderId]);
        $existing = $check->fetch();

        if (!$existing) {
            // Fetch from Razorpay and insert fresh
            $chO = curl_init("https://api.razorpay.com/v1/orders/{$razorpayOrderId}");
            curl_setopt_array($chO, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => "$rzpKeyId:$rzpKeySecret", CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true]);
            $oResp = curl_exec($chO); curl_close($chO);
            $oData = json_decode($oResp, true);

            $ins = $pdo->prepare("INSERT INTO donations (donor_name,donor_email,amount,payment_method,payment_status,razorpay_order_id,razorpay_payment_id,razorpay_signature,created_at,updated_at) VALUES (?,?,?,?,'paid',?,?,?,?,?)");
            $ins->execute([
                $oData['notes']['donor_name']  ?? 'Unknown',
                $oData['notes']['donor_email'] ?? '',
                ($oData['amount'] ?? 0) / 100,
                $method, $razorpayOrderId, $razorpayPaymentId, $razorpaySignature,
                $nowIST, $nowIST
            ]);
        }
    }

    // Fetch full donation record (including receipt_no and id)
    $sel = $pdo->prepare("SELECT * FROM donations WHERE razorpay_order_id = ?");
    $sel->execute([$razorpayOrderId]);
    $donation = $sel->fetch();

} catch (PDOException $e) {
    error_log('[AL Hind verify] DB error: ' . $e->getMessage());
    echo json_encode(['status' => 'success', 'message' => 'Payment verified', 'name' => '', 'amount' => '', 'payment_id' => $razorpayPaymentId]);
    exit;
}

// ── Send 80G receipt email ────────────────────────────────────
// Only send if TCPDF is available; otherwise skip silently
if ($donation && !empty($donation['donor_email'])) {
    $receiptFile = __DIR__ . '/send-receipt.php';
    if (file_exists($receiptFile)) {
        require_once $receiptFile;
        // Merge payment_id into donation array for receipt
        $donation['razorpay_payment_id'] = $razorpayPaymentId;
        $donation['payment_method']      = $method;
        sendDonationReceipt($donation);
    } else {
        error_log('[AL Hind verify] send-receipt.php not found — skipping email');
    }
}

// ── Return success to frontend ────────────────────────────────
echo json_encode([
    'status'     => 'success',
    'message'    => 'Payment verified and recorded',
    'name'       => $donation['donor_name']  ?? '',
    'email'      => $donation['donor_email'] ?? '',
    'amount'     => $donation['amount']      ?? '',
    'payment_id' => $razorpayPaymentId,
]);
