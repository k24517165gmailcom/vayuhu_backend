<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

include "db.php"; // <-- your database connection file

// Handle preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Read incoming JSON data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid input data"]);
    exit;
}

// Extract fields
$id       = $data['id'] ?? null;
$name     = $data['name'] ?? '';
$email    = $data['email'] ?? '';
$phone    = $data['phone'] ?? '';
$dob      = $data['dob'] ?? null;
$address  = $data['address'] ?? '';
$password = $data['password'] ?? '';

// Validation
if (!$id || !$name || !$email || !$phone) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

// Build SQL dynamically (skip password if empty)
if (!empty($password)) {
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $sql = "UPDATE users 
            SET name = ?, email = ?, phone = ?, dob = ?, address = ?, password = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $name, $email, $phone, $dob, $address, $hashedPassword, $id);
} else {
    $sql = "UPDATE users 
            SET name = ?, email = ?, phone = ?, dob = ?, address = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $name, $email, $phone, $dob, $address, $id);
}

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "User updated successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to update user"]);
}

$stmt->close();
$conn->close();
?>
