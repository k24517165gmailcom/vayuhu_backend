<?php
// send_booking_email.php

// --- CORS Configuration ---
$allowed_origin = "http://localhost:5173";
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Response Type ---
header("Content-Type: application/json; charset=UTF-8");

// --- Dependencies ---
require_once 'db.php';
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Read JSON ---
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(["success" => false, "message" => "Invalid input data"]);
    exit;
}

// --- Extract Global User Data ---
$user_id      = $input["user_id"] ?? null;
$user_email   = trim($input["user_email"] ?? "");
$total_amount = trim($input["total_amount"] ?? ""); // Grand Total
$bookings     = $input["bookings"] ?? []; 

// --- Validation ---
if (empty($user_email) || empty($bookings) || !is_array($bookings)) {
    echo json_encode(["success" => false, "message" => "Missing user or booking details."]);
    exit;
}

// --- Compose Email Content ---
$subject = "Your Booking Confirmation - Vayuhu Workspaces";

// Start HTML body
$body = "
<html>
<head><style>
body { font-family: Arial, sans-serif; color: #333; }
.table { border-collapse: collapse; width: 100%; margin-top: 10px; margin-bottom: 20px; } /* Added margin-bottom for spacing */
.table td, .table th { border: 1px solid #ddd; padding: 8px; }
.table th { background-color: #f97316; color: white; text-align: left; width: 35%; }
.total-block { background: #eee; padding: 10px; text-align: right; font-weight: bold; font-size: 1.1em; }
</style></head>
<body>
  <h2>Booking Confirmation</h2>
  <p>Dear Customer,</p>
  <p>Thank you for booking with <strong>Vayuhu Workspaces</strong>. Below are your booking details:</p>
";

// --- Loop through all bookings ---
foreach ($bookings as $index => $booking) {
    $workspace_title = $booking['workspace_title'] ?? '';
    $plan_type       = $booking['plan_type'] ?? '';
    $start_date      = $booking['start_date'] ?? '';
    $end_date        = $booking['end_date'] ?? '';
    $start_time      = $booking['start_time'] ?? '';
    $end_time        = $booking['end_time'] ?? '';
    $final_amount    = $booking['final_amount'] ?? '';
    
    // 🟢 UPDATE 1: Capture Seat Codes (Handle array or string)
    $seat_codes_raw  = $booking['selected_codes'] ?? $booking['seat_codes'] ?? '';
    $seat_codes      = is_array($seat_codes_raw) ? implode(", ", $seat_codes_raw) : $seat_codes_raw;

    // 🟢 UPDATE 2: Check for coupon specific to this item
    $item_coupon     = $booking['coupon_code'] ?? ''; 
    $item_referral   = $booking['referral_source'] ?? '';

    $body .= "
    <table class='table'>
      <tr><th colspan='2'>Item #" . ($index + 1) . ": $workspace_title</th></tr>
      <tr><th>Plan Type</th><td>{$plan_type}</td></tr>
      
      " . (!empty($seat_codes) ? "<tr><th>Seat Numbers</th><td><strong>{$seat_codes}</strong></td></tr>" : "") . "
      
      <tr><th>Start Date</th><td>{$start_date}</td></tr>
      <tr><th>End Date</th><td>{$end_date}</td></tr>
      <tr><th>Time</th><td>{$start_time} - {$end_time}</td></tr>
      <tr><th>Amount</th><td>₹{$final_amount}</td></tr>
      
      " . (!empty($item_coupon) ? "<tr><th>Coupon Applied</th><td>{$item_coupon}</td></tr>" : "") . "
      " . (!empty($item_referral) ? "<tr><th>Referral</th><td>{$item_referral}</td></tr>" : "") . "
    </table>";
}

// 🟢 UPDATE 3: Show the Grand Total at the bottom
$body .= "
  <div class='total-block'>
     Grand Total Paid: ₹{$total_amount}
  </div>
";

// End HTML body
$body .= "
  <p>We look forward to hosting you.</p>
  <p><strong>— Team Vayuhu</strong></p>
</body>
</html>
";

// --- Setup PHPMailer ---
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'k24517165@gmail.com'; 
    $mail->Password   = 'ojnp mnka xorh mdch'; 
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('k24517165@gmail.com', 'Vayuhu Workspaces');
    $mail->addAddress($user_email);             
    $mail->addBCC('admin@vayuhu.com');          

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();
    echo json_encode(["success" => true, "message" => "Email sent successfully"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Mailer Error: {$mail->ErrorInfo}"]);
}
?>