<?php

function admin_normalize_student_filters(array $input): array
{
    $search = trim((string)($input["search"] ?? ""));
    $department = trim((string)($input["department"] ?? ""));
    $year = trim((string)($input["year"] ?? ""));

    if (stripos($year, "year ") === 0) {
        $year = trim(substr($year, 5));
    }

    if (!preg_match('/^[1-4]$/', $year)) {
        $year = "";
    }

    return [
        "search" => $search,
        "department" => $department,
        "year" => $year
    ];
}

function admin_fetch_students(mysqli $conn, array $filters = []): array
{
    $normalizedFilters = admin_normalize_student_filters($filters);

    $hasEmailColumn = false;
    $hasDepartmentColumn = false;
    $hasCurrentYearColumn = false;
    $hasAdmissionYearColumn = false;
    $hasAccessExpiryColumn = false;

    $emailCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
    if ($emailCheck && $emailCheck->num_rows > 0) {
        $hasEmailColumn = true;
    }

    $departmentCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'department'");
    if ($departmentCheck && $departmentCheck->num_rows > 0) {
        $hasDepartmentColumn = true;
    }

    $currentYearCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'current_year'");
    if ($currentYearCheck && $currentYearCheck->num_rows > 0) {
        $hasCurrentYearColumn = true;
    }

    $admissionYearCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'admission_year'");
    if ($admissionYearCheck && $admissionYearCheck->num_rows > 0) {
        $hasAdmissionYearColumn = true;
    }

    $accessExpiryCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'access_expires_at'");
    if ($accessExpiryCheck && $accessExpiryCheck->num_rows > 0) {
        $hasAccessExpiryColumn = true;
    }

    $sql = "
        SELECT
            s.id,
            s.fullname,
            s.username,
            " . ($hasEmailColumn ? "COALESCE(s.email, '') AS email," : "'' AS email,") . "
            COALESCE(s.regno, '') AS regno,
            COALESCE(s.cgpa, '') AS cgpa,
            " . ($hasDepartmentColumn ? "COALESCE(s.department, '') AS department," : "'' AS department,") . "
            " . ($hasCurrentYearColumn ? "s.current_year," : "NULL AS current_year,") . "
            " . ($hasAdmissionYearColumn ? "s.admission_year," : "NULL AS admission_year,") . "
            " . ($hasAccessExpiryColumn ? "s.access_expires_at," : "NULL AS access_expires_at,") . "
            COALESCE((
                SELECT sr.branch
                FROM student_resumes sr
                WHERE sr.student_id = s.id
                ORDER BY (sr.visibility = 'public') DESC, sr.created_at DESC, sr.id DESC
                LIMIT 1
            ), 'N/A') AS branch
        FROM students s
        ORDER BY branch ASC, admission_year DESC, s.fullname ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $currentCalendarYear = (int)date("Y");
    $students = [];

    while ($row = $result->fetch_assoc()) {
        $registeredYear = (int)($row["current_year"] ?? 0);
        $admissionYear = (int)($row["admission_year"] ?? 0);
        $yearNumber = null;
        $yearLabel = "N/A";
        if ($registeredYear >= 1 && $registeredYear <= 4) {
            $yearNumber = $registeredYear;
            $yearLabel = "Year " . $registeredYear;
        } elseif ($admissionYear > 0) {
            $computedYear = ($currentCalendarYear - $admissionYear) + 1;
            if ($computedYear < 1) {
                $computedYear = 1;
            } elseif ($computedYear > 4) {
                $computedYear = 4;
            }
            $yearNumber = $computedYear;
            $yearLabel = "Year " . $computedYear;
        }

        $student = [
            "id" => (int)$row["id"],
            "fullname" => $row["fullname"],
            "username" => $row["username"],
            "email" => $row["email"],
            "regno" => $row["regno"],
            "cgpa" => $row["cgpa"],
            "department" => $row["department"],
            "branch" => $row["department"] !== "" ? $row["department"] : $row["branch"],
            "current_year" => $registeredYear >= 1 && $registeredYear <= 4 ? $registeredYear : null,
            "admission_year" => $admissionYear > 0 ? $admissionYear : null,
            "year_number" => $yearNumber,
            "year_label" => $yearLabel,
            "access_expires_at" => $row["access_expires_at"] ?? null
        ];

        $student = admin_student_sync_lifecycle($conn, $student);

        if (!admin_student_matches_filters($student, $normalizedFilters)) {
            continue;
        }

        $students[] = $student;
    }

    return $students;
}

function admin_student_sync_lifecycle(mysqli $conn, array $student): array
{
    require_once __DIR__ . "/../student-lifecycle.php";

    $studentId = (int)($student["id"] ?? 0);
    if ($studentId <= 0) {
        return $student;
    }

    $currentYear = (int)($student["current_year"] ?? 0);
    $admissionYear = (int)($student["admission_year"] ?? 0);
    $accessExpiresAt = trim((string)($student["access_expires_at"] ?? ""));
    $changes = [];

    if ($currentYear >= 1 && $currentYear <= 4 && $admissionYear <= 0) {
        $admissionYear = student_lifecycle_compute_admission_year_from_current_year($currentYear);
        $changes["admission_year"] = $admissionYear;
    }

    if (($currentYear <= 0 || $currentYear > 4) && $admissionYear > 0) {
        $computedYear = (int)date("Y") - $admissionYear + 1;
        if ($computedYear < 1) {
            $computedYear = 1;
        } elseif ($computedYear > 4) {
            $computedYear = 4;
        }
        $currentYear = $computedYear;
        $changes["current_year"] = $currentYear;
    }

    if (($accessExpiresAt === "" || $accessExpiresAt === "0000-00-00") && $admissionYear > 0) {
        $accessExpiresAt = student_lifecycle_compute_access_expiry_date($admissionYear);
        $changes["access_expires_at"] = $accessExpiresAt;
    }

    if (!empty($changes)) {
        $setParts = [];
        $types = "";
        $params = [];

        foreach ($changes as $column => $value) {
            $setParts[] = "{$column} = ?";
            if ($column === "admission_year" || $column === "current_year") {
                $types .= "i";
                $params[] = (int)$value;
            } else {
                $types .= "s";
                $params[] = (string)$value;
            }
        }

        $types .= "i";
        $params[] = $studentId;

        $stmt = $conn->prepare("UPDATE students SET " . implode(", ", $setParts) . " WHERE id = ?");
        if ($stmt) {
            $bind = [$types];
            foreach ($params as $index => $value) {
                $bind[] = &$params[$index];
            }
            call_user_func_array([$stmt, "bind_param"], $bind);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($currentYear >= 1 && $currentYear <= 4) {
        $student["current_year"] = $currentYear;
        $student["year_number"] = $currentYear;
        $student["year_label"] = "Year " . $currentYear;
    }

    if ($admissionYear > 0) {
        $student["admission_year"] = $admissionYear;
    }

    if ($accessExpiresAt !== "" && $accessExpiresAt !== "0000-00-00") {
        $student["access_expires_at"] = $accessExpiresAt;
    }

    return $student;
}

function admin_fetch_student_detail(mysqli $conn, int $studentId): ?array
{
    if ($studentId <= 0) {
        return null;
    }

    $students = admin_fetch_students($conn, ["search" => "", "department" => "", "year" => ""]);
    $student = null;
    foreach ($students as $item) {
        if ((int)($item["id"] ?? 0) === $studentId) {
            $student = $item;
            break;
        }
    }

    if ($student === null) {
        return null;
    }

    $hasScorecardColumn = false;
    $hasDobColumn = false;
    $hasCreatedAtColumn = false;
    $hasEmailVerifiedColumn = false;

    $scorecardCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'ktu_scorecard_path'");
    if ($scorecardCheck && $scorecardCheck->num_rows > 0) {
        $hasScorecardColumn = true;
    }

    $dobCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'dob'");
    if ($dobCheck && $dobCheck->num_rows > 0) {
        $hasDobColumn = true;
    }

    $createdAtCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'created_at'");
    if ($createdAtCheck && $createdAtCheck->num_rows > 0) {
        $hasCreatedAtColumn = true;
    }

    $emailVerifiedCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'is_email_verified'");
    if ($emailVerifiedCheck && $emailVerifiedCheck->num_rows > 0) {
        $hasEmailVerifiedColumn = true;
    }

    $detailSql = "
        SELECT
            s.id,
            s.fullname,
            s.username,
            COALESCE(s.regno, '') AS regno,
            COALESCE(s.cgpa, '') AS cgpa,
            " . ($hasDobColumn ? "s.dob," : "NULL AS dob,") . "
            " . ($hasScorecardColumn ? "COALESCE(s.ktu_scorecard_path, '') AS ktu_scorecard_path," : "'' AS ktu_scorecard_path,") . "
            " . ($hasCreatedAtColumn ? "s.created_at," : "NULL AS created_at,") . "
            " . ($hasEmailVerifiedColumn ? "COALESCE(s.is_email_verified, 0) AS is_email_verified" : "0 AS is_email_verified") . "
        FROM students s
        WHERE s.id = ?
        LIMIT 1
    ";

    $detailStmt = $conn->prepare($detailSql);
    if ($detailStmt) {
        $detailStmt->bind_param("i", $studentId);
        $detailStmt->execute();
        $detailRow = $detailStmt->get_result()->fetch_assoc();
        if ($detailRow) {
            $student["dob"] = $detailRow["dob"] ?? null;
            $student["created_at"] = $detailRow["created_at"] ?? null;
            $student["is_email_verified"] = ((int)($detailRow["is_email_verified"] ?? 0)) === 1;
            $student["ktu_scorecard_path"] = $detailRow["ktu_scorecard_path"] ?? "";
            $student["scorecard_url"] = !empty($detailRow["ktu_scorecard_path"])
                ? "../" . ltrim((string)$detailRow["ktu_scorecard_path"], "/")
                : "";
        }
    }

    $hasResumeVerifiedCol = false;
    $hasResumeRejectedCol = false;
    $resumeVerifiedCheck = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_verified'");
    if ($resumeVerifiedCheck && $resumeVerifiedCheck->num_rows > 0) {
        $hasResumeVerifiedCol = true;
    }
    $resumeRejectedCheck = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_rejected'");
    if ($resumeRejectedCheck && $resumeRejectedCheck->num_rows > 0) {
        $hasResumeRejectedCol = true;
    }

    $resumeSql = "
        SELECT
            sr.id,
            sr.name,
            sr.branch,
            sr.gpa,
            sr.skills,
            sr.about,
            sr.file_name,
            sr.visibility,
            sr.created_at,
            " . ($hasResumeVerifiedCol ? "COALESCE(sr.is_verified, 0) AS is_verified," : "0 AS is_verified,") . "
            " . ($hasResumeRejectedCol ? "COALESCE(sr.is_rejected, 0) AS is_rejected" : "0 AS is_rejected") . "
        FROM student_resumes sr
        WHERE sr.student_id = ?
        ORDER BY sr.created_at DESC, sr.id DESC
    ";

    $resumeStmt = $conn->prepare($resumeSql);
    $resumes = [];
    $latestResume = null;
    if ($resumeStmt) {
        $resumeStmt->bind_param("i", $studentId);
        $resumeStmt->execute();
        $resumeResult = $resumeStmt->get_result();
        while ($resumeRow = $resumeResult->fetch_assoc()) {
            $resume = [
                "id" => (int)$resumeRow["id"],
                "name" => $resumeRow["name"],
                "branch" => $resumeRow["branch"],
                "gpa" => $resumeRow["gpa"],
                "skills" => $resumeRow["skills"],
                "about" => $resumeRow["about"],
                "file_name" => $resumeRow["file_name"],
                "visibility" => $resumeRow["visibility"],
                "created_at" => $resumeRow["created_at"],
                "is_verified" => ((int)($resumeRow["is_verified"] ?? 0)) === 1,
                "is_rejected" => ((int)($resumeRow["is_rejected"] ?? 0)) === 1,
                "view_url" => "../view_resume.php?id=" . (int)$resumeRow["id"],
                "download_url" => "../view_resume.php?id=" . (int)$resumeRow["id"] . "&dl=1"
            ];
            $resumes[] = $resume;
            if ($latestResume === null) {
                $latestResume = $resume;
            }
        }
    }

    $applicationSql = "
        SELECT
            a.id,
            a.status,
            a.applied_at,
            COALESCE(j.job_title, '') AS job_title,
            COALESCE(NULLIF(c.companyName, ''), c.username) AS company_name
        FROM applications a
        LEFT JOIN jobs j ON j.id = a.job_id
        LEFT JOIN companies c ON c.id = a.company_id
        WHERE a.student_id = ?
        ORDER BY a.applied_at DESC, a.id DESC
    ";

    $applicationStmt = $conn->prepare($applicationSql);
    $applications = [];
    $applicationSummary = [
        "total" => 0,
        "pending" => 0,
        "shortlisted" => 0,
        "placed" => 0,
        "rejected" => 0,
        "cancelled" => 0
    ];
    if ($applicationStmt) {
        $applicationStmt->bind_param("i", $studentId);
        $applicationStmt->execute();
        $applicationResult = $applicationStmt->get_result();
        while ($applicationRow = $applicationResult->fetch_assoc()) {
            $applications[] = [
                "id" => (int)$applicationRow["id"],
                "company_name" => $applicationRow["company_name"],
                "job_title" => $applicationRow["job_title"],
                "status" => $applicationRow["status"],
                "applied_at" => $applicationRow["applied_at"]
            ];

            $applicationSummary["total"]++;
            $statusKey = strtolower(trim((string)($applicationRow["status"] ?? "")));
            if (array_key_exists($statusKey, $applicationSummary)) {
                $applicationSummary[$statusKey]++;
            }
        }
    }

    $student["has_resume"] = $latestResume !== null;
    $student["latest_resume"] = $latestResume;
    $student["resumes"] = $resumes;
    $student["resume_count"] = count($resumes);
    $student["applications"] = $applications;
    $student["application_summary"] = $applicationSummary;
    $student["is_placed"] = $applicationSummary["placed"] > 0;
    $student["latest_status"] = !empty($applications) ? ($applications[0]["status"] ?? "No Applications") : "No Applications";

    return $student;
}

function admin_student_matches_filters(array $student, array $filters): bool
{
    $departmentFilter = strtolower(trim((string)($filters["department"] ?? "")));
    if ($departmentFilter !== "") {
        $branch = strtolower(trim((string)($student["branch"] ?? "")));
        if ($branch !== $departmentFilter) {
            return false;
        }
    }

    $yearFilter = trim((string)($filters["year"] ?? ""));
    if ($yearFilter !== "") {
        $studentYear = (string)($student["year_number"] ?? "");
        if ($studentYear !== $yearFilter) {
            return false;
        }
    }

    $search = strtolower(trim((string)($filters["search"] ?? "")));
    if ($search === "") {
        return true;
    }

    $haystacks = [
        (string)($student["fullname"] ?? ""),
        (string)($student["username"] ?? ""),
        (string)($student["email"] ?? ""),
        (string)($student["regno"] ?? ""),
        (string)($student["branch"] ?? ""),
        (string)($student["year_label"] ?? "")
    ];

    foreach ($haystacks as $value) {
        if (strpos(strtolower($value), $search) !== false) {
            return true;
        }
    }

    return false;
}
