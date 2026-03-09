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

$data = json_decode(file_get_contents("php://input"), true);
$resume_id = (int)($data["resume_id"] ?? 0);
$student_id = (int)$_SESSION["user_id"];
$username = trim($_SESSION["username"] ?? "");

if ($resume_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid resume selected"]);
    exit;
}

$del = $conn->prepare("
    DELETE sr
    FROM student_resumes sr
    LEFT JOIN students s ON s.id = sr.student_id
    WHERE sr.id=?
      AND (
            sr.student_id=?
         OR (s.username IS NOT NULL AND s.username=?)
      )
");
$del->bind_param("iis", $resume_id, $student_id, $username);

if ($del->execute() && $del->affected_rows > 0) {
    echo json_encode(["success" => true, "message" => "Resume removed successfully"]);
} else {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Resume not found"]);
}
?>
