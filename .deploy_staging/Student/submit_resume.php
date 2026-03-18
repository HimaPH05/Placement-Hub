<?php
session_start();
header("Content-Type: application/json");
mysqli_report(MYSQLI_REPORT_OFF);

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

$student_id = $lifeStudentId;
$name = trim($_POST["name"] ?? "");
$branch = trim($_POST["branch"] ?? "");
$gpa = trim($_POST["gpa"] ?? "");
$about = trim($_POST["about"] ?? "");
$skills = trim($_POST["skills"] ?? "");
$visibility = trim($_POST["visibility"] ?? "private");

if ($visibility !== "public" && $visibility !== "private") {
    $visibility = "private";
}

if ($name === "" || $branch === "" || $gpa === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Name, Branch and GPA are required"]);
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

$resumeQuery = $conn->prepare("SELECT id, file_name, mime_type, file_data FROM student_resumes WHERE student_id = ? ORDER BY created_at DESC, id DESC");
$resumeQuery->bind_param("i", $student_id);
$resumeQuery->execute();
$resumeResult = $resumeQuery->get_result();

$resumeIds = [];
$latestResume = null;
while ($row = $resumeResult->fetch_assoc()) {
    $id = (int)$row["id"];
    $resumeIds[] = $id;
    if ($latestResume === null) {
        $latestResume = $row;
    }
}

$hasUpload = isset($_FILES["resumeFile"]) && $_FILES["resumeFile"]["error"] === UPLOAD_ERR_OK;
$allowed = ["pdf", "doc", "docx"];

$fileName = $latestResume["file_name"] ?? "";
$mimeType = $latestResume["mime_type"] ?? "application/octet-stream";
$fileData = $latestResume["file_data"] ?? null;

if ($hasUpload) {
    $file = $_FILES["resumeFile"];
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Only PDF, DOC, DOCX allowed"]);
        exit;
    }

    $newFileData = file_get_contents($file["tmp_name"]);
    if ($newFileData === false) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Unable to read uploaded file"]);
        exit;
    }

    $fileName = $file["name"];
    $mimeType = $file["type"] ?? "application/octet-stream";
    $fileData = $newFileData;
}

if (($fileData === null || $fileData === "") && $latestResume === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Resume file is required"]);
    exit;
}

$blob = null;

if ($latestResume !== null) {
    $statusResetSql = "";
    if ($hasVerifyColumns) {
        $statusResetSql .= ", is_verified = 0, verified_at = NULL";
    }
    if ($hasRejectColumns) {
        $statusResetSql .= ", is_rejected = 0, rejected_at = NULL";
    }

    $update = $conn->prepare("UPDATE student_resumes SET name = ?, branch = ?, gpa = ?, about = ?, skills = ?, file_name = ?, mime_type = ?, file_data = ?, visibility = ?{$statusResetSql} WHERE id = ? AND student_id = ?");
    $latestId = (int)$latestResume["id"];
    $update->bind_param("sssssssbsii", $name, $branch, $gpa, $about, $skills, $fileName, $mimeType, $blob, $visibility, $latestId, $student_id);
    $update->send_long_data(7, $fileData);

    if (!$update->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Unable to update resume details"]);
        exit;
    }

    if (count($resumeIds) > 1) {
        $extraIds = array_slice($resumeIds, 1);
        $placeholders = implode(",", array_fill(0, count($extraIds), "?"));
        $types = str_repeat("i", count($extraIds) + 1);
        $params = array_merge([$student_id], $extraIds);
        $cleanup = $conn->prepare("DELETE FROM student_resumes WHERE student_id = ? AND id IN ($placeholders)");
        $bindArgs = [$types];
        foreach ($params as $k => $v) {
            $bindArgs[] = &$params[$k];
        }
        call_user_func_array([$cleanup, "bind_param"], $bindArgs);
        $cleanup->execute();
    }

    echo json_encode(["success" => true, "message" => "Resume updated successfully"]);
    exit;
}

$insert = $conn->prepare("INSERT INTO student_resumes (student_id, name, branch, gpa, about, skills, file_name, mime_type, file_data, visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$insert->bind_param("isssssssbs", $student_id, $name, $branch, $gpa, $about, $skills, $fileName, $mimeType, $blob, $visibility);
$insert->send_long_data(8, $fileData);

if ($insert->execute()) {
    echo json_encode(["success" => true, "message" => "Resume submitted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to save resume details"]);
}
?>
