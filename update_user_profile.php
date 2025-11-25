<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

include "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($_POST['id'])) {
    echo json_encode(["success" => false, "message" => "User ID missing"]);
    exit;
}

$id      = intval($_POST['id']);
$name    = $_POST['name'] ?? '';
$phone   = $_POST['phone'] ?? '';
$dob     = $_POST['dob'] ?? '';
$address = $_POST['address'] ?? '';

// Validate required fields
if (empty($id) || empty($name) || empty($phone)) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

// Handle profile picture upload
$profile_pic_path = null;
if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . "/uploads/profile_pics/";
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

    $file_tmp = $_FILES['profilePic']['tmp_name'];
    $file_name = uniqid("user_") . "_" . basename($_FILES['profilePic']['name']);
    $target_path = $upload_dir . $file_name;

    if (move_uploaded_file($file_tmp, $target_path)) {
        $profile_pic_path = "uploads/profile_pics/" . $file_name;
    }
}

// ✅ Build query WITHOUT email field
if ($profile_pic_path) {
    $sql = "UPDATE users 
            SET name=?, phone=?, dob=?, address=?, profile_pic=? 
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $name, $phone, $dob, $address, $profile_pic_path, $id);
} else {
    $sql = "UPDATE users 
            SET name=?, phone=?, dob=?, address=? 
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $name, $phone, $dob, $address, $id);
}

// Execute update
if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Profile updated successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to update profile"]);
}

$stmt->close();
$conn->close();
?>
