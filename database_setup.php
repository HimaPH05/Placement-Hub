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
?>