# Placement Hub

Placement Hub is a multi-role campus placement management system for:
- Students
- Companies
- Admin / Placement Cell

It supports registration, role-based login, company job postings, student job applications, resume upload and verification, feedback collection, and placement analytics.

## Features

### Student module
- Student signup and login
- View companies and latest openings
- Apply to jobs (with eligibility checks)
- Track application statuses (`Pending`, `Shortlisted`, `Rejected`, `Cancelled`)
- Cancel active applications
- Upload/edit resume (PDF/DOC/DOCX)
- Resume visibility toggle (`public`/`private`)
- Resume verification status view
- Upload KTU scorecard from profile (if column is enabled)
- Wishlist support (browser local storage)
- Submit feedback to placement cell

### Company module
- Company signup and login
- Company profile management
- Add/edit/delete job postings
- Set minimum CGPA and location per role
- View applicant list with profile data
- Open resumes and scorecards
- Mark applicants as shortlisted/rejected
- Gmail compose flow for sending status emails

### Admin module
- Admin login using JSON credentials file
- Dashboard stats:
  - Total students
  - Total companies
  - Public resumes
  - Placement count (manually set)
- Add/delete companies
- Auto-generate temporary company credentials
- View and verify/reject public resumes
- View student feedback
- Manage admin profile
- Manage placement team members (dynamic add/remove)

## Tech stack

- PHP (procedural + mysqli)
- MySQL / MariaDB
- HTML, CSS, JavaScript (vanilla)
- XAMPP (recommended local environment)

## Project structure

```text
Placement-Hub/
|- admin/                  # Admin dashboard, company + resume + feedback controls
|- company/                # Company dashboard, jobs, applicants, resumes
|- Student/                # Student dashboard, applications, resume, feedback, wishlist
|- uploads/
|  |- resumes/
|  |- scorecards/
|- admin_credentials.json  # Admin account, profile and team members
|- admin-credentials.php   # Admin credential/profile/team helpers
|- admin-auth.php          # Admin login API
|- placementhub.php        # Student/company login API
|- student-signup.php      # Student registration API
|- company-signup.php      # Company registration API
|- database_setup.php      # Table creation + schema migrations
|- login.php               # Main login page
```

## Prerequisites

- XAMPP / Apache + MySQL running
- PHP 8.x (works with XAMPP PHP)
- MySQL database named `detailsdb`

## Setup guide

### 1. Place project in web root

For XAMPP:
- project path should be inside `htdocs`, e.g. `C:\xampp\htdocs\MINI1\Placement-Hub`

### 2. Create database

Create database in MySQL:

```sql
CREATE DATABASE IF NOT EXISTS detailsdb;
```

### 3. Create base tables (`students`, `companies`)

`database_setup.php` creates many supporting tables, but `students` and `companies` should exist first.

Run this SQL once:

```sql
USE detailsdb;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(120) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(150) NOT NULL,
  regno VARCHAR(80) NOT NULL UNIQUE,
  dob DATE NOT NULL,
  cgpa DECIMAL(4,2) NOT NULL,
  ktu_scorecard_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  companyName VARCHAR(180) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT '',
  location VARCHAR(120) NULL,
  industry VARCHAR(120) NULL,
  website VARCHAR(255) DEFAULT '',
  description TEXT NULL,
  employees INT NOT NULL DEFAULT 0,
  locations INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 4. Run schema setup/migrations

Open this once in browser:

`http://localhost/MINI1/Placement-Hub/database_setup.php`

This creates/migrates:
- `jobs`
- `applications`
- `student_resumes`
- `company_profiles`
- `hr_contacts`
- resume verification columns
- optional migration columns used by dashboards

### 5. Start application

Open:

`http://localhost/MINI1/Placement-Hub/login.php`

## Default / admin credentials

Admin login is stored in `admin_credentials.json`.

If this file does not exist, default admin is auto-created by `admin-credentials.php`:
- Username: `admin`
- Password: `admin@geck`

You can update password from:
- `admin-password.php?mode=change`

## Main flows

### Student flow
1. Register from `student-signup.html`
2. Login from `login.php`
3. Update profile + upload scorecard (optional but required for apply if scorecard column is enabled)
4. Upload resume from `Student/submit-resume.html`
5. Explore companies and apply
6. Track status in `Student/applications.php`

### Company flow
1. Register from `company-signup.html`
2. Login from `login.php`
3. Update company details
4. Add job postings
5. Review applicants
6. Update status and send email via Gmail compose

### Admin flow
1. Login as admin from `login.php`
2. Monitor stats on dashboard
3. Manage companies
4. Verify/reject resumes
5. Review feedback
6. Update placement count, profile, and team members

## Important endpoints

### Authentication
- `placementhub.php` -> student/company login
- `admin-auth.php` -> admin login
- `student-signup.php` -> student registration
- `company-signup.php` -> company registration

### Student APIs
- `Student/get_companies.php`
- `Student/get_company_openings.php`
- `Student/apply.php`
- `Student/get_resumes.php`
- `Student/submit_resume.php`
- `Student/update_resume_visibility.php`
- `Student/delete_resume.php`
- `Student/submit_feedback.php`

### Admin APIs
- `admin/company-actions.php`
- `admin/resume-actions.php`
- `admin/dashboard-stats.php`
- `admin/update-placement.php`

### Shared viewer
- `view_resume.php` (role-based resume access control)

## Security and validation implemented

- Password hashing using `password_hash()` / `password_verify()`
- Session-based role access checks
- Prepared statements used in core DB operations
- Basic input validation on auth and CRUD endpoints
- Resume access restrictions in `view_resume.php`

## Notes and limitations

- Wishlist is stored in browser `localStorage` (not in DB).
- Some migration behavior is runtime-triggered by specific pages/endpoints.
- Project uses direct mysqli + procedural PHP style (not framework-based).
- Add `.htaccess` and stronger upload hardening before production deployment.

## Future improvements

- Move credentials and DB config to `.env`
- Add CSRF protection on all POST forms
- Add centralized router/API response format
- Add automated tests
- Add role-based middleware abstraction
- Add email sending backend (SMTP) instead of compose-only flow

## Contributors

Developed as a campus placement management project for Government Engineering College Kozhikode.
