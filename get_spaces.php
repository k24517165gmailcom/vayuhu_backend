<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once "db.php";

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$baseURL = "http://localhost/vayuhu_backend"; // Change if needed

// -------------------------
// FETCH ALL SPACES
// -------------------------
$sql = "SELECT 
            id,
            space_code,
            space,
            per_hour,
            per_day,
            one_week,
            two_weeks,
            three_weeks,
            per_month,
            min_duration,
            min_duration_desc,
            max_duration,
            max_duration_desc,
            image,
            status,
            created_at
        FROM spaces
        ORDER BY id DESC";

$result = $conn->query($sql);
$spaces = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Convert NULL → ""
        foreach ($row as $k => $v) {
            $row[$k] = $v ?? "";
        }

        // Convert image path → full URL
        if (!empty($row["image"])) {
            $row["image_url"] = $baseURL . "/" . ltrim($row["image"], "/");
        } else {
            $row["image_url"] = "";
        }

        $spaces[] = $row;
    }

    echo json_encode([
        "success" => true,
        "spaces" => $spaces
    ]);
} else {
    echo json_encode(["success" => false, "message" => "No spaces found"]);
}

$conn->close();
?>
