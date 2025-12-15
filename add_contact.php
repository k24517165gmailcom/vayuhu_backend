<?php
// ------------------------------------
// CORS Configuration
// ------------------------------------
$allowed_origin = "http://localhost:5173"; // Update if deployed
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
require_once 'db.php'; // Your database connection file

// ------------------------------------
// Get JSON Input
// ------------------------------------
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(["status" => "error", "message" => "Invalid input data."]);
    exit;
}

// ------------------------------------
// Extract Form Data
// ------------------------------------
$name = trim($input["name"] ?? "");
$email = trim($input["email"] ?? "");
$phone = trim($input["phone"] ?? "");

// ------------------------------------
// Validation
// ------------------------------------
if (empty($name) || empty($phone)) {
    echo json_encode(["status" => "error", "message" => "Name and phone number are required."]);
    exit;
}

// ------------------------------------
// Check for Duplicate Entry (Phone)
// ------------------------------------
$checkSql = "SELECT id FROM contact_requests WHERE phone = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("s", $phone);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Contact with this phone already exists."]);
    $checkStmt->close();
    $conn->close();
    exit;
}
$checkStmt->close();

// ------------------------------------
// Insert Contact Request
// ------------------------------------
$status = "Pending"; // Default status when added
$sql = "INSERT INTO contact_requests (name, email, phone, status, created_at) VALUES (?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $name, $email, $phone, $status);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Contact request added successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
