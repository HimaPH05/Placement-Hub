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

$input = json_decode(file_get_contents("php://input"), true);
$placements = isset($input["placements"]) ? (int)$input["placements"] : -1;

if ($placements < 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid placements value"]);
    exit();
}

$conn->query("CREATE TABLE IF NOT EXISTS admin_settings (
    `key_name` VARCHAR(100) PRIMARY KEY,
    `value_text` VARCHAR(255) NOT NULL
)");

$stmt = $conn->prepare(
    "INSERT INTO admin_settings (key_name, value_text)
     VALUES ('placements_count', ?)
     ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to prepare query"]);
    exit();
}

$valueText = (string)$placements;
$stmt->bind_param("s", $valueText);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to save placements"]);
    exit();
}

echo json_encode(["success" => true, "placements" => $placements]);
exit();
?>
