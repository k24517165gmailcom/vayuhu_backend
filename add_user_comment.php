<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "db.php"; // ✅ Use your actual DB file name

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Read input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['user_id']) || !isset($input['status']) || !isset($input['comment'])) {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

$user_id = intval($input['user_id']);
$status = trim($input['status']);
$comment = trim($input['comment']);
$follow_up_date = !empty($input['follow_up_date']) ? $input['follow_up_date'] : null;
$follow_up_time = !empty($input['follow_up_time']) ? $input['follow_up_time'] : null;

// ✅ 1. Insert into user_comments
$sql = "INSERT INTO user_comments (user_id, status, comment, follow_up_date, follow_up_time, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("issss", $user_id, $status, $comment, $follow_up_date, $follow_up_time);

if ($stmt->execute()) {
    // ✅ 2. Update user status in users table
    $update = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $update->bind_param("si", $status, $user_id);
    $update->execute();
    $update->close();

    echo json_encode(["success" => true, "message" => "Comment added & user status updated"]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
