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
    include 'db.php';

    // ------------------
    // Read JSON Input
    // ------------------
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON payload.");
    }

    // ------------------
    // Extract Fields
    // ------------------
    $user_id         = (int)($data['user_id'] ?? 0);
    $space_id        = $data['space_id'] ?? '';
    $workspace_title = $data['workspace_title'] ?? '';
    $plan_type       = $data['plan_type'] ?? '';
    $start_date      = $data['start_date'] ?? '';
    $end_date        = $data['end_date'] ?? '';
    $start_time      = $data['start_time'] ?? '';
    $end_time        = $data['end_time'] ?? '';
    $total_days      = (int)($data['total_days'] ?? 1);
    $total_hours     = (int)($data['total_hours'] ?? 1);
    $num_attendees   = (int)($data['num_attendees'] ?? 1);
    $price_per_unit  = (float)($data['price_per_unit'] ?? 0);
    $base_amount     = (float)($data['base_amount'] ?? 0);
    $gst_amount      = (float)($data['gst_amount'] ?? 0);
    $discount_amount = (float)($data['discount_amount'] ?? 0);
    $final_amount    = (float)($data['final_amount'] ?? 0);
    $coupon_code     = $data['coupon_code'] ?? '';
    $referral_source = $data['referral_source'] ?? '';
    $terms_accepted  = (int)($data['terms_accepted'] ?? 0);

    // ------------------
    // Basic Validation
    // ------------------
    if ($user_id <= 0) throw new Exception("Missing or invalid user_id.");

    if (!$space_id || !$workspace_title || !$plan_type || !$start_date || !$end_date) {
        throw new Exception("Missing required fields.");
    }

    // Validate date format
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date)) {
        throw new Exception("Invalid start_date format. Expected YYYY-MM-DD.");
    }
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date)) {
        throw new Exception("Invalid end_date format. Expected YYYY-MM-DD.");
    }

    // Validate time optional fields
    if ($start_time && !preg_match("/^\d{2}:\d{2}$/", $start_time)) {
        throw new Exception("Invalid start_time (expected HH:MM).");
    }
    if ($end_time && !preg_match("/^\d{2}:\d{2}$/", $end_time)) {
        throw new Exception("Invalid end_time (expected HH:MM).");
    }

    // ------------------
    // Validate user exists
    // ------------------
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        throw new Exception("Invalid user_id: user not found.");
    }
    $stmt->close();

    // ------------------
    // Generate Booking ID
    // Format: BKG-YYYYMMDD-001
    // ------------------
    $today = date("Ymd");
    $query = "
        SELECT booking_id 
        FROM workspace_bookings 
        WHERE booking_id LIKE 'BKG-$today-%'
        ORDER BY booking_id DESC 
        LIMIT 1
    ";

    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $lastNum = (int)substr($row['booking_id'], -3);
        $nextNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $nextNum = "001";
    }

    $booking_id = "BKG-$today-$nextNum";

    // ------------------
    // INSERT BOOKING
    // ------------------
    $stmt = $conn->prepare("
        INSERT INTO workspace_bookings (
            booking_id, user_id, space_id, workspace_title, plan_type,
            start_date, end_date, start_time, end_time,
            total_days, total_hours, num_attendees,
            price_per_unit, base_amount, gst_amount, discount_amount, final_amount,
            coupon_code, referral_source, terms_accepted
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) throw new Exception("Prepare failed.");

    // Correct bind_param types
    $stmt->bind_param(
    "siissssssiidddddssii",
    $booking_id,
    $user_id,
    $space_id,
    $workspace_title,
    $plan_type,
    $start_date,
    $end_date,
    $start_time,
    $end_time,
    $total_days,
    $total_hours,
    $num_attendees,
    $price_per_unit,
    $base_amount,
    $gst_amount,
    $discount_amount,
    $final_amount,
    $coupon_code,
    $referral_source,
    $terms_accepted
);


    if (!$stmt->execute()) {
        error_log("INSERT ERROR: " . $stmt->error);
        throw new Exception("Could not save booking. Please try again.");
    }

    // ------------------
    // Success response
    // ------------------
    echo json_encode([
        "success" => true,
        "message" => "Booking saved successfully.",
        "booking_id" => $booking_id
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
