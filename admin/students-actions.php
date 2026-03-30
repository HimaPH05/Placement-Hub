<?php
session_start();
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

require_once __DIR__ . "/../db.php";

$hasEmailColumn = false;
$hasAdmissionYearColumn = false;
$hasAccessExpiryColumn = false;

$emailCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
if ($emailCheck && $emailCheck->num_rows > 0) {
    $hasEmailColumn = true;
}

$admissionYearCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'admission_year'");
if ($admissionYearCheck && $admissionYearCheck->num_rows > 0) {
    $hasAdmissionYearColumn = true;
}

$accessExpiryCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'access_expires_at'");
if ($accessExpiryCheck && $accessExpiryCheck->num_rows > 0) {
    $hasAccessExpiryColumn = true;
}

$sql = "
    SELECT
        s.id,
        s.fullname,
        s.username,
        " . ($hasEmailColumn ? "COALESCE(s.email, '') AS email," : "'' AS email,") . "
        COALESCE(s.regno, '') AS regno,
        COALESCE(s.cgpa, '') AS cgpa,
        " . ($hasAdmissionYearColumn ? "s.admission_year," : "NULL AS admission_year,") . "
        " . ($hasAccessExpiryColumn ? "s.access_expires_at," : "NULL AS access_expires_at,") . "
        COALESCE((
            SELECT sr.branch
            FROM student_resumes sr
            WHERE sr.student_id = s.id
            ORDER BY (sr.visibility = 'public') DESC, sr.created_at DESC, sr.id DESC
            LIMIT 1
        ), 'N/A') AS branch
    FROM students s
    ORDER BY branch ASC, admission_year DESC, s.fullname ASC
";

$result = $conn->query($sql);
if (!$result) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to fetch students"]);
    exit();
}

$currentYear = (int)date("Y");
$students = [];

while ($row = $result->fetch_assoc()) {
    $admissionYear = (int)($row["admission_year"] ?? 0);
    $yearLabel = "N/A";
    if ($admissionYear > 0) {
        $computedYear = ($currentYear - $admissionYear) + 1;
        if ($computedYear < 1) {
            $computedYear = 1;
        } elseif ($computedYear > 4) {
            $computedYear = 4;
        }
        $yearLabel = "Year " . $computedYear;
    }

    $students[] = [
        "id" => (int)$row["id"],
        "fullname" => $row["fullname"],
        "username" => $row["username"],
        "email" => $row["email"],
        "regno" => $row["regno"],
        "cgpa" => $row["cgpa"],
        "branch" => $row["branch"],
        "admission_year" => $admissionYear > 0 ? $admissionYear : null,
        "year_label" => $yearLabel,
        "access_expires_at" => $row["access_expires_at"] ?? null
    ];
}

echo json_encode(["success" => true, "students" => $students]);
exit();
?>
