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

$baseURL = "http://localhost/vayuhu_backend";
$today = date("Y-m-d");

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

        foreach ($row as $k => $v) {
            $row[$k] = $v ?? "";
        }

        // Full image path
        $row["image_url"] = !empty($row["image"])
            ? $baseURL . "/" . ltrim($row["image"], "/")
            : "";

        $spaceId = (int)$row["id"];

        // --------------------------------------
        // ✅ CHECK IF THIS SPACE IS BOOKED NOW OR FUTURE
        // --------------------------------------
        // Same booking table you used in add_workspace_booking.php
        // If any record overlaps with today or later, we mark unavailable
        $checkSql = "
            SELECT 
                start_date, 
                end_date, 
                plan_type 
            FROM workspace_bookings 
            WHERE space_id = $spaceId
              AND status IN ('Confirmed', 'Pending')
              AND (
                    (plan_type = 'hourly'  AND start_date >= '$today')
                 OR (plan_type = 'daily'   AND start_date >= '$today')
                 OR (plan_type = 'monthly' AND end_date >= '$today')
                  )
            ORDER BY start_date ASC
            LIMIT 1
        ";

        $bookedRow = null;
        $isAvailable = true;
        $res2 = $conn->query($checkSql);
        if ($res2 && $res2->num_rows > 0) {
            $bookedRow = $res2->fetch_assoc();
            $isAvailable = false;
        }

        // --------------------------------------
        // Mark is_available and attach details
        // --------------------------------------
        if ($row["status"] !== "Active") {
            $row["is_available"] = false;
            $row["availability_reason"] = "Space inactive";
        } elseif (!$isAvailable) {
            $row["is_available"] = false;
            $row["availability_reason"] = "Booked until " . $bookedRow["end_date"];
        } else {
            $row["is_available"] = true;
            $row["availability_reason"] = "Available for booking";
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
