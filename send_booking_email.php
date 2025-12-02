<?php
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
require 'vendor/autoload.php'; // Path to PHPMailer autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Read JSON ---
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(["success" => false, "message" => "Invalid input data"]);
    exit;
}

// --- Extract booking data ---
$user_id         = $input["user_id"] ?? null;
$user_email      = trim($input["user_email"] ?? "");
$workspace_title = trim($input["workspace_title"] ?? "");
$plan_type       = trim($input["plan_type"] ?? "");
$start_date      = trim($input["start_date"] ?? "");
$end_date        = trim($input["end_date"] ?? "");
$start_time      = trim($input["start_time"] ?? "");
$end_time        = trim($input["end_time"] ?? "");
$total_amount    = trim($input["total_amount"] ?? "");
$coupon_code     = trim($input["coupon_code"] ?? "");
$referral_source = trim($input["referral_source"] ?? "");

// --- Validation ---
if (empty($user_email) || empty($workspace_title) || empty($plan_type)) {
    echo json_encode(["success" => false, "message" => "Missing booking details."]);
    exit;
}

// --- Compose Email Content ---
$subject = "Your Booking Confirmation - Vayuhu Workspaces";
$body = "
<html>
<head><style>
body { font-family: Arial, sans-serif; color: #333; }
.table { border-collapse: collapse; width: 100%; margin-top: 10px; }
.table td, .table th { border: 1px solid #ddd; padding: 8px; }
.table th { background-color: #f97316; color: white; text-align: left; }
</style></head>
<body>
  <h2>Booking Confirmation</h2>
  <p>Dear Customer,</p>
  <p>Thank you for booking with <strong>Vayuhu Workspaces</strong>. Below are your booking details:</p>
  <table class='table'>
    <tr><th>Workspace</th><td>{$workspace_title}</td></tr>
    <tr><th>Plan Type</th><td>{$plan_type}</td></tr>
    <tr><th>Start Date</th><td>{$start_date}</td></tr>
    <tr><th>End Date</th><td>{$end_date}</td></tr>
    <tr><th>Start Time</th><td>{$start_time}</td></tr>
    <tr><th>End Time</th><td>{$end_time}</td></tr>
    <tr><th>Total Amount</th><td>₹{$total_amount}</td></tr>
    " . (!empty($coupon_code) ? "<tr><th>Coupon Code</th><td>{$coupon_code}</td></tr>" : "") . "
    " . (!empty($referral_source) ? "<tr><th>Referral Source</th><td>{$referral_source}</td></tr>" : "") . "
  </table>
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
    $mail->Host       = 'smtp.gmail.com'; // change to your mail server
    $mail->SMTPAuth   = true;
    $mail->Username   = 'k24517165@gmail.com'; // your email
    $mail->Password   = 'ojnp mnka xorh mdch'; // Gmail app password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('k24517165@gmail.com', 'Vayuhu Workspaces');
    $mail->addAddress($user_email);             // to user
    $mail->addBCC('admin@vayuhu.com');          // optional admin copy

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
