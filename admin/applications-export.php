<?php
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

require_once __DIR__ . "/../db.php";

$status = strtolower(trim((string)($_GET["status"] ?? "")));
$company = trim((string)($_GET["company"] ?? ""));

$allowedStatuses = [
    "shortlisted" => "Shortlisted",
    "placed" => "Placed"
];

if (!array_key_exists($status, $allowedStatuses)) {
    http_response_code(400);
    echo "Invalid status selected";
    exit();
}

$sql = "
    SELECT
        s.fullname AS student_name,
        COALESCE(s.regno, '') AS regno,
        COALESCE(j.job_title, '') AS job_title,
        COALESCE(NULLIF(c.companyName, ''), c.username) AS company_name,
        a.applied_at,
        a.status
    FROM applications a
    JOIN students s ON s.id = a.student_id
    JOIN jobs j ON j.id = a.job_id
    JOIN companies c ON c.id = a.company_id
    WHERE a.status = ?
";

$params = [$allowedStatuses[$status]];
$types = "s";

if ($company !== "" && strtolower($company) !== "all") {
    $sql .= " AND COALESCE(NULLIF(c.companyName, ''), c.username) = ?";
    $params[] = $company;
    $types .= "s";
}

$sql .= " ORDER BY company_name ASC, student_name ASC, a.applied_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "Failed to prepare export query";
    exit();
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$applications = [];
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}

function pdf_escape_text(string $text): string
{
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace("(", "\\(", $text);
    $text = str_replace(")", "\\)", $text);
    $text = preg_replace('/[^\x20-\x7E]/', '?', $text);
    return $text;
}

function pdf_cell_value($value, int $width): string
{
    $text = trim((string)$value);
    if ($text === "") {
        $text = "-";
    }

    if (strlen($text) > $width) {
        $text = substr($text, 0, max(0, $width - 3)) . "...";
    }

    return str_pad($text, $width, " ");
}

function pdf_build_page_stream(array $lines): string
{
    $content = "BT\n/F1 10 Tf\n14 TL\n";
    $content .= "1 0 0 1 28 800 Tm\n";

    foreach ($lines as $index => $line) {
        if ($index > 0) {
            $content .= "T*\n";
        }
        $content .= "(" . pdf_escape_text($line) . ") Tj\n";
    }

    $content .= "ET";
    return $content;
}

$title = $allowedStatuses[$status] . " Students";
$filterLine = ($company !== "" && strtolower($company) !== "all")
    ? "Company: " . $company
    : "Company: All Companies";
$dateLine = "Generated on: " . date("Y-m-d H:i:s");
$countLine = "Total students: " . count($applications);
$headerLine = pdf_cell_value("Student", 22) . " " .
    pdf_cell_value("Company", 24) . " " .
    pdf_cell_value("Job Role", 20) . " " .
    pdf_cell_value("Reg No", 14) . " " .
    pdf_cell_value("Applied On", 19);
$dividerLine = str_repeat("-", strlen($headerLine));

$allLines = [$title, $filterLine, $dateLine, $countLine, "", $headerLine, $dividerLine];

foreach ($applications as $application) {
    $allLines[] =
        pdf_cell_value($application["student_name"] ?? "", 22) . " " .
        pdf_cell_value($application["company_name"] ?? "", 24) . " " .
        pdf_cell_value($application["job_title"] ?? "", 20) . " " .
        pdf_cell_value($application["regno"] ?? "", 14) . " " .
        pdf_cell_value($application["applied_at"] ?? "", 19);
}

if (count($applications) === 0) {
    $allLines[] = "No students matched the selected status/company filter.";
}

$linesPerPage = 48;
$pageLineChunks = array_chunk($allLines, $linesPerPage);

$objects = [];
$pageObjectNumbers = [];
$contentObjectNumbers = [];

$objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

$pageCount = count($pageLineChunks);
$fontObjectNumber = 3 + ($pageCount * 2);

for ($i = 0; $i < $pageCount; $i++) {
    $pageObjectNumbers[$i] = 3 + ($i * 2);
    $contentObjectNumbers[$i] = 4 + ($i * 2);
}

$kids = [];
for ($i = 0; $i < $pageCount; $i++) {
    $pageObjectNumber = $pageObjectNumbers[$i];
    $contentObjectNumber = $contentObjectNumbers[$i];
    $stream = pdf_build_page_stream($pageLineChunks[$i]);
    $objects[$pageObjectNumber] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObjectNumber} 0 R >> >> /Contents {$contentObjectNumber} 0 R >>";
    $objects[$contentObjectNumber] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    $kids[] = "{$pageObjectNumber} 0 R";
}

$objects[2] = "<< /Type /Pages /Kids [" . implode(" ", $kids) . "] /Count {$pageCount} >>";
$objects[$fontObjectNumber] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";

ksort($objects);

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $number => $objectBody) {
    $offsets[$number] = strlen($pdf);
    $pdf .= $number . " 0 obj\n" . $objectBody . "\nendobj\n";
}

$xrefOffset = strlen($pdf);
$objectCount = max(array_keys($objects));
$pdf .= "xref\n0 " . ($objectCount + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
for ($i = 1; $i <= $objectCount; $i++) {
    $offset = $offsets[$i] ?? 0;
    $pdf .= sprintf("%010d 00000 n \n", $offset);
}

$pdf .= "trailer\n<< /Size " . ($objectCount + 1) . " /Root 1 0 R >>\n";
$pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

$filenameParts = [$status];
if ($company !== "" && strtolower($company) !== "all") {
    $filenameParts[] = preg_replace('/[^a-z0-9]+/i', '-', strtolower($company));
}
$filenameParts[] = date("Ymd_His");
$filename = implode("_", array_filter($filenameParts)) . ".pdf";

header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Content-Length: " . strlen($pdf));
echo $pdf;
exit();
?>
