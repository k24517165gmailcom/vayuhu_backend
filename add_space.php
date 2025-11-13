<?php
// --- CORS Configuration ---
$allowed_origin = "http://localhost:5173"; // Adjust if your frontend runs on another port
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

// --- Include Database ---
require_once "db.php";

// --- Validate POST Method ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

// --- Validate Required Fields ---
$required = ["space_code", "space", "status"];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(["success" => false, "message" => ucfirst($field) . " is required."]);
        exit;
    }
}

// --- Sanitize Inputs ---
$space_code = trim($_POST["space_code"]);
$space = trim($_POST["space"]);
$per_hour = $_POST["per_hour"] ?? null;
$per_day = $_POST["per_day"] ?? null;
$one_week = $_POST["one_week"] ?? null;
$two_weeks = $_POST["two_weeks"] ?? null;
$three_weeks = $_POST["three_weeks"] ?? null;
$per_month = $_POST["per_month"] ?? null;
$min_duration = $_POST["min_duration"] ?? null;
$min_duration_desc = $_POST["min_duration_desc"] ?? null;
$max_duration = $_POST["max_duration"] ?? null;
$max_duration_desc = $_POST["max_duration_desc"] ?? null;
$status = $_POST["status"] ?? "Active";

// --- Check for Duplicate Space Code ---
$checkSql = "SELECT id FROM spaces WHERE space_code = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("s", $space_code);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Space with this code already exists."]);
    $checkStmt->close();
    $conn->close();
    exit;
}
$checkStmt->close();

// --- Handle Image Upload ---
$imagePath = "";
if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
    $uploadDir = "uploads/spaces/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileTmp = $_FILES["image"]["tmp_name"];
    $fileName = uniqid("space_") . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "_", $_FILES["image"]["name"]);
    $filePath = $uploadDir . $fileName;

    // Validate image type
    $allowedTypes = ["image/jpeg", "image/png", "image/jpg", "image/webp"];
    $mime = mime_content_type($fileTmp);
    if (!in_array($mime, $allowedTypes)) {
        echo json_encode(["success" => false, "message" => "Invalid image type. Only JPG, PNG, and WEBP allowed."]);
        exit;
    }

    if (!move_uploaded_file($fileTmp, $filePath)) {
        echo json_encode(["success" => false, "message" => "Failed to upload image."]);
        exit;
    }

    $imagePath = $filePath;
} else {
    echo json_encode(["success" => false, "message" => "Image is required."]);
    exit;
}

// --- Insert into Database ---
$sql = "INSERT INTO spaces 
        (space_code, space, per_hour, per_day, one_week, two_weeks, three_weeks, per_month, 
         min_duration, min_duration_desc, max_duration, max_duration_desc, image, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssddddddddssss",
    $space_code,
    $space,
    $per_hour,
    $per_day,
    $one_week,
    $two_weeks,
    $three_weeks,
    $per_month,
    $min_duration,
    $min_duration_desc,
    $max_duration,
    $max_duration_desc,
    $imagePath,
    $status
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Space added successfully!"]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
