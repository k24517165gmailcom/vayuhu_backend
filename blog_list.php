<?php
// -------------------------
// CORS
// -------------------------
$allowed_origin = "http://localhost:5173";
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

// -------------------------
// Prevent PHP warnings
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
// Fetch Blogs
// -------------------------
$sql = "SELECT id, added_by, blog_heading, blog_description, blog_image, created_at 
        FROM blogs 
        ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "SQL Error: " . $conn->error
    ]);
    exit;
}

$blogs = [];

while ($row = $result->fetch_assoc()) {
    // Format values if needed
    $row["blog_image"] = $row["blog_image"] ? $row["blog_image"] : null;

    $blogs[] = $row;
}

echo json_encode([
    "success" => true,
    "total" => count($blogs),
    "data" => $blogs
], JSON_UNESCAPED_SLASHES);

$conn->close();
?>
