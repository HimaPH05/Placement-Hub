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

$payload = json_decode(file_get_contents("php://input"), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$student_id = (int)$_SESSION["user_id"];
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

$stmt = $conn->prepare("UPDATE student_resumes SET visibility = ? WHERE id = ? AND student_id = ?");
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
