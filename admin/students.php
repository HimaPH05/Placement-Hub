<?php
require_once __DIR__ . "/auth-check.php";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Student Management</title>
  <link rel="stylesheet" href="astyle.css?v=20260402">
  <script defer src="script.js?v=14"></script>
</head>

<body>
<header class="topbar">Admin Dashboard</header>
<nav class="navbar">
  <div class="logo">Placement Hub Admin</div>

  <div class="links">
    <a href="index.php">Home</a>
    <a href="students.php" class="active">Students</a>
    <a href="company.php">Company</a>
    <a href="applications.php">Applications</a>
    <a href="resume.php">Resumes</a>
    <a href="feedback.php">Feedback</a>
    <a href="profile.php">Admin Profile</a>
    <a href="logout.php" class="logout" id="logoutBtn">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-head">
    <h2>Registered Students</h2>
    <div class="student-toolbar">
      <input
        type="text"
        id="searchStudents"
        class="search"
        placeholder="Search student, branch, reg no..."
        onkeyup="searchAdminStudents()"
      >
      <select id="studentYearFilter" class="search student-filter-select" onchange="searchAdminStudents()">
        <option value="">All Years</option>
      </select>
      <select id="studentDepartmentFilter" class="search student-filter-select" onchange="searchAdminStudents()">
        <option value="">All Departments</option>
      </select>
      <button type="button" class="primary" onclick="downloadStudentPdf()">Download PDF</button>
    </div>
  </div>

  <p class="sub">Sorted by department/branch and year. Filter by year and department, then export the visible list as PDF.</p>

  <div id="studentList" class="student-grid"></div>
</div>

<div id="studentProfileModal" class="modal student-profile-modal">
  <div class="modal-box student-profile-box">
    <div class="student-profile-head">
      <h3 id="studentProfileTitle">Student Profile</h3>
      <button type="button" class="student-profile-close" onclick="closeStudentProfileModal()">Close</button>
    </div>
    <div id="studentProfileContent" class="student-profile-content">
      <p>Loading student details...</p>
    </div>
  </div>
</div>

</body>
</html>
