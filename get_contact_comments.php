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
$contact_id = $_GET['contact_id'] ?? '';

if (empty($contact_id)) {
    echo json_encode(["success" => false, "message" => "Missing contact ID."]);
    exit;
}

// ------------------------------------
// Fetch Comments
// ------------------------------------
$sql = "SELECT id, status, comment, 
               DATE_FORMAT(created_at, '%d-%m-%Y %h:%i %p') AS created_at 
        FROM contact_comments 
        WHERE contact_id = ? 
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $contact_id);
$stmt->execute();
$result = $stmt->get_result();

$comments = [];
while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
}

echo json_encode(["success" => true, "comments" => $comments]);

$stmt->close();
$conn->close();
?>
