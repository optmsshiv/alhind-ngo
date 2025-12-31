<?php
require('razorpay-php/Razorpay.php');
use Razorpay\Api\Api;

$api = new Api("RAZORPAY_KEY_ID", "RAZORPAY_KEY_SECRET");

$data = json_decode(file_get_contents("php://input"), true);

$attributes = [
  'razorpay_order_id' => $data['razorpay_order_id'],
  'razorpay_payment_id' => $data['razorpay_payment_id'],
  'razorpay_signature' => $data['razorpay_signature']
];

$api->utility->verifyPaymentSignature($attributes);

// payment verified — here you can:
// ✔ save to DB
// ✔ send email receipt
// ✔ generate 80G receipt
