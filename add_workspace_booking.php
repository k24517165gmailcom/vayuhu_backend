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
    // Extract and sanitize fields
    // ------------------
    $user_id         = (int)($data['user_id'] ?? 0);
    $space_id        = (int)($data['space_id'] ?? 0);
    $workspace_title = trim($data['workspace_title'] ?? '');
    $plan_type       = strtolower(trim($data['plan_type'] ?? ''));
    $start_date      = trim($data['start_date'] ?? '');
    $end_date        = trim($data['end_date'] ?? '');
    $start_time      = trim($data['start_time'] ?? null);
    $end_time        = trim($data['end_time'] ?? null);
    $total_days      = (int)($data['total_days'] ?? 1);
    $total_hours     = (int)($data['total_hours'] ?? 1);
    $num_attendees   = (int)($data['num_attendees'] ?? 1);
    $price_per_unit  = (float)($data['price_per_unit'] ?? 0);
    $base_amount     = (float)($data['base_amount'] ?? 0);
    $gst_amount      = (float)($data['gst_amount'] ?? 0);
    $discount_amount = (float)($data['discount_amount'] ?? 0);
    $final_amount    = (float)($data['final_amount'] ?? 0);
    $coupon_code     = trim($data['coupon_code'] ?? '');
    $referral_source = trim($data['referral_source'] ?? '');
    $terms_accepted  = (int)($data['terms_accepted'] ?? 0);

    // ------------------
    // Basic Validation
    // ------------------
    if ($user_id <= 0) throw new Exception("Missing or invalid user_id.");
    if ($space_id <= 0) throw new Exception("Missing or invalid space_id.");
    if (!$workspace_title || !$plan_type || !$start_date || !$end_date) {
        throw new Exception("Missing required fields.");
    }

    if (!in_array($plan_type, ['hourly', 'daily', 'monthly'])) {
        throw new Exception("Invalid plan_type. Must be 'hourly', 'daily', or 'monthly'.");
    }

    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date)) {
        throw new Exception("Invalid start_date format. Expected YYYY-MM-DD.");
    }
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date)) {
        throw new Exception("Invalid end_date format. Expected YYYY-MM-DD.");
    }

    if ($start_time && !preg_match("/^\d{2}:\d{2}$/", $start_time)) {
        throw new Exception("Invalid start_time format. Expected HH:MM.");
    }
    if ($end_time && !preg_match("/^\d{2}:\d{2}$/", $end_time)) {
        throw new Exception("Invalid end_time format. Expected HH:MM.");
    }

    // Append :00 to times for MySQL TIME type if missing
    if ($start_time && strlen($start_time) === 5) $start_time .= ':00';
    if ($end_time && strlen($end_time) === 5) $end_time .= ':00';

    // ------------------
    // Validate user exists
    // ------------------
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) throw new Exception("Invalid user_id: user not found.");
    $stmt->close();

    // ------------------
    // Validate space exists
    // ------------------
    $stmt = $conn->prepare("SELECT 1 FROM spaces WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $space_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) throw new Exception("Invalid space_id: space not found.");
    $stmt->close();

    // ------------------
    // Check for overlapping bookings
    // ------------------
    if ($plan_type === 'hourly') {
        $stmt = $conn->prepare("
            SELECT 1 
            FROM workspace_bookings 
            WHERE space_id = ?
              AND start_date = ?
              AND start_time < ?
              AND end_time > ?
            LIMIT 1
        ");
        $stmt->bind_param("isss", $space_id, $start_date, $end_time, $start_time);

    } elseif ($plan_type === 'daily') {
        $stmt = $conn->prepare("
            SELECT 1
            FROM workspace_bookings
            WHERE space_id = ?
              AND start_date <= ?
              AND end_date >= ?
            LIMIT 1
        ");
        $stmt->bind_param("iss", $space_id, $end_date, $start_date);

    } elseif ($plan_type === 'monthly') {
        // Monthly: check if any booking exists in the same month/year
        $stmt = $conn->prepare("
            SELECT 1
            FROM workspace_bookings
            WHERE space_id = ?
              AND MONTH(start_date) = MONTH(?)
              AND YEAR(start_date) = YEAR(?)
            LIMIT 1
        ");
        $stmt->bind_param("iss", $space_id, $start_date, $start_date);

        // Calculate total_days and total_hours automatically
        $total_days = date("t", strtotime($start_date));
        $total_hours = $total_days * 24;
    }

    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) throw new Exception("This workspace is already booked for the selected date/time range.");
    $stmt->close();

    // ------------------
    // Generate booking ID
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
    // Insert booking
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
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

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
        throw new Exception("Could not save booking. " . $stmt->error);
    }

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
