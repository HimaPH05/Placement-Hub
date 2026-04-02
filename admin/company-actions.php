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
require_once __DIR__ . "/../profile-helpers.php";
include_once __DIR__ . "/../database_setup.php";

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $sql = "
        SELECT
            id,
            companyName AS name,
            email,
            COALESCE(location, 'N/A') AS location,
            COALESCE(industry, 'N/A') AS industry,
            COALESCE(profile_photo_path, '') AS profile_photo_path
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
        $row["photo_url"] = placementhub_company_photo_url($row, "../");
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

$action = strtolower(trim((string)($payload["action"] ?? "add")));

if ($action === "delete") {
    $companyId = (int)($payload["id"] ?? 0);
    if ($companyId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid company selected"]);
        exit();
    }

    $tableExists = function ($name) use ($conn) {
        $safe = $conn->real_escape_string($name);
        $check = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $check && $check->num_rows > 0;
    };

    $conn->begin_transaction();
    try {
        $cleanupTables = [
            ["table" => "applications", "column" => "company_id"],
            ["table" => "jobs", "column" => "company_id"],
            ["table" => "hr_contacts", "column" => "company_id"],
            ["table" => "company_profiles", "column" => "company_id"]
        ];

        foreach ($cleanupTables as $cfg) {
            if (!$tableExists($cfg["table"])) {
                continue;
            }
            $del = $conn->prepare("DELETE FROM {$cfg['table']} WHERE {$cfg['column']} = ?");
            if (!$del) {
                throw new Exception("Failed to prepare cleanup query");
            }
            $del->bind_param("i", $companyId);
            if (!$del->execute()) {
                throw new Exception("Failed to cleanup company data");
            }
        }

        $companyDelete = $conn->prepare("DELETE FROM companies WHERE id = ? LIMIT 1");
        if (!$companyDelete) {
            throw new Exception("Failed to prepare delete query");
        }
        $companyDelete->bind_param("i", $companyId);
        if (!$companyDelete->execute()) {
            throw new Exception("Failed to delete company");
        }

        if ($companyDelete->affected_rows === 0) {
            $conn->rollback();
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Company not found"]);
            exit();
        }

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Company deleted completely"]);
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Unable to delete company"]);
        exit();
    }
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
