<?php
// --- CORS Configuration ---
$allowed_origin = "http://localhost:5173"; // your React app runs on this port (Vite)
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Response Type ---
header("Content-Type: application/json; charset=UTF-8");

// --- Include Database ---
require_once 'db.php'; // use your db.php file

// --- Get JSON Input ---
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(["status" => "error", "message" => "Invalid input data."]);
    exit;
}

// --- Extract form data ---
$name = trim($input["name"] ?? "");
$email = trim($input["email"] ?? "");
$phone = trim($input["phone"] ?? "");

// --- Validation ---
if (empty($name) || empty($phone)) {
    echo json_encode(["status" => "error", "message" => "Name and phone are required."]);
    exit;
}

// --- Check duplicate phone ---
$checkSql = "SELECT id FROM users WHERE phone = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("s", $phone);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "User with this phone already exists."]);
    $checkStmt->close();
    $conn->close();
    exit;
}
$checkStmt->close();

// --- Insert new user ---
$sql = "INSERT INTO users (name, email, phone, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $name, $email, $phone);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "User added successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
