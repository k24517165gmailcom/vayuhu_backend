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

        // Ensure null values are replaced with empty strings
        foreach ($row as $k => $v) {
            $row[$k] = $v ?? "";
        }

        // Construct full image path
        $row["image_url"] = !empty($row["image"])
            ? $baseURL . "/" . ltrim($row["image"], "/")
            : "";

        $spaceId = (int)$row["id"];

        // --------------------------------------
// ✅ CHECK IF THIS SPACE IS BOOKED NOW OR FUTURE (hourly time-sensitive)
// --------------------------------------
$checkSql = "
    SELECT 
        start_date, 
        end_date, 
        start_time,
        end_time,
        plan_type 
    FROM workspace_bookings 
    WHERE space_id = ? 
      AND status IN ('Confirmed', 'Pending')
      AND (
            (plan_type = 'hourly'  AND start_date = ?)
         OR (plan_type = 'daily'   AND start_date >= ?)
         OR (plan_type = 'monthly' AND end_date >= ?)
          )
    ORDER BY start_date ASC
    LIMIT 1
";

$stmt = $conn->prepare($checkSql);
if ($stmt) {
    $stmt->bind_param("isss", $spaceId, $today, $today, $today);
    $stmt->execute();
    $res2 = $stmt->get_result();
} else {
    $res2 = false;
}

$bookedRow = null;
$isAvailable = true;

if ($res2 && $res2->num_rows > 0) {
    $bookedRow = $res2->fetch_assoc();

    // ✅ If hourly booking, check current time
    if ($bookedRow["plan_type"] === "hourly") {
        $now = new DateTime("now");
        $bookingDate = new DateTime($bookedRow["start_date"]);
        $startTime = DateTime::createFromFormat("H:i", $bookedRow["start_time"]);
        $endTime   = DateTime::createFromFormat("H:i", $bookedRow["end_time"]);

        if ($bookingDate->format("Y-m-d") === $now->format("Y-m-d")) {
            // Merge date + time to full DateTime objects
            $bookingStart = new DateTime($bookedRow["start_date"] . " " . $bookedRow["start_time"]);
            $bookingEnd   = new DateTime($bookedRow["start_date"] . " " . $bookedRow["end_time"]);

            if ($now >= $bookingStart && $now <= $bookingEnd) {
                // Still within booked time → mark unavailable
                $isAvailable = false;
            }
        } elseif ($bookingDate > $now) {
            // Future hourly booking → still block it
            $isAvailable = false;
        }
    } else {
        // Daily or Monthly → block as before
        $isAvailable = true; // default
        $endDate = new DateTime($bookedRow["end_date"]);
        if ($endDate >= new DateTime($today)) {
            $isAvailable = false;
        }
    }
}

        // --------------------------------------
        // Mark is_available and attach reason
        // --------------------------------------
        if ($row["status"] !== "Active") {
            $row["is_available"] = false;
            $row["availability_reason"] = "Space inactive";
        } elseif (!$isAvailable) {
            $endDateText = isset($bookedRow["end_date"]) ? $bookedRow["end_date"] : "Unknown date";
            $row["is_available"] = false;
            $row["availability_reason"] = "Booked until " . $endDateText;
        } else {
            $row["is_available"] = true;
            $row["availability_reason"] = "Available for booking";
        }

        // Close prepared statement (good habit)
        if (isset($stmt)) {
            $stmt->close();
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

// Close DB connection
$conn->close();
?>
