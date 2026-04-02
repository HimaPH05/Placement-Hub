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

require_once __DIR__ . "/../db-config.php";
require_once __DIR__ . "/../profile-helpers.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}
include_once __DIR__ . "/../database_setup.php";

require_once __DIR__ . "/../student-lifecycle.php";
[$active, $expiryMsg] = enforce_student_not_expired($conn, (int)($_SESSION["user_id"] ?? 0));
if (!$active) {
    http_response_code(403);
    echo json_encode(["message" => $expiryMsg]);
    exit;
}

$sql = "
    SELECT
        c.id,
        c.companyName AS name,
        COALESCE(c.description, 'No description available.') AS `desc`,
        COALESCE(c.industry, 'N/A') AS industry,
        COALESCE(c.location, 'N/A') AS location,
        COALESCE(c.website, '') AS website,
        COALESCE(c.profile_photo_path, '') AS profile_photo_path,
        j.id AS latest_job_id,
        j.job_title AS latest_job_title,
        j.min_cgpa AS latest_job_min_cgpa,
        CASE
            WHEN j.id IS NOT NULL AND EXISTS (
                SELECT 1
                FROM applications a
                WHERE a.student_id = " . (int)($_SESSION["user_id"] ?? 0) . " AND a.job_id = j.id
            ) THEN 1
            ELSE 0
        END AS latest_job_applied
    FROM companies c
    LEFT JOIN jobs j ON j.id = (
        SELECT j2.id
        FROM jobs j2
        WHERE j2.company_id = c.id
        ORDER BY j2.created_at DESC, j2.id DESC
        LIMIT 1
    )
    ORDER BY c.companyName ASC
";

$result = $conn->query($sql);
$companies = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row["id"] = (int)$row["id"];
        $row["latest_job_id"] = $row["latest_job_id"] !== null ? (int)$row["latest_job_id"] : null;
        $row["latest_job_applied"] = ((int)($row["latest_job_applied"] ?? 0)) === 1;
        $row["photo_url"] = placementhub_company_photo_url($row, "../");
        $companies[] = $row;
    }
}

echo json_encode(["companies" => $companies]);
?>
