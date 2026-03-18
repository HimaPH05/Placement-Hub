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

    $hasEmailVerify = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'is_email_verified'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasEmailVerify = true;
    }

    if ($hasEmailVerify) {
        $stmt = $conn->prepare("SELECT id, username, password, email, regno, is_email_verified FROM students WHERE username = ?");
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, email, regno FROM students WHERE username = ?");
    }
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

if ($role === "student") {
    if (!isset($hasEmailVerify) || $hasEmailVerify !== true) {
        $stmt->bind_result($id, $db_username, $db_password, $db_email, $db_regno);
        $db_is_email_verified = 1;
    } else {
        $stmt->bind_result($id, $db_username, $db_password, $db_email, $db_regno, $db_is_email_verified);
    }
} else {
    $stmt->bind_result($id, $db_username, $db_password);
    $db_email = "";
    $db_regno = "";
}

$stmt->fetch();

/* PASSWORD VERIFY */
if (!password_verify($password, $db_password)) {
    echo json_encode(["message" => "Invalid username or password"]);
    exit;
}

if ($role === "student") {
    require_once __DIR__ . "/access-policy.php";
    [$allowed, $policyMsg] = enforce_student_access_policy(
        $conn,
        trim((string)$db_regno),
        trim((string)$db_email)
    );
    if (!$allowed) {
        echo json_encode(["message" => $policyMsg]);
        exit;
    }

    if (((int)$db_is_email_verified) !== 1) {
        echo json_encode([
            "message" => "Email not verified. Please verify your email before login.",
            "code" => "EMAIL_NOT_VERIFIED"
        ]);
        exit;
    }

    require_once __DIR__ . "/student-lifecycle.php";
    [$active, $expiryMsg] = enforce_student_not_expired($conn, (int)$id);
    if (!$active) {
        echo json_encode([
            "message" => $expiryMsg,
            "code" => "STUDENT_ACCESS_EXPIRED"
        ]);
        exit;
    }
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
