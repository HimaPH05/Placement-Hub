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
require_once __DIR__ . "/student-data.php";

$action = strtolower(trim((string)($_GET["action"] ?? "")));

if ($action === "detail") {
    $studentId = (int)($_GET["id"] ?? 0);
    $student = admin_fetch_student_detail($conn, $studentId);

    if ($student === null) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Student not found"]);
        exit();
    }

    echo json_encode(["success" => true, "student" => $student]);
    exit();
}

$students = admin_fetch_students($conn, $_GET);

echo json_encode(["success" => true, "students" => $students]);
exit();
?>
