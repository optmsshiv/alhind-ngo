<?php

session_start();

include '../config/db.php';

$id=$_GET['id'];

$q=$conn->query("SELECT * FROM ngo_inquiries WHERE id='$id'");

$r=$q->fetch_assoc();


if(isset($_POST['send'])){

$msg=$_POST['message'];

$conn->query("UPDATE ngo_inquiries
SET admin_reply='$msg'
WHERE id='$id'");


// Later PHPMailer SMTP Here

echo "<script>

alert('Reply Saved');

</script>";

}

?>

<!DOCTYPE html>

<html>

<head>

<title>Reply Lead</title>

<link rel="stylesheet"
href="/css/admin-style.css">

</head>

<body>

<div class="main">

<h2>Reply To <?= $r['name'] ?></h2>

<form method="POST">

<textarea name="message"

placeholder="Write Reply"

required

style="width:100%;
height:200px">

</textarea>


<br><br>

<button class="btn btn-primary"
name="send">

Send Reply

</button>

</form>

</div>

</body>

</html>