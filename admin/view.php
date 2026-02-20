<?php
session_start();
include '../config/db.php';

$id=$_GET['id'];

$r=$conn->query("SELECT * FROM ngo_inquiries WHERE id=$id")->fetch_assoc();
?>

<h2>View Lead</h2>

<p><b>Name:</b> <?php echo $r['name']; ?></p>
<p><b>Email:</b> <?php echo $r['email']; ?></p>
<p><b>Phone:</b> <?php echo $r['phone']; ?></p>
<p><b>Interest:</b> <?php echo $r['interest']; ?></p>

<hr>

<p><?php echo nl2br($r['message']); ?></p>
