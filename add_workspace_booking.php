<?php
// ✅ Show PHP errors for debugging (remove on production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ✅ CORS headers (for React frontend)
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// ✅ Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // ✅ Include DB connection
    include 'db.php'; // ensure path is correct

    // ✅ Parse JSON input
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        throw new Exception("Invalid or missing JSON input.");
    }

    // ✅ Extract fields safely
    $space_id          = $conn->real_escape_string($data['space_id'] ?? '');
    $workspace_title   = $conn->real_escape_string($data['workspace_title'] ?? '');
    $plan_type         = $conn->real_escape_string($data['plan_type'] ?? '');
    $start_date        = $conn->real_escape_string($data['start_date'] ?? '');
    $end_date          = $conn->real_escape_string($data['end_date'] ?? '');
    $start_time        = $conn->real_escape_string($data['start_time'] ?? '');
    $end_time          = $conn->real_escape_string($data['end_time'] ?? '');
    $total_days        = (int)($data['total_days'] ?? 1);
    $total_hours       = (int)($data['total_hours'] ?? 1);
    $num_attendees     = (int)($data['num_attendees'] ?? 1);
    $price_per_unit    = (float)($data['price_per_unit'] ?? 0);
    $base_amount       = (float)($data['base_amount'] ?? 0);
    $gst_amount        = (float)($data['gst_amount'] ?? 0);
    $discount_amount   = (float)($data['discount_amount'] ?? 0);
    $final_amount      = (float)($data['final_amount'] ?? 0);
    $coupon_code       = $conn->real_escape_string($data['coupon_code'] ?? '');
    $referral_source   = $conn->real_escape_string($data['referral_source'] ?? '');
    $terms_accepted    = (int)($data['terms_accepted'] ?? 0);

    // ✅ Validate required fields
    if (
        empty($space_id) || empty($workspace_title) || empty($plan_type)
        || empty($start_date) || empty($end_date)
    ) {
        throw new Exception("Missing required booking fields.");
    }

    // ✅ Generate sequential booking_id (BKG-YYYYMMDD-001)
    $today = date('Ymd');
    $result = $conn->query("SELECT booking_id FROM workspace_bookings WHERE booking_id LIKE 'BKG-$today-%' ORDER BY id DESC LIMIT 1");

    if ($result && $row = $result->fetch_assoc()) {
        $lastNumber = (int)substr($row['booking_id'], -3);
        $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $nextNumber = '001';
    }

    $booking_id = "BKG-$today-$nextNumber";

    // ✅ Prepare SQL Insert
    $stmt = $conn->prepare("
        INSERT INTO workspace_bookings (
            booking_id, space_id, workspace_title, plan_type, start_date, end_date,
            start_time, end_time, total_days, total_hours, num_attendees,
            price_per_unit, base_amount, gst_amount, discount_amount, final_amount,
            coupon_code, referral_source, terms_accepted
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // ✅ Bind Parameters (19 total)
    $stmt->bind_param(
        "sissssssiiidddddssi",
        $booking_id,
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

    // ✅ Execute and respond
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Booking saved successfully.",
            "booking_id" => $booking_id
        ]);
    } else {
        throw new Exception("Database insert failed: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
