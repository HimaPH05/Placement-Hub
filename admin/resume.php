<?php
require_once __DIR__ . "/auth-check.php";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Resume Management</title>
  <link rel="stylesheet" href="astyle.css">
  <script defer src="script.js?v=8"></script>
</head>

<body>
<header class="navbar">
  <div class="logo">Placement Hub Admin</div>

  <nav>
    <a href="index.php">Home</a>
    <a href="company.php">Company</a>
    <a href="resume.php" class="active">Resumes</a>
    <a href="feedback.php">Feedback</a>
    <a href="profile.php">Admin Profile</a>
    <a href="../logout.php" class="logout" id="logoutBtn">Logout</a>
  </nav>
</header>

<div class="container">
  <div class="page-head">
    <h2>Resume Management</h2>

    <input
      type="text"
      id="searchResume"
      class="search"
      placeholder="Search student..."
      onkeyup="searchResume()"
    >
  </div>

  <div id="resumeList" class="resume-grid"></div>
</div>

</body>
</html>
