<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "VAYUHU_SECRET_KEY_CHANGE_THIS";

// Get Authorization header
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(["status" => "error", "message" => "Authorization header missing"]);
    exit;
}

list(, $token) = explode(" ", $headers['Authorization']);
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $userData = (array)$decoded->data;

    // Example secured response
    echo json_encode([
        "status" => "success",
        "message" => "Token is valid",
        "user" => $userData
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Invalid or expired token"]);
}
?>
