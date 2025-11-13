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

$baseURL = "http://localhost/vayuhu_backend"; // adjust if folder name differs

// Remove 'comments' column from SQL
$sql = "SELECT 
            id, 
            name, 
            email, 
            phone, 
            dob, 
            address, 
            profile_pic,
            status, 
            details, 
            company, 
            created_at
        FROM users
        ORDER BY id DESC";

$result = $conn->query($sql);
$users = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Handle NULLs
        foreach ($row as $key => $value) {
            $row[$key] = $value ?? "";
        }

        // Convert relative image path → full URL
        if (!empty($row['profile_pic'])) {
            $row['profile_pic'] = $baseURL . '/' . $row['profile_pic'];
        }

        $users[] = $row;
    }

    echo json_encode(["success" => true, "users" => $users]);
} else {
    echo json_encode(["success" => false, "message" => "No users found."]);
}

$conn->close();
?>
