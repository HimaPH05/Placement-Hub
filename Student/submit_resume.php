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

$uploadDir = __DIR__ . "/../uploads/resumes";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$storedName = "resume_" . $student_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
$fullPath = $uploadDir . "/" . $storedName;
$relativePath = "uploads/resumes/" . $storedName;

if (!move_uploaded_file($file["tmp_name"], $fullPath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to save file"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO student_resumes (student_id, name, branch, gpa, about, skills, file_name, file_path, visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssssss", $student_id, $name, $branch, $gpa, $about, $skills, $file["name"], $relativePath, $visibility);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Resume submitted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to save resume details"]);
}
?>
