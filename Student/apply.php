<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student" || !isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["message" => "Please login as student first"]);
    exit;
}

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$student_id = (int)$_SESSION["user_id"];
$company_id = (int)($data["company_id"] ?? 0);
$job_id = (int)($data["job_id"] ?? 0);

if ($company_id <= 0 || $job_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid company or job selected"]);
    exit;
}

$jobStmt = $conn->prepare("SELECT id FROM jobs WHERE id = ? AND company_id = ?");
$jobStmt->bind_param("ii", $job_id, $company_id);
$jobStmt->execute();
$jobStmt->store_result();

if ($jobStmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["message" => "Selected job is not available"]);
    exit;
}

$checkStmt = $conn->prepare("SELECT id FROM applications WHERE student_id = ? AND job_id = ?");
$checkStmt->bind_param("ii", $student_id, $job_id);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(["message" => "You already applied for this role"]);
    exit;
}

$insertStmt = $conn->prepare("INSERT INTO applications (student_id, company_id, job_id, status) VALUES (?, ?, ?, 'Pending')");
$insertStmt->bind_param("iii", $student_id, $company_id, $job_id);

if ($insertStmt->execute()) {
    echo json_encode(["message" => "Application submitted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Unable to submit application"]);
}
?>
