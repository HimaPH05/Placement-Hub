<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student" || !isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Please login as student first"]);
    exit;
}

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$student_id = (int)$_SESSION["user_id"];
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

if (!isset($_FILES["resumeFile"]) || $_FILES["resumeFile"]["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Resume file is required"]);
    exit;
}

$file = $_FILES["resumeFile"];
$ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
$allowed = ["pdf", "doc", "docx"];

if (!in_array($ext, $allowed, true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Only PDF, DOC, DOCX allowed"]);
    exit;
}

$fileData = file_get_contents($file["tmp_name"]);
if ($fileData === false) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to read uploaded file"]);
    exit;
}

$mimeType = $file["type"] ?? "application/octet-stream";
$blob = null;

$stmt = $conn->prepare("INSERT INTO student_resumes (student_id, name, branch, gpa, about, skills, file_name, mime_type, file_data, visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssssssbs", $student_id, $name, $branch, $gpa, $about, $skills, $file["name"], $mimeType, $blob, $visibility);
$stmt->send_long_data(8, $fileData);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Resume submitted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to save resume details"]);
}
?>
