<?php
// ============================================================
//  backend/pay.php — Member Joining Payment Page
//  AL Hind Educational and Charitable Trust
//  Uses cURL only — NO Razorpay PHP SDK
// ============================================================
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', 1); // keep off in production; errors go to log

/*
  File lives at: public_html/backend/pay.php
  DB config:     public_html/config/db.php  ($conn — mysqli)
*/
require __DIR__ . '/../config/db.php';

// ── Razorpay credentials ──────────────────────────────────────
define('RZP_KEY_ID',     'rzp_live_SmY6H2HIaVOr6Q');
define('RZP_KEY_SECRET', '3VXI0InXLgL9BlO4B19kroDj');

// ── cURL helper: create Razorpay order ───────────────────────
//    Mirrors razorpayCreateOrder() in create-order.php exactly
function razorpayCreateOrder(string $keyId, string $keySecret, int $amountPaise, string $receipt): array
{
    $payload = json_encode([
        'amount'          => $amountPaise,
        'currency'        => 'INR',
        'receipt'         => $receipt,
        'payment_capture' => 1,
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('cURL error: ' . $curlError);
    }

    $result = json_decode($response, true);

    if ($httpCode !== 200 || empty($result['id'])) {
        $errMsg = $result['error']['description'] ?? $result['error']['code'] ?? 'Unknown Razorpay error';
        throw new Exception('Razorpay API: ' . $errMsg);
    }

    return $result;
}

// ════════════════════════════════════════════════════════════
//  1. VALIDATE TICKET PARAM
// ════════════════════════════════════════════════════════════

if (empty($_GET['ticket'])) {
    die('Invalid payment link — no ticket provided.');
}
$ticket = trim($_GET['ticket']);

// ════════════════════════════════════════════════════════════
//  2. FETCH RECORD FROM DB
// ════════════════════════════════════════════════════════════

$stmt = $conn->prepare("
    SELECT id, name, email, phone, interest, joining_fee, status
    FROM ngo_inquiries
    WHERE ticket_id = ?
    LIMIT 1
");
if (!$stmt) {
    die('DB prepare failed: ' . $conn->error);
}
$stmt->bind_param('s', $ticket);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die('Invalid ticket — not found in database.');
}
$row = $res->fetch_assoc();

// ════════════════════════════════════════════════════════════
//  3. STATUS CHECK
// ════════════════════════════════════════════════════════════

if ($row['status'] !== 'payment_pending') {
    die('This payment link is no longer active. Status: ' . htmlspecialchars($row['status']));
}

$amount = (int)$row['joining_fee'];
if ($amount <= 0) {
    die('No payment amount on record.');
}

// ════════════════════════════════════════════════════════════
//  4. CREATE RAZORPAY ORDER via cURL
// ════════════════════════════════════════════════════════════

try {
    $order    = razorpayCreateOrder(RZP_KEY_ID, RZP_KEY_SECRET, $amount * 100, $ticket);
    $order_id = $order['id'];
} catch (Exception $e) {
    error_log('[AL Hind] Razorpay order creation failed: ' . $e->getMessage());
    die('Could not create payment order: ' . $e->getMessage());
}

// ════════════════════════════════════════════════════════════
//  5. STORE ORDER ID IN DB
// ════════════════════════════════════════════════════════════

$up = $conn->prepare("UPDATE ngo_inquiries SET razorpay_order_id = ? WHERE ticket_id = ?");
if ($up) {
    $up->bind_param('ss', $order_id, $ticket);
    $up->execute();
}

// ════════════════════════════════════════════════════════════
//  6. RENDER PAYMENT PAGE
// ════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complete Payment — AL Hind Trust</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0 }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .box {
            max-width: 440px; width: 100%;
            background: #fff; border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,.10);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #0f766e, #0a4e48);
            padding: 24px 28px; color: #fff;
        }
        .header h1 { font-size: 1.1rem; font-weight: 700 }
        .header p  { font-size: .82rem; opacity: .75; margin-top: 3px }
        .body { padding: 28px }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: .9rem }
        td { padding: 9px 12px }
        tr:nth-child(odd) td { background: #f0fdf4 }
        td:first-child { color: #64748b; width: 130px }
        td:last-child  { font-weight: 600; color: #1e293b }
        button {
            width: 100%; padding: 14px;
            background: #0f766e; color: #fff;
            border: none; border-radius: 8px;
            font-size: 1rem; font-weight: 700;
            cursor: pointer; letter-spacing: .3px;
        }
        button:hover { background: #0d6561 }
        .note { text-align: center; font-size: .75rem; color: #94a3b8; margin-top: 12px }
    </style>
</head>
<body>
<div class="box">
    <div class="header">
        <h1>AL Hind Educational &amp; Charitable Trust</h1>
        <p>Madhepura, Bihar · alhindtrust.com</p>
    </div>
    <div class="body">
        <table>
            <tr>
                <td>Name</td>
                <td><?= htmlspecialchars($row['name']) ?></td>
            </tr>
            <tr>
                <td>Role</td>
                <td><?= ucfirst(htmlspecialchars($row['interest'])) ?></td>
            </tr>
            <tr>
                <td>Ticket ID</td>
                <td style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($ticket) ?></td>
            </tr>
            <tr>
                <td>Amount</td>
                <td style="color:#0f766e;font-size:1.1rem">
                    ₹<?= $amount ?>
                    <span style="font-size:.75rem;font-weight:400;color:#64748b">(one-time)</span>
                </td>
            </tr>
        </table>

        <button id="payBtn">Pay ₹<?= $amount ?> Now →</button>
        <p class="note">🔒 Secured by Razorpay · 256-bit SSL</p>
    </div>
</div>

<script>
var options = {
    key:         "<?= RZP_KEY_ID ?>",
    amount:      <?= $amount * 100 ?>,
    currency:    "INR",
    name:        "AL Hind Trust",
    description: "Member Joining Contribution",
    order_id:    "<?= htmlspecialchars($order_id, ENT_QUOTES) ?>",
    handler: function(response) {
        window.location.href =
            "verify.php"
            + "?ticket="               + encodeURIComponent("<?= addslashes($ticket) ?>")
            + "&razorpay_payment_id="  + response.razorpay_payment_id
            + "&razorpay_order_id="    + response.razorpay_order_id
            + "&razorpay_signature="   + response.razorpay_signature;
    },
    prefill: {
        name:    "<?= htmlspecialchars($row['name'],  ENT_QUOTES) ?>",
        email:   "<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>",
        contact: "<?= htmlspecialchars($row['phone'], ENT_QUOTES) ?>"
    },
    theme: { color: "#0f766e" }
};

document.getElementById('payBtn').onclick = function() {
    var rzp = new Razorpay(options);
    rzp.open();
};
</script>
</body>
</html>