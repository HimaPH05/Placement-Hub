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

$tokenBytes = random_bytes(32);
$token = rtrim(strtr(base64_encode($tokenBytes), "+/", "-_"), "=");
$tokenHash = hash("sha256", $token);
$expiresAt = (new DateTimeImmutable("+24 hours"))->format("Y-m-d H:i:s");

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
    $studentId = (int)$stmt->insert_id;

    // Only enable verification if DB has the columns (keeps compatibility before running database_setup.php).
    $hasVerify = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email_verify_token_hash'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasVerify = true;
    }

    if ($hasVerify) {
        $u = $conn->prepare("UPDATE students SET is_email_verified = 0, email_verify_token_hash = ?, email_verify_expires_at = ? WHERE id = ?");
        $u->bind_param("ssi", $tokenHash, $expiresAt, $studentId);
        $u->execute();
        $u->close();

        $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
        $host = $_SERVER["HTTP_HOST"] ?? "localhost";
        $basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
        $verifyUrl = "{$scheme}://{$host}{$basePath}/verify-email.php?token=" . urlencode($token);

        require_once __DIR__ . "/email.php";
        $subject = "Verify your Placement Hub email";
        $body = "Hi {$fullname},\n\n";
        $body .= "Please verify your email to activate your Placement Hub student account:\n\n";
        $body .= $verifyUrl . "\n\n";
        $body .= "This link expires in 24 hours.\n\n";
        $body .= "If you did not create this account, you can ignore this email.\n";

        [$sent, $note] = send_email($email, $subject, $body);

        echo json_encode([
            "message" => "Account created. Verification email sent. Please verify before login.",
            "needs_verification" => true,
            "mail_note" => $sent ? "" : $note
        ]);
        exit;
    }

    echo json_encode(["message" => "Student account created", "needs_verification" => false]);
} else {
    echo json_encode(["message" => "Signup failed"]);
}
?>
