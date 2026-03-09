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

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $sql = "
        SELECT
            id,
            companyName AS name,
            email,
            COALESCE(location, 'N/A') AS location,
            COALESCE(industry, 'N/A') AS industry
        FROM companies
        ORDER BY companyName ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to fetch companies"]);
        exit();
    }

    $companies = [];
    while ($row = $result->fetch_assoc()) {
        $row["id"] = (int)$row["id"];
        $companies[] = $row;
    }

    echo json_encode(["success" => true, "companies" => $companies]);
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

$companyName = trim($payload["name"] ?? "");
$email = trim($payload["email"] ?? "");
$location = trim($payload["location"] ?? "");
$industry = trim($payload["industry"] ?? "");

if ($companyName === "" || $email === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Company name and email are required"]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a valid email"]);
    exit();
}

$usernameBase = preg_replace("/[^a-z0-9]/", "", strtolower($companyName));
if ($usernameBase === "") {
    $usernameBase = "company";
}
$usernameBase = substr($usernameBase, 0, 20);

$username = $usernameBase;
$suffix = 1;
while (true) {
    $check = $conn->prepare("SELECT id FROM companies WHERE username = ? LIMIT 1");
    $check->bind_param("s", $username);
    $check->execute();
    $existing = $check->get_result();
    if ($existing && $existing->num_rows === 0) {
        break;
    }
    $suffix++;
    $username = $usernameBase . $suffix;
}

$plainPassword = "PH" . random_int(100000, 999999);
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
$phone = "N/A";
$website = "";

$stmt = $conn->prepare("
    INSERT INTO companies
        (username, password, companyName, email, phone, location, industry, website)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to prepare insert query"]);
    exit();
}

$stmt->bind_param(
    "ssssssss",
    $username,
    $hashedPassword,
    $companyName,
    $email,
    $phone,
    $location,
    $industry,
    $website
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to add company"]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => "Company added successfully",
    "credentials" => [
        "username" => $username,
        "password" => $plainPassword
    ]
]);
exit();
?>
