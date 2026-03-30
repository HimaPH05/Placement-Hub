<?php
session_start();
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

require_once __DIR__ . "/../db.php";

function get_count(mysqli $conn, string $table): int
{
    $safeTable = preg_replace("/[^a-zA-Z0-9_]/", "", $table);
    $sql = "SELECT COUNT(*) AS total FROM `$safeTable`";
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int)($row["total"] ?? 0);
}

$students = get_count($conn, "students");
$companies = get_count($conn, "companies");
$resumes = 0;
$publicResumeResult = $conn->query("SELECT COUNT(*) AS total FROM student_resumes WHERE visibility = 'public'");
if ($publicResumeResult) {
    $publicResumeRow = $publicResumeResult->fetch_assoc();
    $resumes = (int)($publicResumeRow["total"] ?? 0);
}

$shortlisted = 0;
$shortlistedResult = $conn->query("SELECT COUNT(*) AS total FROM applications WHERE status = 'Shortlisted'");
if ($shortlistedResult) {
    $shortlistedRow = $shortlistedResult->fetch_assoc();
    $shortlisted = (int)($shortlistedRow["total"] ?? 0);
}

$placements = 0;
$placementsResult = $conn->query("SELECT COUNT(*) AS total FROM applications WHERE status = 'Placed'");
if ($placementsResult) {
    $placementsRow = $placementsResult->fetch_assoc();
    $placements = (int)($placementsRow["total"] ?? 0);
}

echo json_encode([
    "success" => true,
    "students" => $students,
    "companies" => $companies,
    "resumes" => $resumes,
    "shortlisted" => $shortlisted,
    "placements" => $placements
]);
exit();
?>
