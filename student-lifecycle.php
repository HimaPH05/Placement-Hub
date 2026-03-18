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
    // Conservative: allow access until end of June of the graduation year.
    // Example: admission 2023 -> graduation year 2027 -> expires 2027-06-30
    $graduationYear = $admissionYear + 4;
    return sprintf("%04d-06-30", $graduationYear);
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

    $hasAdmissionDate = students_column_exists($conn, "admission_date");
    $hasAdmissionYear = students_column_exists($conn, "admission_year");

    $select = "SELECT access_expires_at";
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
    $admissionDate = trim((string)($row["admission_date"] ?? ""));
    $admissionYear = (int)($row["admission_year"] ?? 0);

    // Self-heal: if access_expires_at is missing but we have admission info, compute it once and persist.
    if (($expiresAt === "" || $expiresAt === "0000-00-00") && students_column_exists($conn, "access_expires_at")) {
        $computed = "";
        if ($admissionDate !== "" && $admissionDate !== "0000-00-00") {
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
