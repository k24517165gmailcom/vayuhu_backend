<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "db.php";

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['user_id']) || !isset($data['status']) || !isset($data['comment'])) {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

$user_id = intval($data['user_id']);
$status = trim($data['status']);
$comment = trim($data['comment']);
$follow_up_date = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;
$follow_up_time = !empty($data['follow_up_time']) ? $data['follow_up_time'] : null;

// Insert into user_comments table
$sql = "INSERT INTO user_comments (user_id, status, comment, follow_up_date, follow_up_time, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("issss", $user_id, $status, $comment, $follow_up_date, $follow_up_time);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Comment added successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
