
<?php
require '../config/db.php';
require '../razorpay/Razorpay.php';

use Razorpay\Api\Api;

/* ===== BASIC VALIDATION ===== */

if (!isset($_GET['ticket'])) {
    die('Invalid payment link.');
}

$ticket = trim($_GET['ticket']);

/* ===== FETCH RECORD ===== */

$stmt = $conn->prepare("
    SELECT id, name, email, phone, interest, joining_fee, status
    FROM ngo_inquiries
    WHERE ticket_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $ticket);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die('Invalid ticket.');
}

$row = $res->fetch_assoc();

/* ===== STATUS CHECK ===== */

if ($row['status'] !== 'payment_pending') {
    die('Payment already completed or not required.');
}

$amount = (int)$row['joining_fee'];

if ($amount <= 0) {
    die('No payment required.');
}

/* ===== RAZORPAY CONFIG ===== */

$keyId     = 'rzp_test_xxxxx';
$keySecret = 'xxxxxxxx';

$api = new Api($keyId, $keySecret);

/* ===== CREATE ORDER ===== */

$order = $api->order->create([
    'receipt'         => $ticket,
    'amount'          => $amount * 100, // in paise
    'currency'        => 'INR',
    'payment_capture' => 1
]);

$order_id = $order['id'];

/* ===== STORE ORDER ID ===== */

$up = $conn->prepare("
    UPDATE ngo_inquiries
    SET razorpay_order_id = ?
    WHERE ticket_id = ?
");
$up->bind_param("ss", $order_id, $ticket);
$up->execute();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete Payment</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        body{font-family:Segoe UI;background:#f9fafb}
        .box{max-width:420px;margin:80px auto;padding:20px;
             background:#fff;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.1)}
        button{width:100%;padding:12px;background:#0f3d2e;color:#fff;
               border:none;border-radius:6px;font-size:16px}
    </style>
</head>
<body>

<div class="box">
    <h2>Complete Joining Contribution</h2>
    <p><strong>Name:</strong> <?= htmlspecialchars($row['name']) ?></p>
    <p><strong>Role:</strong> <?= ucfirst($row['interest']) ?></p>
    <p><strong>Amount:</strong> ₹<?= $amount ?></p>

    <button id="payBtn">Pay Now</button>
</div>

<script>
var options = {
    "key": "<?= $keyId ?>",
    "amount": "<?= $amount * 100 ?>",
    "currency": "INR",
    "name": "AL Hind Trust",
    "description": "Joining Contribution",
    "order_id": "<?= $order_id ?>",
    "handler": function (response){
        window.location.href =
        "verify.php?ticket=<?= $ticket ?>" +
        "&razorpay_payment_id=" + response.razorpay_payment_id +
        "&razorpay_order_id=" + response.razorpay_order_id +
        "&razorpay_signature=" + response.razorpay_signature;
    },
    "prefill": {
        "name": "<?= htmlspecialchars($row['name']) ?>",
        "email": "<?= htmlspecialchars($row['email']) ?>",
        "contact": "<?= htmlspecialchars($row['phone']) ?>"
    },
    "theme": {
        "color": "#0f3d2e"
    }
};

document.getElementById('payBtn').onclick = function(){
    var rzp = new Razorpay(options);
    rzp.open();
};
</script>

</body>
</html>
