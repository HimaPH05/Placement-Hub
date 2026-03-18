-- Placement-Hub: reset test data (keeps schema)
-- Use in phpMyAdmin (SQL tab) OR locally with mysql client.

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE applications;
TRUNCATE TABLE student_feedback;
TRUNCATE TABLE student_resumes;
TRUNCATE TABLE jobs;
TRUNCATE TABLE hr_contacts;
TRUNCATE TABLE company_profiles;
TRUNCATE TABLE companies;
TRUNCATE TABLE students;

SET FOREIGN_KEY_CHECKS = 1;

