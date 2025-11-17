<?php
// ------------------------------------
// CORS CONFIG
// ------------------------------------
$allowed_origin = "http://localhost:5173";

header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

// ------------------------------------
// DATABASE CONNECTION
// ------------------------------------
require_once "db.php";

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// ------------------------------------
// GET BLOG BY ID
// ------------------------------------
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid blog ID"]);
    exit;
}

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
        WHERE id = $id
        LIMIT 1";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["success" => false, "message" => "SQL Error: " . $conn->error]);
    exit;
}

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Blog not found"]);
    exit;
}

$row = $result->fetch_assoc();
$row["blog_image"] = !empty($row["blog_image"]) ? "http://localhost/vayuhu_backend/" . $row["blog_image"] : null;

echo json_encode(["success" => true, "blog" => $row], JSON_UNESCAPED_SLASHES);
$conn->close();
?>
