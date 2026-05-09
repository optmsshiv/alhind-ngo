<?php
include 'config/db.php';

$receipt = $_POST['receipt'];

$res = $conn->query("SELECT * FROM donations WHERE receipt_no='$receipt'");

if($res->num_rows > 0){

    $row = $res->fetch_assoc();

    echo "<h3>Valid Donation</h3>";

    echo "Name: ".$row['name']."<br>";
    echo "Amount: ₹".$row['amount']."<br>";
    echo "Date: ".$row['created_at'];

}else{

    echo "<h3>Invalid Receipt</h3>";

}
