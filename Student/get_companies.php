<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized"]);
    exit;
}

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

$sql = "
    SELECT
        c.id,
        c.companyName AS name,
        COALESCE(c.description, 'No description available.') AS `desc`,
        COALESCE(c.industry, 'N/A') AS industry,
        COALESCE(c.location, 'N/A') AS location,
        COALESCE(c.website, '') AS website,
        j.id AS latest_job_id,
        j.job_title AS latest_job_title,
        j.min_cgpa AS latest_job_min_cgpa
    FROM companies c
    LEFT JOIN jobs j ON j.id = (
        SELECT j2.id
        FROM jobs j2
        WHERE j2.company_id = c.id
        ORDER BY j2.created_at DESC, j2.id DESC
        LIMIT 1
    )
    ORDER BY c.companyName ASC
";

$result = $conn->query($sql);
$companies = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row["id"] = (int)$row["id"];
        $row["latest_job_id"] = $row["latest_job_id"] !== null ? (int)$row["latest_job_id"] : null;
        $companies[] = $row;
    }
}

echo json_encode(["companies" => $companies]);
?>
