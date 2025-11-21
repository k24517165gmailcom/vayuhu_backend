<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ------------------
// CORS HEADERS
// ------------------
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include "db.php";

    // Fetch all workspace bookings for admin view
    $sql = "
    SELECT 
        wb.booking_id AS id,
        u.name AS name,
        u.phone AS mobile_no,
        wb.workspace_title AS space,
        s.space_code AS space_code,
        wb.plan_type AS pack,
        wb.start_date AS date,
        CONCAT(wb.start_time, ' - ', wb.end_time) AS timings,
        wb.final_amount AS amount,
        wb.discount_amount AS discount,
        wb.final_amount AS final_total,
        wb.created_at AS booked_on
    FROM workspace_bookings wb
    JOIN users u ON u.id = wb.user_id
    JOIN spaces s ON s.id = wb.space_id
    ORDER BY wb.created_at DESC
";


    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("SQL Error: " . $conn->error);
    }

    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }

    echo json_encode([
        "success" => true,
        "reservations" => $reservations
    ]);

    $conn->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
