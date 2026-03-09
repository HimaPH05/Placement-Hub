<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student" || !isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["message" => "Please login as student first"]);
    exit;
}

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

$hasVerifyColumns = false;
$verifyColCheck = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_verified'");
if ($verifyColCheck && $verifyColCheck->num_rows > 0) {
    $hasVerifyColumns = true;
}
$hasRejectColumns = false;
$rejectColCheck = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_rejected'");
if ($rejectColCheck && $rejectColCheck->num_rows > 0) {
    $hasRejectColumns = true;
}

$student_id = (int)$_SESSION["user_id"];
$username = trim($_SESSION["username"] ?? "");

$verifySelect = $hasVerifyColumns
    ? "COALESCE(sr.is_verified, 0) AS is_verified, sr.verified_at,"
    : "0 AS is_verified, NULL AS verified_at,";
$rejectSelect = $hasRejectColumns
    ? "COALESCE(sr.is_rejected, 0) AS is_rejected, sr.rejected_at,"
    : "0 AS is_rejected, NULL AS rejected_at,";

$stmt = $conn->prepare("
    SELECT
        sr.id,
        sr.student_id,
        sr.name,
        sr.branch,
        sr.gpa,
        sr.about,
        sr.skills,
        sr.file_name,
        sr.visibility,
        {$verifySelect}
        {$rejectSelect}
        s.username AS owner_username
    FROM student_resumes sr
    LEFT JOIN students s ON s.id = sr.student_id
    WHERE sr.visibility='public'
       OR sr.student_id=?
       OR (s.username IS NOT NULL AND s.username=?)
    ORDER BY sr.created_at DESC
");
$stmt->bind_param("is", $student_id, $username);
$stmt->execute();
$result = $stmt->get_result();

$resumes = [];
while ($row = $result->fetch_assoc()) {
    $skills = [];
    if (!empty($row["skills"])) {
        $skills = array_map("trim", explode(",", $row["skills"]));
    }

    $resumeId = (int)$row["id"];
    $isOwnerById = ((int)$row["student_id"] === $student_id);
    $isOwnerByUsername = ($username !== "" && isset($row["owner_username"]) && (string)$row["owner_username"] === $username);
    $isOwner = ($isOwnerById || $isOwnerByUsername);

    $resumes[] = [
        "id" => $resumeId,
        "name" => $row["name"],
        "branch" => $row["branch"],
        "gpa" => $row["gpa"],
        "about" => $row["about"],
        "skills" => $skills,
        "file_name" => $row["file_name"],
        "file_url" => "../view_resume.php?id=" . $resumeId,
        "download_url" => "../view_resume.php?id=" . $resumeId . "&dl=1",
        "visibility" => $row["visibility"],
        "is_owner" => $isOwner,
        "is_verified" => ((int)($row["is_verified"] ?? 0)) === 1,
        "verified_at" => $row["verified_at"] ?? null,
        "is_rejected" => ((int)($row["is_rejected"] ?? 0)) === 1,
        "rejected_at" => $row["rejected_at"] ?? null
    ];
}

echo json_encode(["resumes" => $resumes]);
?>
