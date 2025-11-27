<?php
require('razorpay-php/Razorpay.php');
require('config.php');

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$api = new Api($razorpay_config['api_key'], $razorpay_config['api_secret']);
$data = json_decode(file_get_contents("php://input"), true);

try {
  $api->utility->verifyPaymentSignature($data);
  echo json_encode(["success" => true]);
} catch (SignatureVerificationError $e) {
  echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
