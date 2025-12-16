<?php
// ------------------------------------
// CORS Configuration
// ------------------------------------
$allowed_origin = "http://localhost:5173"; // update for production
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ------------------------------------
// Response Type
// ------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// ------------------------------------
// Include Database Connection
// ------------------------------------
require_once "db.php"; // must define $conn (mysqli)

// ====================================
// UPDATE ENQUIRY STATUS (POST)
// ====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid input data."
        ]);
        exit;
    }

    $id     = intval($input["id"] ?? 0);
    $status = trim($input["status"] ?? "");

    if ($id <= 0 || empty($status)) {
        echo json_encode([
            "status" => "error",
            "message" => "Enquiry ID and status are required."
        ]);
        exit;
    }

    $allowedStatuses = ["New", "Follow-Up", "Ongoing", "Closed"];

    if (!in_array($status, $allowedStatuses)) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid status value."
        ]);
        exit;
    }

    $updateSql = "UPDATE virtual_office_enquiries SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Enquiry status updated successfully."
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// ====================================
// FETCH ENQUIRIES (GET)
// ====================================
$sql = "
    SELECT 
        id,
        name,
        email,
        phone,
        referral_source,
        message,
        status,
        created_at
    FROM virtual_office_enquiries
    ORDER BY created_at DESC
";

$result = $conn->query($sql);

$enquiries = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $enquiries[] = [
            "id" => $row["id"],
            "name" => $row["name"],
            "email" => $row["email"],
            "phone" => $row["phone"],
            "referral_source" => $row["referral_source"],
            "message" => $row["message"],
            "status" => $row["status"],
            "created_at" => $row["created_at"]
        ];
    }
}

echo json_encode($enquiries);

$conn->close();
