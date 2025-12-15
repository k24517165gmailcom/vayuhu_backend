<?php
// ------------------------------------
// CORS Configuration
// ------------------------------------
$allowed_origin = "http://localhost:5173"; // Change this when deployed
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ------------------------------------
// Response Type
// ------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// ------------------------------------
// Include Database Connection
// ------------------------------------
require_once 'db.php';

// ------------------------------------
// Fetch All Contacts
// ------------------------------------
$sql = "SELECT id, name, email, phone, status, comments, DATE_FORMAT(created_at, '%d-%m-%Y %h:%i %p') AS date 
        FROM contact_requests 
        ORDER BY created_at DESC";

$result = $conn->query($sql);

$contacts = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $contacts[] = $row;
    }
}

// ------------------------------------
// Send JSON Response
// ------------------------------------
echo json_encode($contacts);

$conn->close();
?>
