<?php
require_once __DIR__ . "/auth-check.php";
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../admin-credentials.php";

function fetch_total(mysqli $conn, string $sql): int
{
  $result = $conn->query($sql);
  if (!$result) {
    return 0;
  }

  $row = $result->fetch_assoc();
  return (int)($row["total"] ?? 0);
}

$studentCount = fetch_total($conn, "SELECT COUNT(*) AS total FROM students");
$companyCount = fetch_total($conn, "SELECT COUNT(*) AS total FROM companies");
$publicResumeCount = fetch_total($conn, "SELECT COUNT(*) AS total FROM student_resumes WHERE visibility = 'public'");
$adminProfile = get_admin_profile();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Home - Placement Hub</title>
  <link rel="stylesheet" href="astyle.css?v=20260309">
  <script defer src="script.js?v=10"></script>
</head>

<body>
<header class="topbar">Admin Dashboard</header>
<nav class="navbar">
  <div class="logo">Placement Hub Admin</div>

  <div class="links">
    <a href="index.php" class="active">Home</a>
    <a href="company.php">Company</a>
    <a href="resume.php">Resumes</a>
    <a href="feedback.php">Feedback</a>
    <a href="profile.php">Admin Profile</a>
    <a href="logout.php" class="logout" id="logoutBtn">Logout</a>
  </div>
</nav>

<div class="container">

  <div class="dashboard-head">
    <h1>Placement Hub Dashboard</h1>
    <p class="sub">Government Engineering College Kozhikode</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card blue">
      <p>Students</p>
      <h2 id="studentCount"><?php echo $studentCount; ?></h2>
    </div>

    <div class="stat-card green">
      <p>Companies</p>
      <h2 id="companyCount"><?php echo $companyCount; ?></h2>
    </div>

    <div class="stat-card purple">
      <p>Public Resumes</p>
      <h2 id="resumeCount"><?php echo $publicResumeCount; ?></h2>
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
    <p><b>Name:</b> <?php echo htmlspecialchars($adminProfile["name"]); ?></p>
    <p><b>Email:</b> <?php echo htmlspecialchars($adminProfile["email"]); ?></p>
    <p><b>Phone:</b> <?php echo htmlspecialchars($adminProfile["phone"]); ?></p>
    <p><b>Office:</b> <?php echo htmlspecialchars($adminProfile["department"]); ?></p>
  </div>
</div>

</div>
</body>
</html>
