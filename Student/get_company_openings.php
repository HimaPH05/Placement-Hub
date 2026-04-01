<?php
session_start();
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized"]);
    exit;
}

$companyId = isset($_GET["company_id"]) ? (int)$_GET["company_id"] : 0;
if ($companyId <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid company id"]);
    exit;
}

require_once __DIR__ . "/../db-config.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

require_once __DIR__ . "/../student-lifecycle.php";
[$active, $expiryMsg] = enforce_student_not_expired($conn, (int)($_SESSION["user_id"] ?? 0));
if (!$active) {
    http_response_code(403);
    echo json_encode(["message" => $expiryMsg]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        j.id,
        j.job_title,
        j.job_description,
        j.openings,
        j.min_cgpa,
        j.location,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM applications a
                WHERE a.student_id = ? AND a.job_id = j.id
            ) THEN 1
            ELSE 0
        END AS is_applied
    FROM jobs j
    WHERE j.company_id = ? AND COALESCE(j.openings, 0) > 0
    ORDER BY j.created_at DESC, j.id DESC
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["message" => "Unable to load company openings"]);
    exit;
}
$studentId = (int)($_SESSION["user_id"] ?? 0);
$stmt->bind_param("ii", $studentId, $companyId);
$stmt->execute();
$result = $stmt->get_result();

$openings = [];
while ($row = $result->fetch_assoc()) {
    $row["id"] = (int)$row["id"];
    $row["openings"] = $row["openings"] !== null ? (int)$row["openings"] : 0;
    $row["min_cgpa"] = $row["min_cgpa"] !== null ? (float)$row["min_cgpa"] : null;
    $row["is_applied"] = ((int)($row["is_applied"] ?? 0)) === 1;
    $openings[] = $row;
}

echo json_encode(["openings" => $openings]);
?>
