<?php
session_start();
include '../config/db.php';

$id = $_GET['id'];

$conn->query("UPDATE ngo_inquiries SET status='rejected' WHERE id=$id");

/* fetch user */
$r = $conn->query("SELECT * FROM ngo_inquiries WHERE id=$id")->fetch_assoc();

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
$mail->Subject="Volunteer Application Update";

$mail->Body="
<p>Dear {$r['name']},</p>

<p>
Thank you for your interest in joining us.
After review, we are unable to proceed at this time.
</p>

<p>
We appreciate your willingness to support our mission.
</p>

<p>
Regards,<br>
AL Hind Team
</p>
";

$mail->send();

header("Location: dashboard.php");
