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
include_once __DIR__ . "/../database_setup.php";

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $result = $conn->query("
        SELECT id, title, company_name, apply_url, description, min_cgpa, max_supplies, deadline_date, is_active, created_at
        FROM admin_opportunity_links
        ORDER BY is_active DESC, deadline_date ASC, created_at DESC
    ");

    if (!$result) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to fetch links"]);
        exit();
    }

    $links = [];
    while ($row = $result->fetch_assoc()) {
        $links[] = [
            "id" => (int)$row["id"],
            "title" => $row["title"],
            "company_name" => $row["company_name"],
            "apply_url" => $row["apply_url"],
            "description" => $row["description"],
            "min_cgpa" => $row["min_cgpa"] !== null ? (float)$row["min_cgpa"] : null,
            "max_supplies" => $row["max_supplies"] !== null ? (int)$row["max_supplies"] : null,
            "deadline_date" => $row["deadline_date"],
            "is_active" => ((int)$row["is_active"]) === 1,
            "created_at" => $row["created_at"]
        ];
    }

    echo json_encode(["success" => true, "links" => $links]);
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

$action = strtolower(trim((string)($payload["action"] ?? "add")));

if ($action === "delete") {
    $id = (int)($payload["id"] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid link selected"]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM admin_opportunity_links WHERE id = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Unable to delete link"]);
        exit();
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Link deleted"]);
    exit();
}

$title = trim((string)($payload["title"] ?? ""));
$companyName = trim((string)($payload["company_name"] ?? ""));
$applyUrl = trim((string)($payload["apply_url"] ?? ""));
$description = trim((string)($payload["description"] ?? ""));
$minCgpaInput = trim((string)($payload["min_cgpa"] ?? ""));
$maxSuppliesInput = trim((string)($payload["max_supplies"] ?? ""));
$deadlineDate = trim((string)($payload["deadline_date"] ?? ""));

if ($title === "" || $companyName === "" || $applyUrl === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Title, company name, and URL are required"]);
    exit();
}

if (!filter_var($applyUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a valid application URL"]);
    exit();
}

$minCgpa = null;
if ($minCgpaInput !== "") {
    if (!is_numeric($minCgpaInput)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Minimum CGPA must be a number"]);
        exit();
    }

    $minCgpa = round((float)$minCgpaInput, 2);
    if ($minCgpa < 0 || $minCgpa > 10) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Minimum CGPA must be between 0 and 10"]);
        exit();
    }
}

if ($deadlineDate === "") {
    $deadlineDate = null;
}

$maxSupplies = null;
if ($maxSuppliesInput !== "") {
    if (!ctype_digit($maxSuppliesInput)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Maximum supplies must be a whole number"]);
        exit();
    }

    $maxSupplies = (int)$maxSuppliesInput;
    if ($maxSupplies < 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Maximum supplies cannot be negative"]);
        exit();
    }
}

$stmt = $conn->prepare("
    INSERT INTO admin_opportunity_links (title, company_name, apply_url, description, min_cgpa, max_supplies, deadline_date)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to save link"]);
    exit();
}

$stmt->bind_param("ssssdis", $title, $companyName, $applyUrl, $description, $minCgpa, $maxSupplies, $deadlineDate);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to save link"]);
    exit();
}

echo json_encode(["success" => true, "message" => "Application link posted"]);
exit();
?>
