<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Content-Type: application/json; charset=UTF-8");

include "db.php";

// Get user_id from query
$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(["success" => false, "message" => "User ID is required."]);
    exit;
}

// Fetch company profile
$sql = "SELECT * FROM company_profile WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $profile = $result->fetch_assoc();

    // Fix logo URL for frontend
    if ($profile['logo']) {
        $profile['logo'] = "http://localhost/vayuhu_backend/" . $profile['logo'];
    }

    echo json_encode(["success" => true, "profile" => $profile]);
} else {
    echo json_encode(["success" => false, "message" => "No company profile found."]);
}

$stmt->close();
$conn->close();
?>
