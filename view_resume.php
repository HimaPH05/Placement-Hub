<?php
session_start();

if (!isset($_SESSION["role"])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

$resume_id = (int)($_GET["id"] ?? 0);
$download = isset($_GET["dl"]) && $_GET["dl"] === "1";

if ($resume_id <= 0) {
    http_response_code(400);
    echo "Invalid resume";
    exit;
}

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    http_response_code(500);
    echo "Database connection failed";
    exit;
}

$stmt = $conn->prepare("SELECT student_id, file_name, mime_type, file_data, file_path, visibility FROM student_resumes WHERE id=?");
$stmt->bind_param("i", $resume_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo "Resume not found";
    exit;
}

$row = $result->fetch_assoc();
$role = $_SESSION["role"];
$user_id = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$isOwner = ((int)$row["student_id"] === $user_id);
$isPublic = ($row["visibility"] === "public");
$hasAppliedAccess = false;

if ($role === "company") {
    $companyId = isset($_SESSION["company_id"]) ? (int)$_SESSION["company_id"] : 0;

    if ($companyId <= 0 && isset($_SESSION["username"])) {
        $companyStmt = $conn->prepare("SELECT id FROM companies WHERE username = ? LIMIT 1");
        $companyStmt->bind_param("s", $_SESSION["username"]);
        $companyStmt->execute();
        $companyRow = $companyStmt->get_result()->fetch_assoc();
        if ($companyRow) {
            $companyId = (int)$companyRow["id"];
            $_SESSION["company_id"] = $companyId;
        }
    }

    if ($companyId > 0) {
        $accessStmt = $conn->prepare("
            SELECT id
            FROM applications
            WHERE company_id = ? AND resume_id = ?
            LIMIT 1
        ");
        $accessStmt->bind_param("ii", $companyId, $resume_id);
        $accessStmt->execute();
        $hasAppliedAccess = $accessStmt->get_result()->num_rows > 0;
    }
}

$allowed = false;
if ($role === "admin") {
    $allowed = true;
} elseif ($role === "company") {
    $allowed = $isPublic || $hasAppliedAccess;
} elseif ($role === "student") {
    $allowed = $isPublic || $isOwner;
}

if (!$allowed) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$fileName = $row["file_name"] ?: ("resume_" . $resume_id . ".pdf");
$mimeType = $row["mime_type"] ?: "application/octet-stream";

if (!empty($row["file_data"])) {
    header("Content-Type: " . $mimeType);
    header("Content-Length: " . strlen($row["file_data"]));
    $disposition = $download ? "attachment" : "inline";
    header("Content-Disposition: " . $disposition . "; filename=\"" . basename($fileName) . "\"");
    echo $row["file_data"];
    exit;
}

if (!empty($row["file_path"])) {
    $legacyPath = __DIR__ . "/" . ltrim($row["file_path"], "/");
    if (is_file($legacyPath)) {
        header("Content-Type: " . $mimeType);
        $disposition = $download ? "attachment" : "inline";
        header("Content-Disposition: " . $disposition . "; filename=\"" . basename($fileName) . "\"");
        readfile($legacyPath);
        exit;
    }
}

http_response_code(404);
echo "Resume data not available";
?>
