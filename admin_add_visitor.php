<?php
// 1. SILENCE HTML ERRORS (Fixes the "<" syntax error)
error_reporting(E_ALL);
ini_set('display_errors', 0); 

// --- CORS Configuration ---
$allowed_origin = "http://localhost:5173"; 
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // 2. CHECK DATABASE CONNECTION
    if (!file_exists('db.php')) {
        throw new Exception("db.php file not found!");
    }
    require_once 'db.php';

    // 3. GET INPUT
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("No JSON data received.");
    }

    if (empty($data['name']) || empty($data['contact'])) {
        throw new Exception("Name and Contact are required.");
    }

    // 4. PREPARE VARIABLES
    $name = $data['name'];
    $contact = $data['contact'];
    
    // Handle NULLs for IDs
    $user_id = !empty($data['user_id']) ? $data['user_id'] : null;
    $admin_id = !empty($data['admin_id']) ? $data['admin_id'] : null;

    $email = $data['email'] ?? null;
    $company_name = $data['company_name'] ?? null;
    $visiting_date = $data['visiting_date'] ?? null;
    $visiting_time = $data['visiting_time'] ?? null;
    $reason = $data['reason'] ?? null;

    // 5. CHECK IF admin_id COLUMN EXISTS (Optional safety check)
    // If your table doesn't have 'admin_id' yet, this query will fail. 
    // Make sure you have the 'admin_id' column or remove it from this query.
    
    $sql = "INSERT INTO visitors (user_id, admin_id, name, contact_no, email, company_name, visiting_date, visiting_time, reason) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    if(!$stmt) {
        throw new Exception("SQL Prepare Failed: " . $conn->error);
    }

    // i = int, s = string (iisssssss)
    $stmt->bind_param("iisssssss", $user_id, $admin_id, $name, $contact, $email, $company_name, $visiting_date, $visiting_time, $reason);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Visitor added successfully"]);
    } else {
        throw new Exception("Database Error: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // 6. RETURN ERROR AS JSON (Not HTML)
    echo json_encode([
        "success" => false, 
        "message" => "Server Error: " . $e->getMessage()
    ]);
}
?>