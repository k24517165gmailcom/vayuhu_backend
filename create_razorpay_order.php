<?php
require('razorpay-php/Razorpay.php');
require('config.php');

use Razorpay\Api\Api;

// CORS for React
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$api = new Api($razorpay_config['api_key'], $razorpay_config['api_secret']);

// Read amount from frontend (in INR)
$data = json_decode(file_get_contents("php://input"), true);
$amount = isset($data['amount']) ? (int)$data['amount'] : 0;

if ($amount <= 0) {
  echo json_encode(["success" => false, "message" => "Invalid amount"]);
  exit;
}

// Create order in Razorpay (amount in paise)
$order = $api->order->create([
  'amount' => $amount * 100,
  'currency' => 'INR',
  'receipt' => 'order_' . time(),
]);

echo json_encode([
  "success" => true,
  "order_id" => $order['id'],
  "key" => $razorpay_config['api_key']
]);
?>
