<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["confirm_logout"])) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Logout - Placement Hub</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="company_logout.css">
</head>
<body>

<header class="topbar">
  Company Dashboard
</header>

<nav class="navbar">
  <a href="home.php">Home</a>
  <a href="company.php">Company</a>
  <a href="applicants.php">Applicants</a>
  <a href="resumes.php">Resumes</a>
</nav>

<div class="container">
  <div class="card logout-card">
    <h2>Logout</h2>
    <p>Are you sure you want to end your company session?</p>

    <form method="POST" class="logout-actions">
      <button type="submit" name="confirm_logout" class="btn">Yes, Logout</button>
      <a href="home.php" class="cancel-btn">Cancel</a>
    </form>
  </div>
</div>

</body>
</html>
