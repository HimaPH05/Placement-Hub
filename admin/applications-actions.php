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

$status = strtolower(trim((string)($_GET["status"] ?? "all")));
$allowedStatuses = [
    "all" => null,
    "pending" => "Pending",
    "shortlisted" => "Shortlisted",
    "placed" => "Placed",
    "rejected" => "Rejected",
    "cancelled" => "Cancelled"
];

if (!array_key_exists($status, $allowedStatuses)) {
    $status = "all";
}

$sql = "
    SELECT
        a.id,
        a.status,
        a.applied_at,
        s.fullname AS student_name,
        COALESCE(s.regno, '') AS regno,
        COALESCE(j.job_title, '') AS job_title,
        COALESCE(NULLIF(c.companyName, ''), c.username) AS company_name
    FROM applications a
    JOIN students s ON s.id = a.student_id
    JOIN jobs j ON j.id = a.job_id
    JOIN companies c ON c.id = a.company_id
";

if ($allowedStatuses[$status] !== null) {
    $sql .= " WHERE a.status = ?";
}

$sql .= " ORDER BY a.applied_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to prepare applications query"]);
    exit();
}

if ($allowedStatuses[$status] !== null) {
    $statusValue = $allowedStatuses[$status];
    $stmt->bind_param("s", $statusValue);
}

$stmt->execute();
$result = $stmt->get_result();

$applications = [];
while ($row = $result->fetch_assoc()) {
    $applications[] = [
        "id" => (int)$row["id"],
        "status" => $row["status"],
        "applied_at" => $row["applied_at"],
        "student_name" => $row["student_name"],
        "regno" => $row["regno"],
        "job_title" => $row["job_title"],
        "company_name" => $row["company_name"]
    ];
}

echo json_encode([
    "success" => true,
    "status" => $status,
    "applications" => $applications
]);
exit();
?>
