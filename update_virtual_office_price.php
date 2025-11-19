<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (
    empty($data['id']) ||
    empty($data['min_duration']) ||
    empty($data['max_duration']) ||
    empty($data['price']) ||
    empty($data['status'])
) {
    echo json_encode(["status" => "error", "message" => "All fields are required."]);
    exit;
}

$id = $conn->real_escape_string($data['id']);
$min_duration = $conn->real_escape_string($data['min_duration']);
$max_duration = $conn->real_escape_string($data['max_duration']);
$price = $conn->real_escape_string($data['price']);
$status = $conn->real_escape_string($data['status']);

$sql = "UPDATE virtualoffice_prices 
        SET min_duration='$min_duration', 
            max_duration='$max_duration', 
            price='$price', 
            status='$status'
        WHERE id='$id'";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Record updated successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update record."]);
}

$conn->close();
?>
