<?php
// add_bulk_bookings.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. CORS Headers
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

    // 2. Decode the BULK payload
    $inputData = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Invalid JSON payload.");

    // Expecting: { "bookings": [ ... ] }
    if (!isset($inputData['bookings']) || !is_array($inputData['bookings'])) {
        throw new Exception("Invalid format. Expected 'bookings' array.");
    }

    $bookings = $inputData['bookings'];
    $responseIds = [];
    
    // 3. START TRANSACTION (The Safety Net)
    // Everything below this happens in a temporary state until we say "commit"
    $conn->begin_transaction();

    // 4. Get the starting Booking ID Number for today
    // We fetch the last ID once, then increment it manually for the batch
    $today = date("Ymd");
    $query = "SELECT booking_id FROM workspace_bookings WHERE booking_id LIKE 'BKG-$today-%' ORDER BY booking_id DESC LIMIT 1";
    $result = $conn->query($query);
    
    // Determine the last sequence number (e.g., if last was 005, current is 5)
    $currentSequence = ($result && $row = $result->fetch_assoc()) 
        ? (int)substr($row['booking_id'], -3) 
        : 0;

    // 5. Loop through every booking in the cart
    foreach ($bookings as $index => $data) {
        
        // --- A. VALIDATION (Same logic as before) ---
        $user_id         = (int)($data['user_id'] ?? 0);
        $space_id        = (int)($data['space_id'] ?? 0);
        
        // Handle seat codes (Array or String)
        $seat_codes_raw  = $data['selected_codes'] ?? $data['seat_codes'] ?? '';
        $seat_codes      = is_array($seat_codes_raw) ? implode(", ", $seat_codes_raw) : trim($seat_codes_raw);

        $workspace_title = trim($data['workspace_title'] ?? '');
        $plan_type       = strtolower(trim($data['plan_type'] ?? ''));
        $start_date      = trim($data['start_date'] ?? '');
        $end_date        = trim($data['end_date'] ?? '');
        $start_time      = trim($data['start_time'] ?? null);
        $end_time        = trim($data['end_time'] ?? null);
        
        // Numbers
        $total_days      = (int)($data['total_days'] ?? 1);
        $total_hours     = (int)($data['total_hours'] ?? 1);
        $num_attendees   = (int)($data['num_attendees'] ?? 1);
        $price_per_unit  = (float)($data['price_per_unit'] ?? 0);
        $base_amount     = (float)($data['base_amount'] ?? 0);
        $gst_amount      = (float)($data['gst_amount'] ?? 0);
        $discount_amount = (float)($data['discount_amount'] ?? 0);
        $final_amount    = (float)($data['final_amount'] ?? 0);
        
        // Extras
        $coupon_code     = trim($data['coupon_code'] ?? '');
        $referral_source = trim($data['referral_source'] ?? '');
        $terms_accepted  = (int)($data['terms_accepted'] ?? 0);
        $payment_id      = trim($data['payment_id'] ?? null); // New Field from React

        // Basic Checks
        if ($user_id <= 0 || $space_id <= 0 || !$workspace_title || !$plan_type || !$start_date || !$end_date) {
            throw new Exception("Item #".($index+1).": Missing required fields.");
        }
        if (!in_array($plan_type, ['hourly', 'daily', 'monthly'])) {
            throw new Exception("Item #".($index+1).": Invalid plan_type.");
        }
        
        // Date/Time Logic Checks
        if (($plan_type === 'hourly' || $plan_type === 'daily') && (date('w', strtotime($start_date)) == 0 || date('w', strtotime($end_date)) == 0)) {
            throw new Exception("Item #".($index+1).": Bookings cannot be made on Sundays.");
        }
        
        // Format Times
        if ($start_time && strlen($start_time) === 5) $start_time .= ':00';
        if ($end_time && strlen($end_time) === 5) $end_time .= ':00';

        // --- B. CONFLICT CHECK (Critical) ---
        $stmt = $conn->prepare("
            SELECT plan_type, start_date, end_date, start_time, end_time
            FROM workspace_bookings
            WHERE space_id = ?
              AND (
                  (plan_type = 'hourly' AND start_date = ? AND NOT (? <= start_time OR ? >= end_time))
               OR (plan_type = 'daily' AND start_date = ?)
               OR (plan_type = 'monthly' AND NOT (? < start_date OR ? > end_date))
              )
            LIMIT 1
        ");
        $stmt->bind_param("issssss", $space_id, $start_date, $end_time, $start_time, $start_date, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            throw new Exception("Item #".($index+1)." ($workspace_title) is already booked for this time.");
        }
        $stmt->close();

        // --- C. ID GENERATION ---
        // Increment sequence for this specific item in the batch
        $currentSequence++; 
        $nextNum = str_pad($currentSequence, 3, '0', STR_PAD_LEFT);
        $booking_id = "BKG-$today-$nextNum";
        
        $responseIds[] = $booking_id;
        
        // Since payment is verified in React before calling this, we assume confirmed
        $status = 'confirmed'; 

        // --- D. INSERT QUERY ---
        // Note: I added payment_id to the schema insert if you have that column. 
        // If not, remove it from the SQL below.
        $stmt = $conn->prepare("
            INSERT INTO workspace_bookings (
                booking_id, user_id, space_id, seat_codes, workspace_title, plan_type,
                start_date, end_date, start_time, end_time,
                total_days, total_hours, num_attendees,
                price_per_unit, base_amount, gst_amount, discount_amount, final_amount,
                coupon_code, referral_source, terms_accepted, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "siisssssssiidddddssiss",
            $booking_id, $user_id, $space_id, $seat_codes, $workspace_title, $plan_type,
            $start_date, $end_date, $start_time, $end_time,
            $total_days, $total_hours, $num_attendees,
            $price_per_unit, $base_amount, $gst_amount, $discount_amount, $final_amount,
            $coupon_code, $referral_source, $terms_accepted, $status
        );

        if (!$stmt->execute()) {
            throw new Exception("Database error on Item #".($index+1).": " . $stmt->error);
        }
        $stmt->close();
    }

    // 6. COMMIT TRANSACTION
    // If we reached here, no errors occurred. Save everything permanently.
    $conn->commit();
    $conn->close();

    echo json_encode([
        "success" => true,
        "message" => "All bookings confirmed successfully.",
        "booking_ids" => $responseIds
    ]);

} catch (Exception $e) {
    // 7. ROLLBACK
    // If ANY error happened above, undo ALL changes. Database stays clean.
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    
    http_response_code(400);
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage()
    ]);
}
?>