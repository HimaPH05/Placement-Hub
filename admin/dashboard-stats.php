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

$conn->query("CREATE TABLE IF NOT EXISTS admin_settings (
    `key_name` VARCHAR(100) PRIMARY KEY,
    `value_text` VARCHAR(255) NOT NULL
)");

$students = get_count($conn, "students");
$companies = get_count($conn, "companies");
$resumes = get_count($conn, "student_resumes");

$placements = 0;
$stmt = $conn->prepare("SELECT value_text FROM admin_settings WHERE key_name = 'placements_count' LIMIT 1");
if ($stmt && $stmt->execute()) {
    $stmt->bind_result($valueText);
    if ($stmt->fetch()) {
        $placements = max(0, (int)$valueText);
    }
}

echo json_encode([
    "success" => true,
    "students" => $students,
    "companies" => $companies,
    "resumes" => $resumes,
    "placements" => $placements
]);
exit();
?>
