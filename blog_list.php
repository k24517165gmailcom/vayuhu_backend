<?php
// ------------------------------------
// CORS CONFIG
// ------------------------------------
$allowed_origin = "http://localhost:5173";

header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

// ------------------------------------
// ERROR HANDLING
// ------------------------------------
ini_set("display_errors", 0);
error_reporting(E_ALL);

// ------------------------------------
// DATABASE CONNECTION
// ------------------------------------
require_once "db.php";

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// ------------------------------------
// FETCH BLOGS
// ------------------------------------
$sql = "SELECT 
            id, 
            added_by, 
            blog_heading, 
            blog_description, 
            blog_image, 
            status,
            created_at,
            updated_at
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

    // Convert empty image to null
    $row["blog_image"] = !empty($row["blog_image"]) ? $row["blog_image"] : null;

    // Format dates (optional)
    $row["created_at"] = $row["created_at"] ?? null;
    $row["updated_at"] = $row["updated_at"] ?? null;

    $blogs[] = $row;
}

// ------------------------------------
// ✅ AUTO-ROTATE BLOG ORDER DAILY (ADDED)
// ------------------------------------
$totalBlogs = count($blogs);

if ($totalBlogs > 0) {
    // Use day of year (0–365) to determine how many positions to rotate
    $dayOfYear = date('z'); 
    $shift = $dayOfYear % $totalBlogs;

    // Rotate the blogs array so that order changes daily
    $blogs = array_merge(
        array_slice($blogs, -$shift),
        array_slice($blogs, 0, -$shift)
    );
}

// ------------------------------------
// RESPONSE
// ------------------------------------
echo json_encode([
    "success" => true,
    "total" => count($blogs),
    "data" => $blogs
], JSON_UNESCAPED_SLASHES);

$conn->close();
?>
