<?php

session_start();

if(!isset($_SESSION['admin'])){
header("location:login.php");
exit;
}

include '../config/db.php';

$id=$_GET['id'];

$q=$conn->query("SELECT * FROM ngo_inquiries WHERE id='$id'");

$r=$q->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<title>View Lead</title>

<link rel="stylesheet" href="/css/admin-style.css">

</head>

<body>

<div class="main">

<h2>Inquiry Details</h2>

<div class="view-card">

<p>

<strong>Name :</strong>

<?= $r['name'] ?>

</p>

<p>

<strong>Email :</strong>

<?= $r['email'] ?>

</p>

<p>

<strong>Phone :</strong>

<?= $r['phone'] ?>

</p>

<p>

<strong>Interest :</strong>

<?= $r['interest'] ?>

</p>

<p>

<strong>Status :</strong>

<?= ucfirst($r['status']) ?>

</p>

<p>

<strong>Date :</strong>

<?= $r['created_at'] ?>

</p>

</div>

<a class="btn" href="join-leads.php">

Back

</a>

</div>

</body>

</html>