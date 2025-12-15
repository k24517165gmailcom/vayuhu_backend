<?php
// ------------------------------------
// CORS Configuration
// ------------------------------------
$allowed_origin = "http://localhost:5173"; // update when deployed
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
// Include Database
// ------------------------------------
require_once 'db.php';

// ------------------------------------
// Get Contact ID
// ------------------------------------
$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode(["success" => false, "message" => "Missing contact ID."]);
    exit;
}

// ------------------------------------
// Fetch Contact
// ------------------------------------
$sql = "SELECT id, name, email, phone, status, comments, DATE_FORMAT(created_at, '%d-%m-%Y %h:%i %p') AS created_at 
        FROM contact_requests 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Contact not found."]);
    exit;
}

$contact = $result->fetch_assoc();

echo json_encode(["success" => true, "contact" => $contact]);

$stmt->close();
$conn->close();
?>
