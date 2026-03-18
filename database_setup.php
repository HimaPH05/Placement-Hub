<?php
$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) die("Connection failed");

/* STUDENT EMAIL VERIFICATION COLUMNS (if students table exists) */
$studentsTable = $conn->query("SHOW TABLES LIKE 'students'");
if ($studentsTable && $studentsTable->num_rows > 0) {
    $verifyCols = [
        "is_email_verified" => "ALTER TABLE students ADD COLUMN is_email_verified TINYINT(1) NOT NULL DEFAULT 0",
        "email_verified_at" => "ALTER TABLE students ADD COLUMN email_verified_at DATETIME NULL",
        "email_verify_token_hash" => "ALTER TABLE students ADD COLUMN email_verify_token_hash CHAR(64) NULL",
        "email_verify_expires_at" => "ALTER TABLE students ADD COLUMN email_verify_expires_at DATETIME NULL"
    ];

    foreach ($verifyCols as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM students LIKE '{$col}'");
        if ($check && $check->num_rows === 0) {
            $conn->query($sql);
        }
    }

    /* STUDENT GRADUATION EXPIRY COLUMNS */
    $lifecycleCols = [
        "admission_year" => "ALTER TABLE students ADD COLUMN admission_year INT NULL",
        "admission_date" => "ALTER TABLE students ADD COLUMN admission_date DATE NULL",
        "access_expires_at" => "ALTER TABLE students ADD COLUMN access_expires_at DATE NULL"
    ];
    foreach ($lifecycleCols as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM students LIKE '{$col}'");
        if ($check && $check->num_rows === 0) {
            $conn->query($sql);
        }
    }
}

/* COMPANIES TABLE MIGRATIONS (for dashboard fields) */
$companyCols = [
    "description" => "ALTER TABLE companies ADD COLUMN description TEXT NULL",
    "employees"   => "ALTER TABLE companies ADD COLUMN employees INT NOT NULL DEFAULT 0",
    "locations"   => "ALTER TABLE companies ADD COLUMN locations INT NOT NULL DEFAULT 0",
    "location"    => "ALTER TABLE companies ADD COLUMN location VARCHAR(120) NULL",
    "industry"    => "ALTER TABLE companies ADD COLUMN industry VARCHAR(120) NULL"
];
foreach ($companyCols as $col => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM companies LIKE '{$col}'");
    if ($check && $check->num_rows === 0) {
        $conn->query($sql);
    }
}

/* COMPANY PROFILE */
$conn->query("CREATE TABLE IF NOT EXISTS company_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    description TEXT,
    employees_count INT,
    locations_count INT,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
)");

/* JOBS TABLE */
$conn->query("CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    job_title VARCHAR(100) NOT NULL,
    job_description TEXT NOT NULL,
    openings INT NOT NULL,
    salary VARCHAR(50),
    location VARCHAR(100),
    deadline DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
)");

/* HR CONTACT */
$conn->query("CREATE TABLE IF NOT EXISTS hr_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    hr_name VARCHAR(100),
    hr_email VARCHAR(100),
    hr_phone VARCHAR(20),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
)");

/* JOBS TABLE MIGRATION: add min_cgpa if missing */
$minCgpaCol = $conn->query("SHOW COLUMNS FROM jobs LIKE 'min_cgpa'");
if ($minCgpaCol && $minCgpaCol->num_rows === 0) {
    $conn->query("ALTER TABLE jobs ADD COLUMN min_cgpa DECIMAL(4,2) NULL AFTER openings");
}

/* APPLICATIONS TABLE */
$conn->query("CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    company_id INT NOT NULL,
    job_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_job (student_id, job_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
)");

/* STUDENT RESUMES TABLE */
$conn->query("CREATE TABLE IF NOT EXISTS student_resumes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    branch VARCHAR(100) NOT NULL,
    gpa VARCHAR(20) NOT NULL,
    about TEXT,
    skills TEXT,
    file_name VARCHAR(255),
    mime_type VARCHAR(120),
    file_data LONGBLOB,
    file_path VARCHAR(255),
    visibility ENUM('public','private') NOT NULL DEFAULT 'private',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_visibility_created (visibility, created_at)
)");

/* APPLICATIONS TABLE MIGRATION: add resume_id if missing */
$resumeIdCol = $conn->query("SHOW COLUMNS FROM applications LIKE 'resume_id'");
if ($resumeIdCol && $resumeIdCol->num_rows === 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN resume_id INT NULL AFTER job_id");
}

/* STUDENT RESUME VERIFICATION COLUMNS */
$resumeVerifiedCol = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_verified'");
if ($resumeVerifiedCol && $resumeVerifiedCol->num_rows === 0) {
    $conn->query("ALTER TABLE student_resumes ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER visibility");
}

$resumeVerifiedAtCol = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'verified_at'");
if ($resumeVerifiedAtCol && $resumeVerifiedAtCol->num_rows === 0) {
    $conn->query("ALTER TABLE student_resumes ADD COLUMN verified_at DATETIME NULL AFTER is_verified");
}

$resumeRejectedCol = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_rejected'");
if ($resumeRejectedCol && $resumeRejectedCol->num_rows === 0) {
    $conn->query("ALTER TABLE student_resumes ADD COLUMN is_rejected TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified");
}

$resumeRejectedAtCol = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'rejected_at'");
if ($resumeRejectedAtCol && $resumeRejectedAtCol->num_rows === 0) {
    $conn->query("ALTER TABLE student_resumes ADD COLUMN rejected_at DATETIME NULL AFTER verified_at");
}
?>
