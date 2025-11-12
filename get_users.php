<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include "db.php";

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// ✅ Updated SQL to include all relevant columns
$sql = "SELECT 
            id, 
            name, 
            email, 
            phone, 
            dob, 
            address, 
            status, 
            comments, 
            details, 
            company, 
            created_at
        FROM users
        ORDER BY id DESC";

$result = $conn->query($sql);

$users = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Optional: Handle NULL values gracefully
        foreach ($row as $key => $value) {
            $row[$key] = $value ?? "";
        }
        $users[] = $row;
    }

    echo json_encode(["success" => true, "users" => $users]);
} else {
    echo json_encode(["success" => false, "message" => "No users found."]);
}

$conn->close();
?>
