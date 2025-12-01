<?php
// -------------------------
// CORS
// -------------------------
$allowed_origin = "http://localhost:5173";
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

// -------------------------
// Prevent PHP Warnings from breaking JSON
// -------------------------
ini_set("display_errors", 0);
error_reporting(E_ALL);

// -------------------------
// Database
// -------------------------
require_once "db.php";
if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// -------------------------
// Fetch Coupons
// -------------------------
$sql = "SELECT id, coupon_code, valid_from, valid_to, user_type, space_type, discount, min_price, max_price, pack_type, email, mobile
        FROM coupons
        ORDER BY created_at DESC";

$result = $conn->query($sql);

$coupons = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $coupons[] = $row;
    }
    echo json_encode(["success" => true, "coupons" => $coupons]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to fetch coupons"]);
}

$conn->close();
?>
