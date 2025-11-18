<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ------------------
// CORS HEADERS
// ------------------
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include "db.php";

    // Read request body
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON payload.");
    }

    $user_id = (int)($data['user_id'] ?? 0);
    if ($user_id <= 0) {
        throw new Exception("Invalid user_id.");
    }

    // Fetch bookings
    $stmt = $conn->prepare("
        SELECT 
            booking_id,
            space_id,
            workspace_title,
            plan_type,
            start_date,
            end_date,
            start_time,
            end_time,
            total_days,
            total_hours,
            num_attendees,
            price_per_unit,
            base_amount,
            gst_amount,
            discount_amount,
            final_amount,
            coupon_code,
            referral_source,
            terms_accepted,
            status,
            created_at
        FROM workspace_bookings
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed.");
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }

    echo json_encode([
        "success" => true,
        "bookings" => $bookings
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
