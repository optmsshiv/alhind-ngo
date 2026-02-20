<?php
header('Content-Type: application/json');

require '../config/db.php';
require '../vendor/autoload.php';

use Razorpay\Api\Api;

/* ---------- READ JSON INPUT ---------- */
$data = json_decode(file_get_contents("php://input"), true);

$amount = $data['amount'] ?? 0;

/* ---------- VALIDATION ---------- */
if (!is_numeric($amount) || $amount < 100) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid amount'
    ]);
    exit;
}
/* ---------- CREATE RAZORPAY ORDER ---------- */ 

try {

    /* ---------- INIT RAZORPAY ---------- */
    $api = new Api(
        RAZORPAY_KEY_ID,
        RAZORPAY_KEY_SECRET
    );

    /* ---------- CREATE ORDER ---------- */
    $order = $api->order->create([
        'amount'   => $amount,
        'currency' => 'INR',
        'receipt'  => 'don_' . time(),
        'payment_capture' => 1
    ]);

    /* ---------- STORE PENDING IN DB (IMPORTANT) ---------- */
    $oid = $order['id'];
    $inrAmount = $amount / 100;

    $stmt = $pdo->prepare("
        INSERT INTO donations_pending
        (order_id, amount, status, donor_name, donor_email, created_at)
        VALUES
        (:order_id, :amount, 'PENDING', :donor_name, :donor_email, NOW())
    ");

    $stmt->execute([
        ':order_id' => $oid,
        ':amount'   => $inrAmount
    ]);

    /* ---------- RETURN JSON ONLY ---------- */
    echo json_encode([
        'status' => 'success',
        'id'     => $oid,
        'amount' => $amount,
        'currency' => 'INR'
    ]);

} catch (Exception $e) {

    error_log('Razorpay Order Error: ' . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Order creation failed'
    ]);

}
