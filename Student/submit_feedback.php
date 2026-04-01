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

$conn->query("CREATE TABLE IF NOT EXISTS student_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    username VARCHAR(120) NOT NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    feedback TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_created (student_id, created_at)
)");

$ratingColumnCheck = $conn->query("SHOW COLUMNS FROM student_feedback LIKE 'rating'");
if ($ratingColumnCheck && $ratingColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE student_feedback ADD COLUMN rating TINYINT NOT NULL DEFAULT 5 AFTER username");
}

$data = json_decode(file_get_contents("php://input"), true);
$feedback = trim((string)($data["feedback"] ?? ""));
$rating = (int)($data["rating"] ?? 0);

if ($feedback === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Feedback cannot be empty"]);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Please select a valid rating"]);
    exit;
}

$student_id = (int)$_SESSION["user_id"];
$username = trim((string)($_SESSION["username"] ?? ""));
if ($username === "") {
    $username = "student_" . $student_id;
}

$stmt = $conn->prepare("INSERT INTO student_feedback (student_id, username, rating, feedback) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isis", $student_id, $username, $rating, $feedback);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Feedback submitted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to submit feedback"]);
}
?>
