<?php
require_once __DIR__ . "/auth-check.php";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Home - Placement Hub</title>
  <link rel="stylesheet" href="astyle.css">
  <script defer src="script.js?v=10"></script>
</head>

<body>
<header class="navbar">
  <div class="logo">Placement Hub Admin</div>

  <nav>
    <a href="index.php" class="active">Home</a>
    <a href="company.php">Company</a>
    <a href="resume.php">Resumes</a>
    <a href="feedback.php">Feedback</a>
    <a href="profile.php">Admin Profile</a>
    <a href="../logout.php" class="logout" id="logoutBtn">Logout</a>
  </nav>
</header>

<div class="container">

  <div class="dashboard-head">
    <h1>Placement Hub Dashboard</h1>
    <p class="sub">Government Engineering College Kozhikode</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card blue">
      <p>Students</p>
      <h2 id="studentCount">0</h2>
    </div>

    <div class="stat-card green">
      <p>Companies</p>
      <h2 id="companyCount">0</h2>
    </div>

    <div class="stat-card purple">
      <p>Resumes</p>
      <h2 id="resumeCount">0</h2>
    </div>

    <div class="stat-card orange">
      <p>Placements</p>
      <h2 id="placementCount">0</h2>
      <div class="placement-controls">
        <input type="number" id="placementInput" min="0" value="0" aria-label="Placement count">
        <button type="button" class="primary" id="savePlacementBtn">Save</button>
      </div>
      <p id="placementMsg" class="placement-msg"></p>
    </div>
  </div>

<div class="info-grid">
  <div class="info-card">
    <div class="info-icon">*</div>
    <h3>About Placement Team</h3>
    <p>
      We connect students with top recruiters through campus hiring,
      resume verification, interview scheduling and placement tracking.
    </p>
  </div>

  <div class="info-card">
    <div class="info-icon">*</div>
    <h3>Contact Details</h3>
    <p><b>Email:</b> placement@university.edu</p>
    <p><b>Phone:</b> +91 9876543210</p>
    <p><b>Office:</b> Training & Placement Cell</p>
  </div>
</div>

</div>
</body>
</html>
