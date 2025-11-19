<?php
// -----------------------------
// CORS + Headers
// -----------------------------
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// -----------------------------
// DB Connection
// -----------------------------
include "db.php";

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// -----------------------------
// Parse Input
// -----------------------------
$data = json_decode(file_get_contents("php://input"), true);

$user_id      = $conn->real_escape_string($data['user_id'] ?? '');
$start_date   = $conn->real_escape_string($data['start_date'] ?? '');
$end_date     = $conn->real_escape_string($data['end_date'] ?? '');
$price        = $conn->real_escape_string($data['price'] ?? '');
$total_years  = $conn->real_escape_string($data['total_years'] ?? 1);

// -----------------------------
// Validate Inputs
// -----------------------------
if (empty($user_id) || empty($start_date) || empty($end_date) || empty($price)) {
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit;
}

// -----------------------------
// ✅ Prevent Duplicate Active Booking
// -----------------------------
$checkSql = "SELECT id FROM virtualoffice_bookings 
             WHERE user_id = '$user_id' AND status = 'Active' 
             AND end_date >= CURDATE() 
             LIMIT 1";
$checkResult = $conn->query($checkSql);

if ($checkResult && $checkResult->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "You already have an active booking."
    ]);
    $conn->close();
    exit;
}

// -----------------------------
// ✅ Fetch Active Plan
// -----------------------------
$priceQuery = "SELECT id FROM virtualoffice_prices WHERE status='Active' LIMIT 1";
$priceResult = $conn->query($priceQuery);

if ($priceResult && $priceResult->num_rows > 0) {
    $priceRow = $priceResult->fetch_assoc();
    $price_id = $priceRow['id'];
} else {
    echo json_encode(["success" => false, "message" => "No active plan found."]);
    $conn->close();
    exit;
}

// -----------------------------
// ✅ Insert Booking Record
// -----------------------------
$sql = "INSERT INTO virtualoffice_bookings 
        (user_id, price_id, start_date, end_date, total_years, total_amount, status, created_at)
        VALUES 
        ('$user_id', '$price_id', '$start_date', '$end_date', '$total_years', '$price', 'Active', NOW())";

if ($conn->query($sql)) {
    echo json_encode(["success" => true, "message" => "Booking created successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
}

$conn->close();
?>
