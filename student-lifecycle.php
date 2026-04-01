<?php
declare(strict_types=1);

function students_column_exists(mysqli $conn, string $column): bool
{
    $column = trim($column);
    if ($column === "") {
        return false;
    }

    $res = $conn->query("SHOW COLUMNS FROM students LIKE '" . $conn->real_escape_string($column) . "'");
    return $res && $res->num_rows > 0;
}

function student_lifecycle_compute_access_expiry_date(int $admissionYear): string
{
    // Conservative: allow access until end of September of the graduation year.
    // Example: admission 2023 -> graduation year 2027 -> expires 2027-09-30
    $graduationYear = $admissionYear + 4;
    return sprintf("%04d-09-30", $graduationYear);
}

function student_lifecycle_current_academic_year(): int
{
    $today = new DateTimeImmutable("today");
    $year = (int)$today->format("Y");
    $month = (int)$today->format("n");

    // Academic cycle assumed to start in June.
    return $month >= 6 ? $year : ($year - 1);
}

function student_lifecycle_compute_admission_year_from_current_year(int $currentYear): int
{
    if ($currentYear < 1) {
        $currentYear = 1;
    } elseif ($currentYear > 4) {
        $currentYear = 4;
    }

    return student_lifecycle_current_academic_year() - ($currentYear - 1);
}

function student_lifecycle_compute_access_expiry_from_current_year(int $currentYear): string
{
    $admissionYear = student_lifecycle_compute_admission_year_from_current_year($currentYear);
    return student_lifecycle_compute_access_expiry_date($admissionYear);
}

/**
 * Returns [allowed(bool), message(string)].
 * If DB is not migrated (no column), allow by default to avoid breaking existing installs.
 */
function enforce_student_not_expired(mysqli $conn, int $studentId): array
{
    if ($studentId <= 0) {
        return [false, "Please login as student first"];
    }

    if (!students_column_exists($conn, "access_expires_at")) {
        return [true, ""];
    }

    $hasCurrentYear = students_column_exists($conn, "current_year");
    $hasAdmissionDate = students_column_exists($conn, "admission_date");
    $hasAdmissionYear = students_column_exists($conn, "admission_year");

    $select = "SELECT access_expires_at";
    if ($hasCurrentYear) {
        $select .= ", current_year";
    } else {
        $select .= ", NULL AS current_year";
    }
    if ($hasAdmissionDate) {
        $select .= ", admission_date";
    } else {
        $select .= ", NULL AS admission_date";
    }
    if ($hasAdmissionYear) {
        $select .= ", admission_year";
    } else {
        $select .= ", NULL AS admission_year";
    }
    $select .= " FROM students WHERE id = ? LIMIT 1";

    $stmt = $conn->prepare($select);
    if (!$stmt) {
        return [true, ""];
    }
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $expiresAt = trim((string)($row["access_expires_at"] ?? ""));
    $currentYear = (int)($row["current_year"] ?? 0);
    $admissionDate = trim((string)($row["admission_date"] ?? ""));
    $admissionYear = (int)($row["admission_year"] ?? 0);

    // Self-heal: if access_expires_at is missing but we have admission info, compute it once and persist.
    if (($expiresAt === "" || $expiresAt === "0000-00-00") && students_column_exists($conn, "access_expires_at")) {
        $computed = "";
        if ($currentYear >= 1 && $currentYear <= 4) {
            $computed = student_lifecycle_compute_access_expiry_from_current_year($currentYear);
        } elseif ($admissionDate !== "" && $admissionDate !== "0000-00-00") {
            $dt = DateTimeImmutable::createFromFormat("Y-m-d", $admissionDate);
            if ($dt instanceof DateTimeImmutable) {
                $computed = $dt->modify("+4 years")->format("Y-m-d");
            }
        } elseif ($admissionYear > 0) {
            $computed = student_lifecycle_compute_access_expiry_date($admissionYear);
        }

        if ($computed !== "") {
            $u = $conn->prepare("UPDATE students SET access_expires_at = ? WHERE id = ? AND (access_expires_at IS NULL OR access_expires_at = '' OR access_expires_at = '0000-00-00')");
            if ($u) {
                $u->bind_param("si", $computed, $studentId);
                $u->execute();
                $u->close();
            }
            $expiresAt = $computed;
        }
    }

    if ($expiresAt === "" || $expiresAt === "0000-00-00") {
        return [true, ""];
    }

    $today = (new DateTimeImmutable("today"))->format("Y-m-d");
    if ($expiresAt < $today) {
        return [false, "Student access expired after graduation (access was valid till {$expiresAt})."];
    }

    return [true, ""];
}
