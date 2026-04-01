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
$department = strtoupper(trim((string)($data->department ?? '')));
$currentYear = trim((string)($data->current_year ?? ''));
$dob      = trim($data->dob ?? '');
$cgpa     = trim($data->cgpa ?? '');

if ($username === "" || $email === "" || $password === "" || $fullname === "" || $regno === "" || $department === "" || $currentYear === "" || $dob === "" || $cgpa === "") {
    echo json_encode(["message" => "All fields are required"]);
    exit;
}

$allowedDepartments = ["CE", "ME", "CHE", "EC", "AEI", "CSD"];
if (!in_array($department, $allowedDepartments, true)) {
    echo json_encode(["message" => "Please select a valid department"]);
    exit;
}

if (!preg_match('/^[1-4]$/', $currentYear)) {
    echo json_encode(["message" => "Please select a valid year"]);
    exit;
}

require_once __DIR__ . "/student-lifecycle.php";
$admissionYear = student_lifecycle_compute_admission_year_from_current_year((int)$currentYear);
$accessExpiresAt = student_lifecycle_compute_access_expiry_from_current_year((int)$currentYear);

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
$hasDepartmentCol = false;
$hasCurrentYearCol = false;
$hasAdmissionYearCol = false;
$lifeColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'access_expires_at'");
if ($lifeColCheck && $lifeColCheck->num_rows > 0) {
    $hasLifecycleCols = true;
}

$departmentColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'department'");
if ($departmentColCheck && $departmentColCheck->num_rows > 0) {
    $hasDepartmentCol = true;
}

$currentYearColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'current_year'");
if ($currentYearColCheck && $currentYearColCheck->num_rows > 0) {
    $hasCurrentYearCol = true;
}

$admissionYearColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'admission_year'");
if ($admissionYearColCheck && $admissionYearColCheck->num_rows > 0) {
    $hasAdmissionYearCol = true;
}

if ($hasLifecycleCols) {
    if ($hasDepartmentCol && $hasCurrentYearCol && $hasAdmissionYearCol) {
        $stmt = $conn->prepare(
            "INSERT INTO students (username, email, password, fullname, regno, department, current_year, admission_year, access_expires_at, dob, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssssissd", $username, $email, $hashed, $fullname, $regno, $department, $currentYear, $admissionYear, $accessExpiresAt, $dob, $cgpa);
    } elseif ($hasDepartmentCol && $hasAdmissionYearCol) {
        $stmt = $conn->prepare(
            "INSERT INTO students (username, email, password, fullname, regno, department, admission_year, access_expires_at, dob, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssssissd", $username, $email, $hashed, $fullname, $regno, $department, $admissionYear, $accessExpiresAt, $dob, $cgpa);
    } elseif ($hasCurrentYearCol && $hasAdmissionYearCol) {
        $stmt = $conn->prepare(
            "INSERT INTO students (username, email, password, fullname, regno, current_year, admission_year, access_expires_at, dob, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssisssd", $username, $email, $hashed, $fullname, $regno, $currentYear, $admissionYear, $accessExpiresAt, $dob, $cgpa);
    } elseif ($hasDepartmentCol) {
        $stmt = $conn->prepare(
            "INSERT INTO students (username, email, password, fullname, regno, department, access_expires_at, dob, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssssssd", $username, $email, $hashed, $fullname, $regno, $department, $accessExpiresAt, $dob, $cgpa);
    } elseif ($hasCurrentYearCol) {
        $stmt = $conn->prepare(
            "INSERT INTO students (username, email, password, fullname, regno, current_year, access_expires_at, dob, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssssssd", $username, $email, $hashed, $fullname, $regno, $currentYear, $accessExpiresAt, $dob, $cgpa);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO students (username, email, password, fullname, regno, access_expires_at, dob, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssssd", $username, $email, $hashed, $fullname, $regno, $accessExpiresAt, $dob, $cgpa);
    }
} else {
    if ($hasDepartmentCol && $hasCurrentYearCol && $hasAdmissionYearCol) {
        $stmt = $conn->prepare(
          "INSERT INTO students (username, email, password, fullname, regno, department, current_year, admission_year, dob, cgpa)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssssisd", $username, $email, $hashed, $fullname, $regno, $department, $currentYear, $admissionYear, $dob, $cgpa);
    } elseif ($hasDepartmentCol && $hasAdmissionYearCol) {
        $stmt = $conn->prepare(
          "INSERT INTO students (username, email, password, fullname, regno, department, admission_year, dob, cgpa)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssssisd", $username, $email, $hashed, $fullname, $regno, $department, $admissionYear, $dob, $cgpa);
    } elseif ($hasCurrentYearCol && $hasAdmissionYearCol) {
        $stmt = $conn->prepare(
          "INSERT INTO students (username, email, password, fullname, regno, current_year, admission_year, dob, cgpa)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssissd", $username, $email, $hashed, $fullname, $regno, $currentYear, $admissionYear, $dob, $cgpa);
    } elseif ($hasDepartmentCol) {
        $stmt = $conn->prepare(
          "INSERT INTO students (username, email, password, fullname, regno, department, dob, cgpa)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssssd", $username, $email, $hashed, $fullname, $regno, $department, $dob, $cgpa);
    } elseif ($hasCurrentYearCol) {
        $stmt = $conn->prepare(
          "INSERT INTO students (username, email, password, fullname, regno, current_year, dob, cgpa)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssssd", $username, $email, $hashed, $fullname, $regno, $currentYear, $dob, $cgpa);
    } else {
        $stmt = $conn->prepare(
          "INSERT INTO students (username, email, password, fullname, regno, dob, cgpa)
           VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssssd", $username, $email, $hashed, $fullname, $regno, $dob, $cgpa);
    }
}

if ($stmt->execute()) {
    // Email verification disabled: account is immediately active (subject to college email policy + expiry policy).
    echo json_encode(["message" => "Student account created", "needs_verification" => false]);
} else {
    echo json_encode(["message" => "Signup failed"]);
}
?>
