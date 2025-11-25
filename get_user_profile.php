<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "db.php";

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$baseURL = "http://localhost/vayuhu_backend"; // change if folder differs

// ✅ Get user id from query string
if (!isset($_GET['id'])) {
    echo json_encode(["success" => false, "message" => "User ID missing"]);
    exit;
}

$id = intval($_GET['id']);
$sql = "SELECT id, name, email, phone, dob, address, profile_pic FROM users WHERE id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    foreach ($row as $key => $value) {
        $row[$key] = $value ?? "";
    }

    if (!empty($row['profile_pic'])) {
        $row['profile_pic'] = $baseURL . '/' . $row['profile_pic'];
    }

    echo json_encode(["success" => true, "user" => $row]);
} else {
    echo json_encode(["success" => false, "message" => "User not found"]);
}

$stmt->close();
$conn->close();
?>
