<?php
$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) die("Connection failed");

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
?>
