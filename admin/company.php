<?php
require_once __DIR__ . "/auth-check.php";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Company Management</title>
  <link rel="stylesheet" href="astyle.css?v=20260402-photo">
  <script defer src="script.js?v=20260402-photo"></script>
</head>

<body>
<header class="topbar">Admin Dashboard</header>
<nav class="navbar">
  <div class="logo">Placement Hub Admin</div>

  <div class="links">
    <a href="index.php">Home</a>
    <a href="company.php" class="active">Company</a>
    <a href="resume.php">Resumes</a>
    <a href="feedback.php">Feedback</a>
    <a href="profile.php">Admin Profile</a>
    <a href="logout.php" class="logout" id="logoutBtn">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-head">
    <h2>Company Management</h2>
    <button class="primary" onclick="openModal()">+ Add Company</button>
  </div>

  <div id="companyCredMsg" class="company-cred-msg" style="display:none;"></div>

  <div id="companyList" class="company-grid"></div>
</div>

<div id="modal" class="modal">
  <div class="modal-box">
    <h3>Add Company</h3>
    <input id="name" placeholder="Company Name">
    <input id="email" placeholder="HR Email">
    <input id="location" placeholder="Location">
    <input id="industry" placeholder="Industry">

    <div class="modal-actions">
      <button class="primary" onclick="addCompany()">Save</button>
      <button class="cancel" onclick="closeModal()">Cancel</button>
    </div>
  </div>
</div>

</body>
</html>
