<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../config/db.php';
require '../vendor/autoload.php';

/* ===== BASIC VALIDATION ===== */

if (!isset($_POST['interest'])) {
    echo 'error';
    exit;
}

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$interest = trim($_POST['interest'] ?? '');
$message  = trim($_POST['message'] ?? '');
$ip       = $_SERVER['REMOTE_ADDR'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo 'invalid';
    exit;
}

/* ===== JOINING FEE + STATUS ===== */

$fee    = 0;
$status = 'submitted';

if ($interest === 'volunteer') {
    $fee    = 499;
    $status = 'payment_pending';
}

if ($interest === 'team') {
    $fee    = 999;
    $status = 'payment_pending';
}

/* ===== INSERT INQUIRY ===== */

$stmt = $conn->prepare("
    INSERT INTO ngo_inquiries
    (name, email, phone, interest, joining_fee, status, volunteer_id, message, ip_address)
    VALUES (?,?,?,?,?,?,?,?,?)
");

$volunteer_id = '';

$stmt->bind_param(
    "ssssissss",
    $name,
    $email,
    $phone,
    $interest,
    $fee,
    $status,
    $volunteer_id,
    $message,
    $ip
);

$stmt->execute();

/* ===== TICKET ID ===== */

$id        = $stmt->insert_id;
$ticket_id = 'NGO' . date('Ymd') . $id;

$up = $conn->prepare("UPDATE ngo_inquiries SET ticket_id=? WHERE id=?");
$up->bind_param("si", $ticket_id, $id);
$up->execute();

$stmt->close();
$up->close();

/* ===== SMTP ===== */

function smtp()
{
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = 'smtp.gmail.com';
    $m->SMTPAuth   = true;
    $m->Username   = 'alhindtrust@gmail.com';
    $m->Password   = 'yyym lxhp pyro alyk'; // move to env later
    $m->SMTPSecure = 'tls';
    $m->Port       = 587;
    $m->setFrom('alhindtrust@gmail.com', 'AL Hind Trust');
    $m->CharSet = 'UTF-8';
    return $m;
}

/* ===== SANITIZE FOR EMAIL ===== */

$safe_name     = htmlspecialchars($name);
$safe_interest = htmlspecialchars($interest);
$safe_message  = nl2br(htmlspecialchars($message));

/* ===== USER MAIL ===== */

try {

    $mail = smtp();
    $mail->addEmbeddedImage('../assets/logo.jpeg', 'ngologo');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = "Welcome to AL Hind Trust – Request Received";

    $fee_note = '';
    if ($fee > 0) {
        $pay_url = "https://alhindtrust.com/backend/pay.php?ticket=" . urlencode($ticket_id);
        
        $fee_note = "
        <p style='margin-top:12px;color:#0f3d2e;'>
            <strong>Next Step:</strong><br>
            Please complete the one-time joining contribution of 
            <strong>₹{$fee}</strong> to activate your role.
            Our team will guide you after confirmation.
        </p>

        <div style='margin-top:15px;padding:12px;background:#f0fdf4;border-radius:8px;'>
            <strong style='color:#0f3d2e;'>Joining Fee Payment:</strong><br>
            To complete your registration as a <strong>{$safe_interest}</strong>, please proceed with the payment of 
            <strong>₹{$fee}</strong> by clicking the link below:<br>
            <a href='{$pay_url}' style='display:inline-block;margin-top:8px;padding:10px 16px;background:#0f3d2e;color:#ffffff;text-decoration:none;border-radius:6px;'>
                Pay Joining Fee
            </a>

            <p style='font-size:13px;color:#6b7280;'>
            This link remains valid until your request is processed.
        </p>
        </div>
        ";
    }

    $mail->Body = "
    <table width='600' align='center' cellpadding='0' cellspacing='0'
    style='font-family:Segoe UI;border:1px solid #e5e7eb;'>

    <tr><td style='background:#0f3d2e;color:#fff;padding:16px;text-align:center;'>
        <h2>AL Hind Educational & Charitable Trust</h2>
    </td></tr>

    <tr> <td style='text-align:center;padding:14px;'> 
    <img src='cid:ngologo' style='width:86px;height:86px;border-radius:50%;border:3px solid #0f3d2e;'> </td> </tr>

    <tr><td style='padding:10px 20px;color:#111827;font-size:14px;line-height:1.6;'>
        <p>Dear <strong>{$safe_name}</strong>,</p>
        <p>Thank you for reaching out to <strong>AL Hind Trust</strong>. We have received your inquiry and appreciate your interest in our work. </p>
        <p>We have generated a unique ticket ID for your reference:</p>
        <p><strong>Ticket ID:</strong> {$ticket_id}</p>
        <p>Please keep this ID handy for any future correspondence regarding your inquiry. 
        This is an automated reply to let you know that our team will review your message and respond as soon as possible.
        We aim to reply to all inquiries within 2–3 business days. </p>
        <p>You can also find information about our programs and activities on our website. </p>

        <p> Thank you for your support and for taking the time to connect with us. We look forward to being in touch.<br></p>
        <p> We value your interest in <b>{$safe_interest}</b>. </p>
        <p> Our team will connect with you within <strong>24–48 hours</strong>. </p>
        {$fee_note}
        <hr>
        <p><strong>Your Message:</strong></p>
        <div style='background:#f3f4f6;padding:10px;border-radius:6px;'>
            {$safe_message}
        </div>
    </td></tr>

    <tr> <td style='background:#111827;color:#ffffff;text-align:center;padding:14px;font-size:14px;'> AL Hind Team<br> Madhepura, Bihar<br> <a href='https://alhindtrust.com' style='color:#a7f3d0;text-decoration:none;'>alhindtrust.com</a> </td> </tr>

    </table>";

    $mail->send();

} catch (Exception $e) {
    echo 'mailer:' . $e->getMessage();
    exit;
}

/* ===== ADMIN MAIL ===== */

try {

    $admin = smtp();
    $admin->addAddress('alhindtrust@gmail.com');
    $admin->addReplyTo($email, $safe_name);
    $admin->isHTML(true);
    $admin->Subject = "NEW JOIN LEAD – {$safe_name}";

    $admin->Body = "
    <h3>New Inquiry</h3>
    <table border='1' cellpadding='8' style='border-collapse:collapse;'>
        <tr><td>Name</td><td>{$safe_name}</td></tr>
        <tr><td>Email</td><td>{$email}</td></tr>
        <tr><td>Phone</td><td>{$phone}</td></tr>
        <tr><td>Interest</td><td>{$safe_interest}</td></tr>
        <tr><td>Joining Fee</td><td>₹{$fee}</td></tr>
        <tr><td>Status</td><td>{$status}</td></tr>
        <tr><td>IP</td><td>{$ip}</td></tr>
    </table>
    ";

    $admin->send();

} catch (Exception $e) {
    echo 'admin:' . $e->getMessage();
    exit;
}

echo 'success';
