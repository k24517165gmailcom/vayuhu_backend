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

// ✅ Read JSON input
$data = json_decode(file_get_contents("php://input"), true);
$user_id = $data['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(["success" => false, "message" => "User ID required"]);
    exit;
}

// ✅ Fetch visitors with company name joined
$sql = "
    SELECT 
        v.id,
        v.name,
        v.contact_no,
        v.email,
        v.visiting_date,
        v.visiting_time,
        v.reason,
        v.added_on,
        c.company_name
    FROM visitors v
    LEFT JOIN company_profile c ON v.company_id = c.id
    WHERE v.user_id = ?
    ORDER BY v.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$visitors = [];
while ($row = $result->fetch_assoc()) {
    $visitors[] = [
        "id"            => $row["id"],
        "name"          => $row["name"],
        "contact"       => $row["contact_no"],
        "email"         => $row["email"],
        "company_name"  => $row["company_name"] ?: "—",
        "visiting_date" => $row["visiting_date"],
        "visiting_time" => $row["visiting_time"],
        "reason"        => $row["reason"],
        "added_on"      => $row["added_on"]
    ];
}

if (count($visitors) > 0) {
    echo json_encode(["success" => true, "visitors" => $visitors]);
} else {
    echo json_encode(["success" => false, "message" => "No visitors found"]);
}

$stmt->close();
$conn->close();
?>
