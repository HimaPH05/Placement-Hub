<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$username    = $data["username"] ?? "";
$password    = $data["password"] ?? "";
$companyName = $data["companyName"] ?? "";
$email       = $data["email"] ?? "";
$phone       = $data["phone"] ?? "";
$location    = $data["location"] ?? "";
$industry    = $data["industry"] ?? "";
$website     = $data["website"] ?? "";

if (!$username || !$password || !$companyName || !$email || !$phone || !$location || !$industry) {
    echo json_encode(["message" => "All required fields must be filled"]);
    exit;
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert into companies table
$stmt = $conn->prepare("INSERT INTO companies (username, password, companyName, email, phone, location, industry, website) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssss", $username, $hashedPassword, $companyName, $email, $phone, $location, $industry, $website);

if ($stmt->execute()) {
    echo json_encode(["message" => "Company account created successfully"]);
} else {
    echo json_encode(["message" => "Error: " . $stmt->error]);
}

$conn->close();
?>
