<?php
// --- CORS Configuration ---
$allowed_origin = "http://localhost:5173";
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Response Type ---
header("Content-Type: application/json; charset=UTF-8");

// --- Include Database ---
require_once 'db.php';

// --- Get JSON Input ---
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON input."]);
    exit;
}

$email = trim($input["email"] ?? "");
$password = $input["password"] ?? "";

// --- Basic Validation ---
if (empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Email and password are required."]);
    exit;
}

// --- Fetch admin by email ---
$sql = "SELECT id, name, email, password, role FROM admins WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "No admin account found with this email."]);
    $stmt->close();
    $conn->close();
    exit;
}

$admin = $result->fetch_assoc();

// --- Verify password ---
if (!password_verify($password, $admin["password"])) {
    echo json_encode(["status" => "error", "message" => "Incorrect password."]);
    $stmt->close();
    $conn->close();
    exit;
}

// --- Login successful ---
unset($admin["password"]); // remove password from response

echo json_encode([
    "status" => "success",
    "message" => "Admin login successful.",
    "admin" => $admin
]);

$stmt->close();
$conn->close();
?>
