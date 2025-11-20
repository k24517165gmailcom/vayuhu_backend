<?php
// bulk_generate_spaces.php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "db.php";
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// read JSON body
$body = json_decode(file_get_contents('php://input'), true);
$group = isset($body['group']) ? trim($body['group']) : '';

if (!$group) {
    echo json_encode(['success' => false, 'message' => 'Missing group parameter']);
    exit;
}

// mapping — keep this synced with frontend SPACE_GROUPS
$groups = [
    "Workspace" => ['prefix' => 'WS', 'max' => 45],
    "Team Leads Cubicle" => ['prefix' => 'TLC', 'max' => 4],
    "Manager Cubicle" => ['prefix' => 'MC', 'max' => 2],
    "Video Conferencing" => ['prefix' => 'VC', 'max' => 1],
    "Executive Cabin" => ['prefix' => 'EC', 'max' => 2],
    "CEO Cabin" => ['prefix' => 'CD', 'max' => 1],
];

if (!isset($groups[$group])) {
    echo json_encode(['success' => false, 'message' => 'Invalid group']);
    exit;
}

$prefix = $groups[$group]['prefix'];
$max = (int)$groups[$group]['max'];

// fetch existing codes for this prefix
$stmt = $conn->prepare("SELECT space_code FROM spaces WHERE space_code LIKE CONCAT(?, '%')");
$likePrefix = $prefix;
$stmt->bind_param("s", $likePrefix);
$stmt->execute();
$res = $stmt->get_result();
$existing = [];
while ($r = $res->fetch_assoc()) {
    $existing[] = $r['space_code'];
}
$stmt->close();

// prepare list of all desired codes
$allDesired = [];
for ($i = 1; $i <= $max; $i++) {
    $code = $prefix . str_pad($i, 2, "0", STR_PAD_LEFT);
    $allDesired[] = $code;
}

// decide which to create
$toCreate = array_values(array_diff($allDesired, $existing));
$skipped = array_values(array_intersect($allDesired, $existing));

if (empty($toCreate)) {
    echo json_encode([
        'success' => true,
        'message' => 'All codes already exist',
        'created_count' => 0,
        'skipped_count' => count($skipped),
        'created_codes' => [],
        'skipped_codes' => $skipped
    ]);
    exit;
}

// Begin transaction
$conn->begin_transaction();

$created = [];
$error = null;

$insertSql = "INSERT INTO spaces
    (space_code, space, per_hour, per_day, one_week, two_weeks, three_weeks, per_month, min_duration, min_duration_desc, max_duration, max_duration_desc, image, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
$stmtInsert = $conn->prepare($insertSql);

if (!$stmtInsert) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

// We'll insert minimal defaults: space (group name + number), numeric fields empty (NULL),
// image empty string, status = Active
foreach ($toCreate as $code) {
    $spaceName = $group; // you can adjust; e.g., "Workspace" or "Workspace - WS01"
    // Default values - use empty strings so DB will cast appropriately; change if you prefer NULL
    $per_hour = ""; $per_day = ""; $one_week = ""; $two_weeks = ""; $three_weeks = ""; $per_month = "";
    $min_duration = ""; $min_duration_desc = ""; $max_duration = ""; $max_duration_desc = "";
    $image = ""; $status = "Active";

    // bind as strings — MySQL will convert types if columns are numeric
    if (!$stmtInsert->bind_param(
        "ssssssssssssss",
        $code,
        $spaceName,
        $per_hour,
        $per_day,
        $one_week,
        $two_weeks,
        $three_weeks,
        $per_month,
        $min_duration,
        $min_duration_desc,
        $max_duration,
        $max_duration_desc,
        $image,
        $status
    )) {
        $error = "Bind failed: " . $stmtInsert->error;
        break;
    }

    if (!$stmtInsert->execute()) {
        // if duplicate (race), skip and continue or abort depending on your policy
        // here we abort and rollback to maintain atomicity
        $error = "Insert failed for {$code}: " . $stmtInsert->error;
        break;
    } else {
        $created[] = $code;
    }
}

if ($error) {
    $conn->rollback();
    $stmtInsert->close();
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
} else {
    $conn->commit();
    $stmtInsert->close();
    echo json_encode([
        'success' => true,
        'message' => 'Bulk generation completed',
        'created_count' => count($created),
        'skipped_count' => count($skipped),
        'created_codes' => $created,
        'skipped_codes' => $skipped
    ]);
    exit;
}
?>
