<?php
header("Content-Type: application/json");
session_start();

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["message" => "Invalid request"]);
    exit;
}

$username = trim((string)($data["username"] ?? ""));
$password = trim((string)($data["password"] ?? ""));

if ($username === "" || $password === "") {
    echo json_encode(["message" => "Username and password are required"]);
    exit;
}

$colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email_verify_token_hash'");
if (!$colCheck || $colCheck->num_rows === 0) {
    echo json_encode(["message" => "Email verification is not enabled in the database yet. Run database setup once."]);
    exit;
}

$stmt = $conn->prepare("SELECT id, email, fullname, password, COALESCE(is_email_verified, 0) AS is_email_verified FROM students WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(["message" => "Student account not found"]);
    exit;
}

if (!password_verify($password, (string)$row["password"])) {
    echo json_encode(["message" => "Invalid username or password"]);
    exit;
}

if (((int)$row["is_email_verified"]) === 1) {
    echo json_encode(["message" => "Email already verified. You can login now."]);
    exit;
}

$tokenBytes = random_bytes(32);
$token = rtrim(strtr(base64_encode($tokenBytes), "+/", "-_"), "=");
$tokenHash = hash("sha256", $token);
$expiresAt = (new DateTimeImmutable("+24 hours"))->format("Y-m-d H:i:s");

$studentId = (int)$row["id"];
$u = $conn->prepare("UPDATE students SET email_verify_token_hash = ?, email_verify_expires_at = ? WHERE id = ?");
$u->bind_param("ssi", $tokenHash, $expiresAt, $studentId);
$u->execute();
$u->close();

$scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
$host = $_SERVER["HTTP_HOST"] ?? "localhost";
$basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
$verifyUrl = "{$scheme}://{$host}{$basePath}/verify-email.php?token=" . urlencode($token);

require_once __DIR__ . "/email.php";
$subject = "Verify your Placement Hub email";
$body = "Hi " . (string)$row["fullname"] . ",\n\n";
$body .= "Please verify your email to activate your Placement Hub student account:\n\n";
$body .= $verifyUrl . "\n\n";
$body .= "This link expires in 24 hours.\n";

[$sent, $note] = send_email((string)$row["email"], $subject, $body);

echo json_encode([
    "message" => $sent ? "Verification email sent. Please check your inbox." : "Verification email could not be sent. Please contact placement cell.",
    "mail_note" => $sent ? "" : $note
]);

