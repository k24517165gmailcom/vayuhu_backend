<?php
// virtualoffice_booking.php

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

$user_id     = $conn->real_escape_string($data['user_id'] ?? '');
$check_only  = $data['check_only'] ?? false; // 👈 NEW FLAG

// -----------------------------
// Validation (Common for both modes)
// -----------------------------
if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "User ID is required."]);
    exit;
}

// -----------------------------
// 1️⃣ CHECK: Does user already have an active booking?
// -----------------------------
$checkSql = "SELECT id FROM virtualoffice_bookings 
             WHERE user_id = '$user_id' 
             AND status = 'Active' 
             AND end_date >= CURDATE() 
             LIMIT 1";

$checkResult = $conn->query($checkSql);

if ($checkResult && $checkResult->num_rows > 0) {
    // If found, they cannot book again.
    echo json_encode([
        "success" => false, 
        "message" => "You already have an active booking."
    ]);
    $conn->close();
    exit;
}

// -----------------------------
// 🛑 STOP HERE IF "CHECK ONLY" MODE
// -----------------------------
if ($check_only) {
    // If we reached here, it means no duplicate was found. User is safe to proceed.
    echo json_encode([
        "success" => true, 
        "message" => "User is eligible to book."
    ]);
    $conn->close();
    exit;
}

// =================================================================
// ⬇️ BOOKING LOGIC (Only runs if check_only is FALSE)
// =================================================================

$start_date     = $conn->real_escape_string($data['start_date'] ?? '');
$end_date       = $conn->real_escape_string($data['end_date'] ?? '');
$price          = $conn->real_escape_string($data['price'] ?? '');
$total_years    = $conn->real_escape_string($data['total_years'] ?? 1);
$payment_id     = $conn->real_escape_string($data['payment_id'] ?? '');
$payment_status = $conn->real_escape_string($data['payment_status'] ?? 'Pending');

if (empty($start_date) || empty($end_date) || empty($price)) {
    echo json_encode(["success" => false, "message" => "Booking details are incomplete."]);
    exit;
}

// Fetch Active Plan ID
$priceQuery = "SELECT id FROM virtualoffice_prices WHERE status='Active' LIMIT 1";
$priceResult = $conn->query($priceQuery);

if ($priceResult && $priceResult->num_rows > 0) {
    $priceRow = $priceResult->fetch_assoc();
    $price_id = $priceRow['id'];
} else {
    echo json_encode(["success" => false, "message" => "No active plan configuration found."]);
    $conn->close();
    exit;
}

// Insert Booking
$sql = "INSERT INTO virtualoffice_bookings 
        (user_id, price_id, start_date, end_date, total_years, total_amount, status, payment_id, payment_status, created_at)
        VALUES 
        ('$user_id', '$price_id', '$start_date', '$end_date', '$total_years', '$price', 'Active', '$payment_id', '$payment_status', NOW())";

if ($conn->query($sql)) {
    echo json_encode(["success" => true, "message" => "Booking created successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
}

$conn->close();
?>