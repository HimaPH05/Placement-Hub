<?php
header("Content-Type: application/json");
session_start();

require_once "admin-credentials.php";

$input = json_decode(file_get_contents("php://input"), true);
$username = trim($input["username"] ?? "");
$password = trim($input["password"] ?? "");
$role = trim($input["role"] ?? "");

if ($role !== "admin") {
    echo json_encode(["message" => "Invalid role"]);
    exit;
}

$creds = get_admin_credentials();

if ($username === $creds["username"] && password_verify($password, $creds["password_hash"])) {
    $_SESSION["username"] = $username;
    $_SESSION["role"] = "admin";
    echo json_encode(["message" => "Login successful", "role" => "admin"]);
    exit;
}

echo json_encode(["message" => "Invalid username or password"]);
