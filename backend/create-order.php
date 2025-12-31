<?php
require('razorpay-php/Razorpay.php');
use Razorpay\Api\Api;

$data = json_decode(file_get_contents("php://input"), true);

$api = new Api("RAZORPAY_KEY_ID", "RAZORPAY_KEY_SECRET");

$order = $api->order->create([
  'amount' => $data['amount'],
  'currency' => 'INR',
  'payment_capture' => 1
]);

echo json_encode($order);
