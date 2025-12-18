<?php
// --- CORS Configuration ---
$allowed_origin = "http://localhost:5173"; // Your React app origin
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Include Database ---
require_once 'db.php'; // adjust path if needed

// --- Query Visitors ---
$sql = "
    SELECT 
        v.id,
        v.user_id,
        v.company_id,
        v.name AS visitor_name,
        v.contact_no AS contact,          -- Use alias 'contact' for frontend
        v.email AS email,
        v.company_name,
        v.visiting_date,
        v.visiting_time,
        v.reason,
        v.added_on,
        u.name AS user_name
    FROM visitors v
    LEFT JOIN users u ON v.user_id = u.id
    ORDER BY v.added_on DESC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$visitors = [];
while ($row = $result->fetch_assoc()) {
    $visitors[] = [
        "id" => (int)$row['id'],
        "user_id" => (int)$row['user_id'],
        "company_id" => $row['company_id'] ? (int)$row['company_id'] : null,
        "name" => $row['visitor_name'],
        "contact" => $row['contact'],
        "email" => $row['email'],
        "company_name" => $row['company_name'],
        "visiting_date" => $row['visiting_date'],
        "visiting_time" => $row['visiting_time'],
        "reason" => $row['reason'],
        "added_on" => $row['added_on'],
        "user_name" => $row['user_name'] ?? "Unknown User"
    ];
}

echo json_encode([
    "success" => true,
    "visitors" => $visitors
]);

$conn->close();
?>
