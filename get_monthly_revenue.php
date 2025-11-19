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

    $sql = "
        SELECT 
            DATE_FORMAT(start_date, '%Y-%m') AS month,
            SUM(final_amount) AS total_revenue
        FROM workspace_bookings
        GROUP BY month
        ORDER BY month ASC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("SQL Error: " . $conn->error);
    }

    $revenue = [];
    while ($row = $result->fetch_assoc()) {
        $revenue[] = $row;
    }

    echo json_encode([
        "success" => true,
        "revenue" => $revenue
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
