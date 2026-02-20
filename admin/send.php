<?php
use PHPMailer\PHPMailer\PHPMailer;

require '../vendor/autoload.php';

session_start();
include '../config/db.php';

$id=$_POST['id'];
$reply=$_POST['reply'];

$r=$conn->query("SELECT * FROM ngo_inquiries WHERE id=$id")->fetch_assoc();


$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->Host='smtp.gmail.com';
$mail->SMTPAuth=true;

$mail->Username='alhindtrust@gmail.com';
$mail->Password='APP_PASSWORD';

$mail->SMTPSecure='tls';
$mail->Port=587;

$mail->setFrom('alhindtrust@gmail.com','AL Hind Admin');
$mail->addAddress($r['email']);

$mail->isHTML(true);

$mail->Subject="Reply from AL Hind Trust";

$mail->Body="
<h3>Dear {$r['name']},</h3>

<p>$reply</p>

<hr>

<p>
Regards,<br>
AL Hind Team<br>
Madhepura, Bihar
</p>
";

$mail->send();


/* UPDATE STATUS */
$conn->query("UPDATE ngo_inquiries SET status='replied' WHERE id=$id");

echo "sent";

?>
