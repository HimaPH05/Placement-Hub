<?php
require_once __DIR__ . "/auth-check.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["confirm_logout"])) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    session_destroy();

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
    header("Location: ../login.php?logout=success");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Logout | Placement Hub</title>
  <link rel="stylesheet" href="astyle.css?v=20260309">
</head>
<body>
<header class="topbar">Admin Dashboard</header>
<nav class="navbar">
  <div class="logo">Placement Hub Admin</div>
  <div class="links">
    <a href="index.php">Home</a>
    <a href="company.php">Company</a>
    <a href="resume.php">Resumes</a>
    <a href="feedback.php">Feedback</a>
    <a href="profile.php">Admin Profile</a>
    <a href="logout.php" class="active">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="logout-card">
    <h2>Logout</h2>
    <p>Are you sure you want to end your admin session?</p>

    <form method="POST" class="logout-actions">
      <button type="submit" name="confirm_logout" class="primary">Yes, Logout</button>
      <a href="index.php" class="cancel">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
