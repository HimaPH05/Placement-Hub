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

$conn = new mysqli("localhost", "root", "", "detailsdb");
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
    SELECT id, job_title, job_description, openings, min_cgpa, location
    FROM jobs
    WHERE company_id = ? AND COALESCE(openings, 0) > 0
    ORDER BY created_at DESC, id DESC
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$result = $stmt->get_result();

$openings = [];
while ($row = $result->fetch_assoc()) {
    $row["id"] = (int)$row["id"];
    $row["openings"] = $row["openings"] !== null ? (int)$row["openings"] : 0;
    $row["min_cgpa"] = $row["min_cgpa"] !== null ? (float)$row["min_cgpa"] : null;
    $openings[] = $row;
}

echo json_encode(["openings" => $openings]);
?>
