<?php
session_start();

if (isset($_SESSION["role"])) {
    if ($_SESSION["role"] === "admin") {
        header("Location: admin/index.php");
        exit();
    }

    if ($_SESSION["role"] === "company") {
        header("Location: company/home.php");
        exit();
    }

    if ($_SESSION["role"] === "student") {
        header("Location: Student/home.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Placement Hub Login</title>
<link rel="stylesheet" href="login.css?v=2">
</head>

<body>

<header class="college-header">

<img src="logo.jpg" class="college-logo">

<h1 class="college-name">
Government Engineering College Kozhikode
</h1>

<p class="college-text">
Accredited with NBA (AE&I, CHE, CE, ME, ECE) and ISO 9001:2015 Certified
</p>

<p class="college-text-small">
Under Section 2(f) of UGC Act 1956 (Approved by AICTE & Affiliated to APJ Abdul Kalam Technological University)
</p>

</header>


<div class="container">

<form id="loginForm" class="card">

<h2>Placement Hub</h2>
<h3>Login</h3>

<label for="username">Username</label>
<input type="text" id="username" placeholder="Enter username" required>

<label for="password">Password</label>

<div class="password-wrapper">
<input type="password" id="password" placeholder="Enter password" required>
<span id="togglePassword">&#128065;</span>
</div>

<select id="role" name="role" required>
<option value="">Select Role</option>
<option value="student">Student</option>
<option value="company">Company</option>
<option value="admin">Admin</option>
</select>

<button type="submit">Login</button>

<div id="changePasswordSection" class="change-password-section">
<a id="changePasswordLink" href="#">Change Password</a>
<span>|</span>
<a id="forgotPasswordLink" href="#">Forgot Password</a>
</div>

<p id="message" class="error"></p>
<p id="logoutMessage" style="color:green; font-weight:bold; text-align:center;"></p>

<div class="signup-section">
<p>Don't have an account?</p>
<a href="student-signup.html">Sign up as Student</a>
<span>|</span>
<a href="company-signup.html">Sign up as Company</a>
</div>

</form>

</div>

<script src="login.js?v=3"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const params = new URLSearchParams(window.location.search);
  const logoutMsg = document.getElementById("logoutMessage");
  if (params.get("logout") === "success") {
    logoutMsg.textContent = "Logged out successfully!";
    window.history.pushState(null, "", window.location.href);
    window.addEventListener("popstate", function () {
      window.history.pushState(null, "", window.location.href);
    });
  }
});
</script>

</body>
</html>
