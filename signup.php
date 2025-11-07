<?php
// Allow requests from your frontend origin
header("Access-Control-Allow-Origin: http://localhost:5173");

// Allow specific methods
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Allow specific headers (this fixes your exact error)
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  http_response_code(200);
  exit();
}

header("Content-Type: application/json");

include 'db.php';


// Get raw POST data
$data = json_decode(file_get_contents("php://input"), true);

$name = $data["name"];
$email = $data["email"];
$password = $data["password"];

// Basic validation
if (empty($name) || empty($email) || empty($password)) {
  echo json_encode(["status" => "error", "message" => "All fields are required."]);
  exit;
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert into database
$sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $name, $email, $hashed_password);

if ($stmt->execute()) {
  echo json_encode(["status" => "success", "message" => "User registered successfully."]);
} else {
  echo json_encode(["status" => "error", "message" => "Email already exists or database error."]);
}

$stmt->close();
$conn->close();
?>
