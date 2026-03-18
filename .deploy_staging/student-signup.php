<?php
header("Content-Type: application/json");

require_once __DIR__ . "/db-config.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);

if ($conn->connect_error) {
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

$username = trim($data->username ?? '');
$email    = trim($data->email ?? '');
$password = trim($data->password ?? '');
$fullname = trim($data->fullname ?? '');
$regno    = trim($data->regno ?? '');
$admissionDateRaw = trim((string)($data->admission_date ?? ''));
$dob      = trim($data->dob ?? '');
$cgpa     = trim($data->cgpa ?? '');

if ($username === "" || $email === "" || $password === "" || $fullname === "" || $regno === "" || $admissionDateRaw === "" || $dob === "" || $cgpa === "") {
    echo json_encode(["message" => "All fields are required"]);
    exit;
}

require_once __DIR__ . "/student-lifecycle.php";
$admissionDate = DateTimeImmutable::createFromFormat("Y-m-d", $admissionDateRaw);
if (!($admissionDate instanceof DateTimeImmutable)) {
    echo json_encode(["message" => "Invalid date of joining"]);
    exit;
}
$admissionDateStr = $admissionDate->format("Y-m-d");
$accessExpiresAt = $admissionDate->modify("+4 years")->format("Y-m-d");

require_once __DIR__ . "/access-policy.php";
[$allowed, $policyMsg] = enforce_student_access_policy($conn, $regno, $email);
if (!$allowed) {
    echo json_encode(["message" => $policyMsg]);
    exit;
}

$check = $conn->prepare("SELECT id FROM students WHERE username=? OR regno=? OR email=?");
$check->bind_param("sss", $username, $regno, $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["message" => "Username, Email, or Register Number already exists"]);
    exit;
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

$hasLifecycleCols = false;
$lifeColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'access_expires_at'");
if ($lifeColCheck && $lifeColCheck->num_rows > 0) {
    $hasLifecycleCols = true;
}

if ($hasLifecycleCols) {
    $hasAdmissionDateCol = false;
    $adColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'admission_date'");
    if ($adColCheck && $adColCheck->num_rows > 0) {
        $hasAdmissionDateCol = true;
    }

    $stmt = $conn->prepare(
      $hasAdmissionDateCol
        ? "INSERT INTO students (username, email, password, fullname, regno, admission_date, access_expires_at, dob, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        : "INSERT INTO students (username, email, password, fullname, regno, access_expires_at, dob, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if ($hasAdmissionDateCol) {
        $stmt->bind_param("ssssssssd", $username, $email, $hashed, $fullname, $regno, $admissionDateStr, $accessExpiresAt, $dob, $cgpa);
    } else {
        $stmt->bind_param("sssssssd", $username, $email, $hashed, $fullname, $regno, $accessExpiresAt, $dob, $cgpa);
    }
} else {
    $stmt = $conn->prepare(
      "INSERT INTO students (username, email, password, fullname, regno, dob, cgpa)
       VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("ssssssd", $username, $email, $hashed, $fullname, $regno, $dob, $cgpa);
}

if ($stmt->execute()) {
    echo json_encode(["message" => "Student account created", "needs_verification" => false]);
} else {
    echo json_encode(["message" => "Signup failed"]);
}
?>
