<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

$space_id = (int)($data['space_id'] ?? 0);
$plan_type = strtolower(trim($data['plan_type'] ?? ''));
$start_date = trim($data['start_date'] ?? '');
$end_date = trim($data['end_date'] ?? '');
$start_time = trim($data['start_time'] ?? '');
$end_time = trim($data['end_time'] ?? '');

if (!$space_id || !$plan_type || !$start_date || !$end_date) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT plan_type, start_date, end_date, start_time, end_time
    FROM workspace_bookings
    WHERE space_id = ?
      AND (
            (plan_type = 'hourly' AND start_date = ? AND NOT (? <= start_time OR ? >= end_time))
         OR (plan_type = 'daily' AND start_date = ?)
         OR (plan_type = 'monthly' AND NOT (? < start_date OR ? > end_date))
          )
    LIMIT 1
");
$stmt->bind_param("issssss", $space_id, $start_date, $end_time, $start_time, $start_date, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "This workspace is already booked for the selected time/date."]);
} else {
    echo json_encode(["success" => true, "message" => "Available"]);
}
$stmt->close();
$conn->close();
