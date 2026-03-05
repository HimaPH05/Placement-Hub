<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student" || !isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["message" => "Please login as student first"]);
    exit;
}

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

$student_id = (int)$_SESSION["user_id"];
$stmt = $conn->prepare("SELECT id, student_id, name, branch, gpa, about, skills, file_name, file_path, visibility FROM student_resumes WHERE visibility='public' OR student_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$resumes = [];
while ($row = $result->fetch_assoc()) {
    $skills = [];
    if (!empty($row["skills"])) {
        $skills = array_map("trim", explode(",", $row["skills"]));
    }

    $resumes[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"],
        "branch" => $row["branch"],
        "gpa" => $row["gpa"],
        "about" => $row["about"],
        "skills" => $skills,
        "file_name" => $row["file_name"],
        "file_url" => "../" . $row["file_path"],
        "visibility" => $row["visibility"],
        "is_owner" => ((int)$row["student_id"] === $student_id)
    ];
}

echo json_encode(["resumes" => $resumes]);
?>
