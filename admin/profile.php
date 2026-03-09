<?php
require_once __DIR__ . "/auth-check.php";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Profile</title>
  <link rel="stylesheet" href="astyle.css">
  <script defer src="script.js?v=10"></script>
</head>

<body>
<header class="navbar">
  <div class="logo">Placement Hub Admin</div>

  <nav>
    <a href="index.php">Home</a>
    <a href="company.php">Company</a>
    <a href="resume.php">Resumes</a>
    <a href="feedback.php">Feedback</a>
    <a href="profile.php" class="active">Admin Profile</a>
    <a href="../logout.php" class="logout" id="logoutBtn">Logout</a>
  </nav>
</header>

<div class="container">
  <h2 class="page-title">Admin Profile</h2>

  <div class="profile-card">
    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="profile-avatar">

    <div class="profile-info">
      <h3>Admin User</h3>
      <p><b>Email:</b> admin@university.edu</p>
      <p><b>Role:</b> Placement Coordinator</p>
      <p><b>Department:</b> Placement Cell</p>
    </div>
  </div>

  <h3 class="section-title">Placement Team Members</h3>

  <div class="team-grid">
    <div class="team-card">
      <h4>Dr. John Smith</h4>
      <p>Head Coordinator</p>
    </div>

    <div class="team-card">
      <h4>Sarah Johnson</h4>
      <p>Assistant Coordinator</p>
    </div>

    <div class="team-card">
      <h4>Emily Davis</h4>
      <p>Industry Liaison</p>
    </div>

    <div class="team-card">
      <h4>David Wilson</h4>
      <p>Student Relations</p>
    </div>
  </div>

</div>

</body>
</html>
