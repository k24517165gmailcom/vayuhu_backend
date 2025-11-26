<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Read raw JSON body
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid request data"]);
    exit;
}

// ✅ Extract user_id
$user_id = $data['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(["success" => false, "message" => "User ID required"]);
    exit;
}

// ✅ Fetch company info automatically
$company_id = null;
$company_name = null;

$getCompany = $conn->prepare("SELECT id, company_name FROM company_profile WHERE user_id = ?");
$getCompany->bind_param("i", $user_id);
$getCompany->execute();
$getCompany->bind_result($company_id, $company_name);
$getCompany->fetch();
$getCompany->close();

if (!$company_id) {
    echo json_encode(["success" => false, "message" => "No company profile found for this user"]);
    exit;
}

// ✅ Collect visitor fields
$name          = trim($data['name'] ?? "");
$contact       = trim($data['contact'] ?? "");
$email         = trim($data['email'] ?? "");
$visitingDate  = trim($data['visitingDate'] ?? "");
$visitingTime  = trim($data['visitingTime'] ?? "");
$reason        = trim($data['reason'] ?? "");

// ✅ Validation
if (empty($name) || empty($contact)) {
    echo json_encode(["success" => false, "message" => "Name and Contact No are required"]);
    exit;
}

// ✅ Insert into database
$sql = "INSERT INTO visitors (user_id, company_id, name, contact_no, email, company_name, visiting_date, visiting_time, reason, added_on)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iisssssss", $user_id, $company_id, $name, $contact, $email, $company_name, $visitingDate, $visitingTime, $reason);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Visitor added successfully",
        "visitorId" => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
