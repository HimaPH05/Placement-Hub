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

$conn->query("CREATE TABLE IF NOT EXISTS student_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    username VARCHAR(120) NOT NULL,
    feedback TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_created (student_id, created_at)
)");

$data = json_decode(file_get_contents("php://input"), true);
$feedback = trim((string)($data["feedback"] ?? ""));

if ($feedback === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Feedback cannot be empty"]);
    exit;
}

$student_id = (int)$_SESSION["user_id"];
$username = trim((string)($_SESSION["username"] ?? ""));
if ($username === "") {
    $username = "student_" . $student_id;
}

$stmt = $conn->prepare("INSERT INTO student_feedback (student_id, username, feedback) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $student_id, $username, $feedback);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Feedback submitted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to submit feedback"]);
}
?>
