<?php
session_start();

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
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Logout | Placement Hub</title>
  <link rel="stylesheet" href="style.css?v=20260309">
</head>
<body>
<header class="topbar">Student Dashboard</header>
<nav class="navbar">
  <h2 class="logo">Placement Hub</h2>
  <div class="links">
    <a href="home.php">Home</a>
    <a href="companies.html">Companies</a>
    <a href="wishlist.html">Wishlist</a>
    <a href="feedback.html">Feedback</a>
    <a href="resumes.html">Resumes</a>
  </div>
</nav>

<div class="container">
  <div class="form-wrapper">
    <h1>Logout</h1>
    <p class="sub-text">Are you sure you want to end your student session?</p>
    <form method="POST" class="form-card">
      <button type="submit" name="confirm_logout" class="btn">Yes, Logout</button>
      <a href="home.php" class="btn secondary">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
