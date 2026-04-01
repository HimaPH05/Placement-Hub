<?php
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/student-data.php";

$filters = admin_normalize_student_filters($_GET);
$students = admin_fetch_students($conn, $filters);

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
    $content .= "1 0 0 1 40 800 Tm\n";

    foreach ($lines as $index => $line) {
        if ($index > 0) {
            $content .= "T*\n";
        }
        $content .= "(" . pdf_escape_text($line) . ") Tj\n";
    }

    $content .= "ET";
    return $content;
}

$title = "Registered Students";
$filterParts = [];
if ($filters["search"] !== "") {
    $filterParts[] = "Search: " . $filters["search"];
}
if ($filters["department"] !== "") {
    $filterParts[] = "Department: " . $filters["department"];
}
if ($filters["year"] !== "") {
    $filterParts[] = "Year: " . $filters["year"];
}
$filterLine = empty($filterParts) ? "Filters: All students" : "Filters: " . implode(" | ", $filterParts);
$dateLine = "Generated on: " . date("Y-m-d H:i:s");
$countLine = "Total students: " . count($students);
$headerLine = pdf_cell_value("Reg No", 16) . " " .
    pdf_cell_value("Name", 24) . " " .
    pdf_cell_value("Department", 18) . " " .
    pdf_cell_value("Year", 6) . " " .
    pdf_cell_value("CGPA", 6) . " " .
    pdf_cell_value("Email", 28);
$dividerLine = str_repeat("-", strlen($headerLine));

$allLines = [$title, $filterLine, $dateLine, $countLine, "", $headerLine, $dividerLine];

foreach ($students as $student) {
    $allLines[] =
        pdf_cell_value($student["regno"] ?? "", 16) . " " .
        pdf_cell_value($student["fullname"] ?? "", 24) . " " .
        pdf_cell_value($student["branch"] ?? "", 18) . " " .
        pdf_cell_value((string)($student["year_number"] ?? ""), 6) . " " .
        pdf_cell_value((string)($student["cgpa"] ?? ""), 6) . " " .
        pdf_cell_value($student["email"] ?? "", 28);
}

if (count($students) === 0) {
    $allLines[] = "No students matched the selected filters.";
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

$filenameParts = ["students"];
if ($filters["department"] !== "") {
    $filenameParts[] = preg_replace('/[^a-z0-9]+/i', '-', strtolower($filters["department"]));
}
if ($filters["year"] !== "") {
    $filenameParts[] = "year-" . $filters["year"];
}
$filenameParts[] = date("Ymd_His");
$filename = implode("_", array_filter($filenameParts)) . ".pdf";

header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Content-Length: " . strlen($pdf));
echo $pdf;
exit();
