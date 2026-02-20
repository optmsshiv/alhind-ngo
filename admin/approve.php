<?php
session_start();
include '../config/db.php';

$id = $_GET['id'];

/* generate volunteer ID */
$vid = "VLT".date('Y').rand(1000,9999);

$conn->query("
UPDATE ngo_inquiries 
SET status='approved', volunteer_id='$vid' 
WHERE id=$id
");

/* fetch user */
$r = $conn->query("SELECT * FROM ngo_inquiries WHERE id=$id")->fetch_assoc();

/* SEND APPROVAL MAIL */
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host='smtp.gmail.com';
$mail->SMTPAuth=true;
$mail->Username='alhindtrust@gmail.com';
$mail->Password='APP_PASSWORD';
$mail->SMTPSecure='tls';
$mail->Port=587;

$mail->setFrom('alhindtrust@gmail.com','AL Hind Trust');
$mail->addAddress($r['email']);

$mail->isHTML(true);
$mail->Subject="Volunteer Application Approved";

$mail->Body="
<h2>Congratulations {$r['name']} 🎉</h2>

<p>
Your application to join <b>AL Education and Trust Charitable</b>
has been <b>approved</b>.
</p>

<p>
<b>Your Volunteer ID:</b> {$vid}
</p>

<p>
Our team will contact you shortly with further instructions.
</p>

<p>
Regards,<br>
AL Hind Team
</p>
";

$mail->send();

header("Location: dashboard.php");
