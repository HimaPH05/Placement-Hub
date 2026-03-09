<?php
session_start();
require_once __DIR__ . "/../admin-credentials.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: ../login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = (int)$_SESSION["user_id"];

$hasScorecardColumn = false;
$hasEmailColumn = false;

$colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'ktu_scorecard_path'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasScorecardColumn = true;
}

$emailColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
if ($emailColCheck && $emailColCheck->num_rows > 0) {
    $hasEmailColumn = true;
}

if ($hasScorecardColumn) {
    if ($hasEmailColumn) {
        $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, ktu_scorecard_path FROM students WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa, ktu_scorecard_path FROM students WHERE id = ?");
    }
} else {
    if ($hasEmailColumn) {
        $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa FROM students WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa FROM students WHERE id = ?");
    }
}

$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    die("Student not found");
}

if ($hasScorecardColumn) {
    $stmt->bind_result($fullname, $email, $regno, $cgpa, $ktu_scorecard_path);
} else {
    $stmt->bind_result($fullname, $email, $regno, $cgpa);
    $ktu_scorecard_path = "";
}
$stmt->fetch();

$student = [
    "fullname" => $fullname,
    "email" => $email,
    "regno" => $regno,
    "cgpa" => $cgpa,
    "ktu_scorecard_path" => $ktu_scorecard_path
];

$hasResumeVerifyCol = false;
$resumeVerifyColCheck = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_verified'");
if ($resumeVerifyColCheck && $resumeVerifyColCheck->num_rows > 0) {
    $hasResumeVerifyCol = true;
}
$hasResumeRejectCol = false;
$resumeRejectColCheck = $conn->query("SHOW COLUMNS FROM student_resumes LIKE 'is_rejected'");
if ($resumeRejectColCheck && $resumeRejectColCheck->num_rows > 0) {
    $hasResumeRejectCol = true;
}

$latestResumeStatus = "Not Submitted";
$latestResumeVisibility = "";
$showResumeStatus = true;
if ($hasResumeVerifyCol || $hasResumeRejectCol) {
    $verifySelect = $hasResumeVerifyCol
        ? "COALESCE(is_verified, 0) AS is_verified"
        : "0 AS is_verified";
    $rejectSelect = $hasResumeRejectCol
        ? "COALESCE(is_rejected, 0) AS is_rejected"
        : "0 AS is_rejected";

    $resumeStmt = $conn->prepare("
        SELECT visibility, {$verifySelect}, {$rejectSelect}
        FROM student_resumes
        WHERE student_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $resumeStmt->bind_param("i", $student_id);
    $resumeStmt->execute();
    $resumeRow = $resumeStmt->get_result()->fetch_assoc();

    if ($resumeRow) {
        $latestResumeVisibility = strtolower((string)($resumeRow["visibility"] ?? ""));
        $isVerified = ((int)($resumeRow["is_verified"] ?? 0)) === 1;
        $isRejected = ((int)($resumeRow["is_rejected"] ?? 0)) === 1;

        if ($latestResumeVisibility === "private") {
            $showResumeStatus = false;
        } elseif ($isRejected) {
            $latestResumeStatus = "Rejected";
        } elseif ($isVerified) {
            $latestResumeStatus = "Verified";
        } else {
            $latestResumeStatus = "Pending Verification";
        }
    }
}

$adminProfile = get_admin_profile();
$teamMembers = get_admin_team_members();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Student Home - Placement Hub</title>
  <link rel="stylesheet" href="style.css?v=20260309">
</head>

<body>
<header class="topbar">Student Dashboard</header>

<nav class="navbar">
  <div class="logo">Placement Hub</div>

  <div class="links">
    <a href="home.php" class="active">Home</a>
    <a href="companies.html">Companies</a>
    <a href="applications.php">My Applications</a>
    <a href="wishlist.html">Wishlist</a>
    <a href="feedback.html">Feedback</a>
    <a href="resumes.html">Resumes</a>
  </div>

  <div class="profile-container">
    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="profile-icon" onclick="toggleProfile()">

    <div id="profileDropdown" class="profile-dropdown">
      <p><strong><?php echo htmlspecialchars($student["fullname"]); ?></strong></p>
      <?php if ($hasEmailColumn): ?>
        <p>Email: <?php echo htmlspecialchars($student["email"]); ?></p>
      <?php endif; ?>
      <p>Reg No: <?php echo htmlspecialchars($student["regno"]); ?></p>
      <p>CGPA: <?php echo htmlspecialchars($student["cgpa"]); ?></p>
      <?php if ($showResumeStatus): ?>
        <p>
          Resume Status: <?php echo htmlspecialchars($latestResumeStatus); ?>
          <?php if ($latestResumeVisibility !== ""): ?>
            (<?php echo htmlspecialchars(ucfirst($latestResumeVisibility)); ?>)
          <?php endif; ?>
        </p>
      <?php endif; ?>
      <?php if ($hasScorecardColumn): ?>
        <p>
          KTU Scorecard:
          <?php if (!empty($student["ktu_scorecard_path"])): ?>
            <a href="../<?php echo htmlspecialchars($student["ktu_scorecard_path"]); ?>" target="_blank">View</a>
          <?php else: ?>
            Not uploaded
          <?php endif; ?>
        </p>
      <?php endif; ?>
      <hr>
      <a href="edit_profile.php">Edit Profile</a><br><br>
      <a href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="container">
  <section class="hero">
    <h1>Welcome to Placement Hub</h1>
    <p>Your gateway to career opportunities and campus placements</p>

    <div class="hero-buttons">
      <a href="companies.html" class="btn">Explore Companies</a>
      <a href="resumes.html" class="btn secondary">Upload Resume</a>
    </div>
  </section>

  <section class="features">
    <div class="feature-card">
      <h3>Companies</h3>
      <p>Browse companies visiting campus</p>
    </div>

    <div class="feature-card">
      <h3>Wishlist</h3>
      <p>Save your favorite companies</p>
    </div>

    <div class="feature-card">
      <h3>Applications</h3>
      <p>Track your current application status</p>
    </div>

    <div class="feature-card">
      <h3>Resumes</h3>
      <p>Upload and manage resumes</p>
    </div>

    <div class="feature-card">
      <h3>Feedback</h3>
      <p>Share your placement experience</p>
    </div>
  </section>

  <div class="officer-section">
    <h2 class="section-title">Placement Officer</h2>

    <div class="officer-card">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="officer-avatar">

      <div class="officer-info">
        <h3><?php echo htmlspecialchars($adminProfile["name"]); ?></h3>
        <p class="role"><?php echo htmlspecialchars($adminProfile["role_title"]); ?></p>
        <p>📧 <?php echo htmlspecialchars($adminProfile["email"]); ?></p>
        <p>📞 <?php echo htmlspecialchars($adminProfile["phone"]); ?></p>
        <p>🏢 <?php echo htmlspecialchars($adminProfile["department"]); ?></p>
      </div>
    </div>
  </div>

  <section class="team-section">
    <h2 class="section-title">Placement Team Members</h2>
    <div class="student-team-grid">
      <?php foreach ($teamMembers as $member): ?>
        <div class="student-team-card">
          <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="student-team-avatar" alt="Team member">
          <div class="student-team-info">
            <h3><?php echo htmlspecialchars($member["name"] ?? ""); ?></h3>
            <p class="student-team-role"><?php echo htmlspecialchars($member["role"] ?? ""); ?></p>
            <?php if (!empty($member["mobile"])): ?>
              <p class="student-team-mobile">📞 <?php echo htmlspecialchars($member["mobile"]); ?></p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<script>
function toggleProfile() {
  const dropdown = document.getElementById("profileDropdown");
  dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
}
</script>
</body>
</html>
