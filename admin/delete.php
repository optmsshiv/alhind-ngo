<?php
session_start();
include '../config/db.php';

$id=$_GET['id'];

$conn->query("DELETE FROM ngo_inquiries WHERE id=$id");

header("Location: dashboard.php");
?>
