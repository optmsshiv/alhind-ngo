<?php
$host = "localhost";
$user = "u699609112_alhind";
$pass = "123@Alhindtrust";
$db   = "u699609112_alhind";

$conn = new mysqli($host,$user,$pass,$db);

if($conn->connect_error){
    die("DB FAILED");
}
?>
