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

if ($resume_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid resume selected"]);
    exit;
}

$del = $conn->prepare("DELETE FROM student_resumes WHERE id=? AND student_id=?");
$del->bind_param("ii", $resume_id, $student_id);

if ($del->execute() && $del->affected_rows > 0) {
    echo json_encode(["success" => true, "message" => "Resume removed successfully"]);
} else {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Resume not found"]);
}
?>
