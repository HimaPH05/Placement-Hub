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

$isVerifiedCol = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_verified'");
if ($isVerifiedCol && $isVerifiedCol->num_rows === 0) {
    $conn->query("ALTER TABLE student_resumes ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER visibility");
}

$verifiedAtCol = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'verified_at'");
if ($verifiedAtCol && $verifiedAtCol->num_rows === 0) {
    $conn->query("ALTER TABLE student_resumes ADD COLUMN verified_at DATETIME NULL AFTER is_verified");
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $sql = "
        SELECT
            sr.id,
            sr.student_id,
            sr.name,
            sr.branch,
            sr.gpa,
            sr.skills,
            sr.about,
            sr.file_name,
            sr.created_at,
            COALESCE(sr.is_verified, 0) AS is_verified,
            sr.verified_at,
            COALESCE(s.username, '') AS student_username
        FROM student_resumes sr
        LEFT JOIN students s ON s.id = sr.student_id
        WHERE sr.visibility = 'public'
        ORDER BY sr.created_at DESC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to fetch resumes"]);
        exit();
    }

    $resumes = [];
    while ($row = $result->fetch_assoc()) {
        $resumes[] = [
            "id" => (int)$row["id"],
            "student_id" => (int)$row["student_id"],
            "name" => $row["name"],
            "student_username" => $row["student_username"],
            "branch" => $row["branch"],
            "gpa" => $row["gpa"],
            "skills" => $row["skills"],
            "about" => $row["about"],
            "file_name" => $row["file_name"],
            "created_at" => $row["created_at"],
            "is_verified" => ((int)$row["is_verified"]) === 1,
            "verified_at" => $row["verified_at"],
            "file_url" => "../view_resume.php?id=" . (int)$row["id"],
            "download_url" => "../view_resume.php?id=" . (int)$row["id"] . "&dl=1"
        ];
    }

    echo json_encode(["success" => true, "resumes" => $resumes]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$payload = json_decode(file_get_contents("php://input"), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$resumeId = (int)($payload["resume_id"] ?? 0);
$isVerified = (int)($payload["is_verified"] ?? 0);
$isVerified = ($isVerified === 1) ? 1 : 0;

if ($resumeId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid resume"]);
    exit();
}

$update = $conn->prepare("
    UPDATE student_resumes
    SET is_verified = ?, verified_at = IF(? = 1, NOW(), NULL)
    WHERE id = ? AND visibility = 'public'
");

if (!$update) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to verify resume"]);
    exit();
}

$update->bind_param("iii", $isVerified, $isVerified, $resumeId);
$update->execute();

if ($update->affected_rows === 0) {
    $check = $conn->prepare("SELECT id FROM student_resumes WHERE id = ? AND visibility = 'public' LIMIT 1");
    $check->bind_param("i", $resumeId);
    $check->execute();
    $exists = $check->get_result();

    if (!$exists || $exists->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Public resume not found"]);
        exit();
    }
}

echo json_encode([
    "success" => true,
    "message" => $isVerified === 1 ? "Resume verified" : "Resume marked as unverified",
    "resume_id" => $resumeId,
    "is_verified" => ($isVerified === 1)
]);
exit();
?>
