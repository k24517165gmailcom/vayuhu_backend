<?php
// ✅ Show PHP errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ✅ Allow React Frontend (CORS)
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include 'db.php'; // ensure correct path

    // ✅ Parse JSON input
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        throw new Exception("Invalid or missing JSON input.");
    }

    // ✅ Extract fields safely
    $start_date  = $conn->real_escape_string($data['start_date'] ?? '');
    $years       = (int)($data['years'] ?? 0);
    $user_id     = (int)($data['user_id'] ?? 0); // optional, if available

    // ✅ Validation
    if (empty($start_date) || $years <= 0) {
        throw new Exception("Start date and years are required.");
    }

    // ✅ Calculate end_date (start_date + years)
    $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $years . ' years'));

    // ✅ Prepare SQL
    $stmt = $conn->prepare("INSERT INTO virtualoffice_bookings (user_id, start_date, years, end_date) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("isis", $user_id, $start_date, $years, $end_date);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Virtual Office booking saved successfully."
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
