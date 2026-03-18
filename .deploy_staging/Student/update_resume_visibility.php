<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student" || !isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Please login as student first"]);
    exit;
}

require_once __DIR__ . "/../db-config.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$lifeStudentId = (int)$_SESSION["user_id"];
require_once __DIR__ . "/../student-lifecycle.php";
[$active, $expiryMsg] = enforce_student_not_expired($conn, $lifeStudentId);
if (!$active) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => $expiryMsg]);
    exit;
}

$payload = json_decode(file_get_contents("php://input"), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$student_id = $lifeStudentId;
$resume_id = (int)($payload["resume_id"] ?? 0);
$visibility = strtolower(trim((string)($payload["visibility"] ?? "")));

if ($resume_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid resume selected"]);
    exit;
}

if ($visibility !== "public" && $visibility !== "private") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid visibility option"]);
    exit;
}

$hasVerifyColumns = false;
$verifyColCheck = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_verified'");
if ($verifyColCheck && $verifyColCheck->num_rows > 0) {
    $hasVerifyColumns = true;
}
$hasRejectColumns = false;
$rejectColCheck = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_rejected'");
if ($rejectColCheck && $rejectColCheck->num_rows > 0) {
    $hasRejectColumns = true;
}

$statusResetSql = "";
if ($visibility === "public") {
    if ($hasVerifyColumns) {
        $statusResetSql .= ", is_verified = 0, verified_at = NULL";
    }
    if ($hasRejectColumns) {
        $statusResetSql .= ", is_rejected = 0, rejected_at = NULL";
    }
}

$stmt = $conn->prepare("UPDATE student_resumes SET visibility = ?{$statusResetSql} WHERE id = ? AND student_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to update resume visibility"]);
    exit;
}

$stmt->bind_param("sii", $visibility, $resume_id, $student_id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    $check = $conn->prepare("SELECT id FROM student_resumes WHERE id = ? AND student_id = ? LIMIT 1");
    $check->bind_param("ii", $resume_id, $student_id);
    $check->execute();
    $exists = $check->get_result();

    if (!$exists || $exists->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Resume not found"]);
        exit;
    }
}

echo json_encode([
    "success" => true,
    "message" => "Resume visibility updated",
    "resume_id" => $resume_id,
    "visibility" => $visibility
]);
?>
