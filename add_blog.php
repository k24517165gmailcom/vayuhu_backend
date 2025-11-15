<?php
// -------------------------
// CORS
// -------------------------
$allowed_origin = "http://localhost:5173";
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

// -------------------------
// Prevent PHP warnings from breaking JSON
// -------------------------
ini_set("display_errors", 0);
error_reporting(E_ALL);

// -------------------------
// Database
// -------------------------
require_once "db.php";
if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// -------------------------
// Upload Limits (important for JSON reliability)
// -------------------------
ini_set("upload_max_filesize", "5M");
ini_set("post_max_size", "10M");

// -------------------------
// Validate Request Method
// -------------------------
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

// -------------------------
// Required Fields
// -------------------------
$required = ["added_by", "blog_heading", "blog_description"];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === "") {
        echo json_encode(["success" => false, "message" => "$field is required"]);
        exit;
    }
}

// -------------------------
// Sanitize Inputs
// -------------------------
function val($key) {
    return isset($_POST[$key])
        ? trim($_POST[$key])
        : null;
}

$added_by = val("added_by");
$blog_heading = val("blog_heading");
$blog_description = val("blog_description");

// -------------------------
// Duplicate Blog Heading Check (Optional)
// -------------------------
$chk = $conn->prepare("SELECT id FROM blogs WHERE blog_heading = ?");
$chk->bind_param("s", $blog_heading);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "Blog heading already exists"
    ]);
    exit;
}
$chk->close();

// -------------------------
// Image Upload Validation (improved & robust)
// -------------------------
if (!isset($_FILES["blog_image"]) || $_FILES["blog_image"]["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "Blog image is required or upload failed."]);
    exit;
}

$fileTmp = $_FILES["blog_image"]["tmp_name"];
$originalName = $_FILES["blog_image"]["name"];
$fileSize = $_FILES["blog_image"]["size"];

// Basic size check (5MB)
$maxBytes = 5 * 1024 * 1024;
if ($fileSize > $maxBytes) {
    echo json_encode(["success" => false, "message" => "Image exceeds maximum allowed size of 5MB"]);
    exit;
}

// Determine MIME type in a few ways for reliability
$mime = null;

// 1) Prefer the fileinfo extension
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detected = finfo_file($finfo, $fileTmp);
        if ($detected !== false) $mime = $detected;
        finfo_close($finfo);
    }
}

// 2) Fallback to getimagesize for images
if ($mime === null || $mime === false) {
    $gs = @getimagesize($fileTmp);
    if ($gs && isset($gs['mime'])) {
        $mime = $gs['mime'];
    }
}

// 3) Final fallback to provided client MIME (less trustworthy)
if ($mime === null || $mime === false) {
    $mime = isset($_FILES["blog_image"]["type"]) ? $_FILES["blog_image"]["type"] : null;
}

if (!$mime) {
    echo json_encode(["success" => false, "message" => "Unable to determine uploaded file type"]);
    exit;
}

// Acceptable MIME types (include common variants)
$allowed = [
    "image/jpeg", "image/pjpeg", "image/jpg",
    "image/png", "image/x-png",
    "image/webp",
    "image/gif",
    "image/avif" // <-- added support for AVIF
];


if (!in_array(strtolower($mime), $allowed, true)) {
    // helpful debug info (remove in production)
    echo json_encode([
        "success" => false,
        "message" => "Invalid image format: detected('$mime'). Allowed: jpeg/png/webp/gif.",
    ]);
    exit;
}

// Ensure upload dir exists
$uploadDir = "uploads/blogs/";
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(["success" => false, "message" => "Failed to create upload directory"]);
        exit;
    }
}

// Generate safe file name and move
$safeName = uniqid("blog_") . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "_", $originalName);
$filePath = $uploadDir . $safeName;

if (!move_uploaded_file($fileTmp, $filePath)) {
    echo json_encode(["success" => false, "message" => "Image upload failed (move_uploaded_file)"]);
    exit;
}

// -------------------------
// Insert Blog
// -------------------------
$sql = "INSERT INTO blogs 
        (added_by, blog_heading, blog_description, blog_image, created_at)
        VALUES (?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssss",
    $added_by,
    $blog_heading,
    $blog_description,
    $filePath
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Blog added successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "DB Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();

?>
