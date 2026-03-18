<?php
session_start();
header("Content-Type: application/json");
ini_set("display_errors", "0");
mysqli_report(MYSQLI_REPORT_OFF);

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

require_once __DIR__ . "/../student-lifecycle.php";
[$active, $expiryMsg] = enforce_student_not_expired($conn, (int)($_SESSION["user_id"] ?? 0));
if (!$active) {
    http_response_code(403);
    echo json_encode(["message" => $expiryMsg]);
    exit;
}

function fail_json($status, $message) {
    http_response_code($status);
    echo json_encode(["message" => $message]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$student_id = (int)$_SESSION["user_id"];
$company_id = (int)($data["company_id"] ?? 0);
$job_id = (int)($data["job_id"] ?? 0);

if ($company_id <= 0 || $job_id <= 0) {
    fail_json(400, "Invalid company or job selected");
}

$jobStmt = $conn->prepare("SELECT id, min_cgpa FROM jobs WHERE id = ? AND company_id = ?");
$jobQueryError = $conn->error;
if (!$jobStmt) {
    fail_json(500, "Unable to validate selected job. " . $jobQueryError);
}
$jobStmt->bind_param("ii", $job_id, $company_id);
$jobStmt->execute();
$jobResult = $jobStmt->get_result();

if ($jobResult->num_rows === 0) {
    fail_json(404, "Selected job is not available");
}
$job = $jobResult->fetch_assoc();
$min_cgpa = $job["min_cgpa"] !== null ? (float)$job["min_cgpa"] : null;

$hasScorecardColumn = false;
$scorecardColumnCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'ktu_scorecard_path'");
if ($scorecardColumnCheck && $scorecardColumnCheck->num_rows > 0) {
    $hasScorecardColumn = true;
}

$studentQuery = $hasScorecardColumn
    ? "SELECT cgpa, ktu_scorecard_path FROM students WHERE id = ?"
    : "SELECT cgpa, '' AS ktu_scorecard_path FROM students WHERE id = ?";

$studentStmt = $conn->prepare($studentQuery);
if (!$studentStmt) {
    fail_json(500, "Unable to load student profile.");
}
$studentStmt->bind_param("i", $student_id);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();

if ($studentResult->num_rows === 0) {
    fail_json(404, "Student profile not found");
}

$student = $studentResult->fetch_assoc();
$student_cgpa = (float)$student["cgpa"];
$scorecard_path = trim((string)($student["ktu_scorecard_path"] ?? ""));

if ($hasScorecardColumn && $scorecard_path === "") {
    fail_json(400, "Upload your KTU scorecard in Edit Profile before applying");
}

$resumeTableCheck = $conn->query("SHOW TABLES LIKE 'student_resumes'");
if (!$resumeTableCheck || $resumeTableCheck->num_rows === 0) {
    fail_json(500, "Resume module is not configured. Contact admin.");
}

$resumeStmt = $conn->prepare("
    SELECT id
    FROM student_resumes
    WHERE student_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 1
");
if (!$resumeStmt) {
    fail_json(500, "Unable to load your resume details.");
}
$resumeStmt->bind_param("i", $student_id);
$resumeStmt->execute();
$resumeResult = $resumeStmt->get_result();

if ($resumeResult->num_rows === 0) {
    fail_json(400, "Upload your resume before applying");
}

$resume = $resumeResult->fetch_assoc();
$resume_id = (int)$resume["id"];

if ($min_cgpa !== null && $student_cgpa < $min_cgpa) {
    fail_json(400, "Not eligible: minimum CGPA required is " . $min_cgpa);
}

$checkStmt = $conn->prepare("SELECT id FROM applications WHERE student_id = ? AND job_id = ?");
if (!$checkStmt) {
    fail_json(500, "Unable to validate existing application.");
}
$checkStmt->bind_param("ii", $student_id, $job_id);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    fail_json(409, "You already applied for this role");
}

$hasResumeIdColumn = false;
$resumeIdCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'resume_id'");
if ($resumeIdCheck && $resumeIdCheck->num_rows > 0) {
    $hasResumeIdColumn = true;
}

if ($hasResumeIdColumn) {
    $insertStmt = $conn->prepare("INSERT INTO applications (student_id, company_id, job_id, resume_id, status) VALUES (?, ?, ?, ?, 'Pending')");
    if (!$insertStmt) {
        fail_json(500, "Unable to submit application right now.");
    }
    $insertStmt->bind_param("iiii", $student_id, $company_id, $job_id, $resume_id);
} else {
    $insertStmt = $conn->prepare("INSERT INTO applications (student_id, company_id, job_id, status) VALUES (?, ?, ?, 'Pending')");
    if (!$insertStmt) {
        fail_json(500, "Unable to submit application right now.");
    }
    $insertStmt->bind_param("iii", $student_id, $company_id, $job_id);
}

if ($insertStmt->execute()) {
    echo json_encode(["message" => "Application submitted successfully"]);
} else {
    $stmtErr = trim((string)$insertStmt->error);
    $connErr = trim((string)$conn->error);
    $errText = $stmtErr !== "" ? $stmtErr : ($connErr !== "" ? $connErr : "Unknown DB error");
    fail_json(500, "Unable to submit application: " . $errText);
}
?>
