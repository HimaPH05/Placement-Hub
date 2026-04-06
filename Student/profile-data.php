<?php
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student" || !isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized"]);
    exit();
}

require_once __DIR__ . "/../db-config.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit();
}

include_once __DIR__ . "/../database_setup.php";

$studentId = (int)$_SESSION["user_id"];

$hasEmailColumn = false;
$hasScorecardColumn = false;
$hasProfilePhotoColumn = false;
$hasSupplyCountColumn = false;

$emailColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
if ($emailColCheck && $emailColCheck->num_rows > 0) {
    $hasEmailColumn = true;
}

$scorecardColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'ktu_scorecard_path'");
if ($scorecardColCheck && $scorecardColCheck->num_rows > 0) {
    $hasScorecardColumn = true;
}

$profilePhotoColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'profile_photo_path'");
if ($profilePhotoColCheck && $profilePhotoColCheck->num_rows > 0) {
    $hasProfilePhotoColumn = true;
}
$supplyCountColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'supply_count'");
if ($supplyCountColCheck && $supplyCountColCheck->num_rows > 0) {
    $hasSupplyCountColumn = true;
}

if ($hasEmailColumn && $hasScorecardColumn && $hasProfilePhotoColumn && $hasSupplyCountColumn) {
    $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, supply_count, ktu_scorecard_path, profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasEmailColumn && $hasScorecardColumn && $hasProfilePhotoColumn) {
    $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, 0 AS supply_count, ktu_scorecard_path, profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasEmailColumn && $hasScorecardColumn && $hasSupplyCountColumn) {
    $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, supply_count, ktu_scorecard_path, '' AS profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasEmailColumn && $hasProfilePhotoColumn && $hasSupplyCountColumn) {
    $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, supply_count, '' AS ktu_scorecard_path, profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasScorecardColumn && $hasProfilePhotoColumn && $hasSupplyCountColumn) {
    $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa, supply_count, ktu_scorecard_path, profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasEmailColumn && $hasProfilePhotoColumn) {
    $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, 0 AS supply_count, '' AS ktu_scorecard_path, profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasScorecardColumn && $hasProfilePhotoColumn) {
    $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa, 0 AS supply_count, ktu_scorecard_path, profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasProfilePhotoColumn) {
    $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa, 0 AS supply_count, '' AS ktu_scorecard_path, profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasEmailColumn && $hasScorecardColumn) {
    $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, 0 AS supply_count, ktu_scorecard_path, '' AS profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasEmailColumn && $hasSupplyCountColumn) {
    $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, supply_count, '' AS ktu_scorecard_path, '' AS profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasEmailColumn) {
    $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, 0 AS supply_count, '' AS ktu_scorecard_path, '' AS profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasScorecardColumn && $hasSupplyCountColumn) {
    $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa, supply_count, ktu_scorecard_path, '' AS profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasScorecardColumn) {
    $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa, 0 AS supply_count, ktu_scorecard_path, '' AS profile_photo_path FROM students WHERE id = ? LIMIT 1");
} elseif ($hasSupplyCountColumn) {
    $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa, supply_count, '' AS ktu_scorecard_path, '' AS profile_photo_path FROM students WHERE id = ? LIMIT 1");
} else {
    $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa, 0 AS supply_count, '' AS ktu_scorecard_path, '' AS profile_photo_path FROM students WHERE id = ? LIMIT 1");
}

$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    http_response_code(404);
    echo json_encode(["message" => "Student not found"]);
    exit();
}

$defaultPhotoUrl = "https://cdn-icons-png.flaticon.com/512/3135/3135715.png";
$profilePhotoPath = trim((string)($result["profile_photo_path"] ?? ""));
$profilePhotoUrl = $profilePhotoPath !== "" ? "../" . ltrim($profilePhotoPath, "/") : $defaultPhotoUrl;

echo json_encode([
    "fullname" => (string)($result["fullname"] ?? ""),
    "email" => (string)($result["email"] ?? ""),
    "regno" => (string)($result["regno"] ?? ""),
    "cgpa" => (string)($result["cgpa"] ?? ""),
    "supply_count" => (int)($result["supply_count"] ?? 0),
    "ktu_scorecard_path" => (string)($result["ktu_scorecard_path"] ?? ""),
    "profile_photo_path" => $profilePhotoPath,
    "profile_photo_url" => $profilePhotoUrl
]);
?>
