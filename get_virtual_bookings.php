<?php
// -----------------------------
// CORS + Headers
// -----------------------------
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
// Fetch Bookings with User Details
// -----------------------------
// We use LEFT JOIN to ensure we get the booking record even if the user was deleted
$sql = "SELECT 
            b.id,
            b.user_id,
            u.name AS user_name,
            u.email AS user_email,
            b.start_date,
            b.end_date,
            b.total_amount,
            b.payment_id,
            b.payment_status,
            b.status,
            b.created_at
        FROM virtualoffice_bookings b
        LEFT JOIN users u ON b.user_id = u.id
        ORDER BY b.created_at DESC";

$result = $conn->query($sql);

if ($result) {
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    echo json_encode([
        "success" => true, 
        "count" => count($bookings), 
        "bookings" => $bookings
    ]);
} else {
    echo json_encode([
        "success" => false, 
        "message" => "Error executing query: " . $conn->error
    ]);
}

$conn->close();
?>