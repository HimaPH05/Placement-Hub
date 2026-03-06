<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header("Content-Type: application/json");

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

$username = trim($data["username"] ?? "");
$password = trim($data["password"] ?? "");
$role     = trim($data["role"] ?? "");

if (empty($username) || empty($password) || empty($role)) {
    echo json_encode(["message" => "All fields are required"]);
    exit;
}

/* ================= STUDENT LOGIN ================= */
if ($role === "student") {

    $stmt = $conn->prepare("SELECT id, username, password FROM students WHERE username = ?");
    $stmt->bind_param("s", $username);
}

/* ================= COMPANY LOGIN ================= */
else if ($role === "company") {

    $stmt = $conn->prepare("SELECT id, username, password FROM companies WHERE username = ?");
    $stmt->bind_param("s", $username);
}
else {
    echo json_encode(["message" => "Invalid role selected"]);
    exit;
}

$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(["message" => "Invalid username or password"]);
    exit;
}

$stmt->bind_result($id, $db_username, $db_password);
$stmt->fetch();

/* PASSWORD VERIFY */
if (!password_verify($password, $db_password)) {
    echo json_encode(["message" => "Invalid username or password"]);
    exit;
}

/* CREATE SESSION */
$_SESSION["user_id"]  = $id;
$_SESSION["username"] = $db_username;
$_SESSION["role"]     = $role;
if ($role === "company") {
    $_SESSION["company_id"] = $id;
}

echo json_encode([
    "message" => "Login successful",
    "role" => $role
]);

exit;
?>
