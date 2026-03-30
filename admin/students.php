<?php
require_once __DIR__ . "/auth-check.php";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Student Management</title>
  <link rel="stylesheet" href="astyle.css?v=20260309">
  <script defer src="script.js?v=11"></script>
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
    <input
      type="text"
      id="searchStudents"
      class="search"
      placeholder="Search student, branch, reg no..."
      onkeyup="searchAdminStudents()"
    >
  </div>

  <p class="sub">Sorted by department/branch and year.</p>

  <div id="studentList" class="student-grid"></div>
</div>

</body>
</html>
