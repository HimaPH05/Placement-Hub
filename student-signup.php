<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "detailsdb");

if ($conn->connect_error) {
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

$username = trim($data->username ?? '');
$email    = trim($data->email ?? '');
$password = trim($data->password ?? '');
$fullname = trim($data->fullname ?? '');
$regno    = trim($data->regno ?? '');
$dob      = trim($data->dob ?? '');
$cgpa     = trim($data->cgpa ?? '');

if ($username === "" || $email === "" || $password === "" || $fullname === "" || $regno === "" || $dob === "" || $cgpa === "") {
    echo json_encode(["message" => "All fields are required"]);
    exit;
}

$check = $conn->prepare("SELECT id FROM students WHERE username=? OR regno=? OR email=?");
$check->bind_param("sss", $username, $regno, $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["message" => "Username, Email, or Register Number already exists"]);
    exit;
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
  "INSERT INTO students (username, email, password, fullname, regno, dob, cgpa)
   VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("ssssssd", $username, $email, $hashed, $fullname, $regno, $dob, $cgpa);

if ($stmt->execute()) {
    echo json_encode(["message" => "Student account created"]);
} else {
    echo json_encode(["message" => "Signup failed"]);
}
?>
