<?php
// -----------------------------------
// CORS + Headers
// -----------------------------------
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// -----------------------------------
// DB Connection
// -----------------------------------
include "db.php";

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// -----------------------------------
// Query to fetch virtual office prices
// -----------------------------------
$sql = "SELECT 
            id,
            min_duration,
            max_duration,
            price,
            status,
            created_at
        FROM virtualoffice_prices
        ORDER BY id DESC";

$result = $conn->query($sql);
$priceList = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Handle NULL values safely
        foreach ($row as $key => $value) {
            $row[$key] = $value ?? "";
        }
        $priceList[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $priceList
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "No records found."
    ]);
}

// -----------------------------------
// Close connection
// -----------------------------------
$conn->close();
?>
