<?php
session_start();
include("../db.php");
include_once("../database_setup.php");
require_once __DIR__ . "/../profile-helpers.php";

if (
    (!isset($_SESSION["role"]) || $_SESSION["role"] !== "company")
    && !isset($_SESSION["company_id"])
) {
    die("Unauthorized Access");
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$search = "";
$hasProfilePhotoColumn = false;
$profilePhotoCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'profile_photo_path'");
if ($profilePhotoCheck && $profilePhotoCheck->num_rows > 0) {
    $hasProfilePhotoColumn = true;
}

$companyPhotoUrl = placementhub_default_profile_photo();
if (isset($_SESSION["company_id"])) {
    $companyPhotoStmt = $conn->prepare("SELECT COALESCE(profile_photo_path, '') AS profile_photo_path FROM companies WHERE id = ? LIMIT 1");
    if ($companyPhotoStmt) {
        $companyPhotoStmt->bind_param("i", $_SESSION["company_id"]);
        $companyPhotoStmt->execute();
        $companyProfile = $companyPhotoStmt->get_result()->fetch_assoc() ?: [];
        $companyPhotoUrl = placementhub_company_photo_url($companyProfile, "../");
    }
}

if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
    $stmt = $conn->prepare("
        SELECT sr.id, sr.student_id, sr.name, sr.branch, sr.gpa, sr.about, sr.skills, sr.file_name, sr.created_at,
               " . ($hasProfilePhotoColumn ? "COALESCE(s.profile_photo_path, '') AS profile_photo_path" : "'' AS profile_photo_path") . "
        FROM student_resumes sr
        LEFT JOIN students s ON s.id = sr.student_id
        WHERE sr.visibility = 'public'
        AND (sr.name LIKE ? OR sr.branch LIKE ? OR sr.skills LIKE ?)
        ORDER BY sr.created_at DESC
    ");
    $search_param = "%" . $search . "%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} else {
    $stmt = $conn->prepare("
        SELECT sr.id, sr.student_id, sr.name, sr.branch, sr.gpa, sr.about, sr.skills, sr.file_name, sr.created_at,
               " . ($hasProfilePhotoColumn ? "COALESCE(s.profile_photo_path, '') AS profile_photo_path" : "'' AS profile_photo_path") . "
        FROM student_resumes sr
        LEFT JOIN students s ON s.id = sr.student_id
        WHERE sr.visibility = 'public'
        ORDER BY sr.created_at DESC
    ");
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Resumes - Placement Hub</title>
  <link rel="stylesheet" href="company_resume.css?v=20260402-photo">
</head>

<body>

<header class="topbar">
  Company Dashboard
</header>

<nav class="navbar">
  <div class="nav-links">
    <a href="home.php">Home</a>
    <a href="company.php">Company</a>
    <a href="applicants.php">Applicants</a>
    <a href="resumes.php" class="active">Resumes</a>
  </div>
  <div class="company-profile-menu">
    <img src="<?php echo htmlspecialchars($companyPhotoUrl); ?>" class="company-profile-icon" onclick="toggleProfile()" alt="Company profile photo">
    <div id="profileDropdown" class="company-profile-dropdown">
      <a href="edit_company.php">Edit Profile</a><br><br>
      <a href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="container">

<div class="search-box card">
  <h3>Student Resume Database</h3>
  <form method="GET">
    <input
      type="text"
      name="search"
      placeholder="Search by name, branch, skill..."
      value="<?php echo htmlspecialchars($search); ?>"
    >
  </form>
</div>

<div class="resume-grid">

<?php if ($result->num_rows > 0): ?>
  <?php while ($resume = $result->fetch_assoc()): ?>

    <div class="resume-card card">
      <img src="<?php echo htmlspecialchars(placementhub_media_url((string)($resume["profile_photo_path"] ?? ""), "../")); ?>" class="resume-avatar" alt="Student photo">

      <h4><?php echo htmlspecialchars($resume["name"]); ?></h4>
      <p>Branch: <?php echo htmlspecialchars($resume["branch"]); ?></p>
      <p>GPA: <?php echo htmlspecialchars($resume["gpa"]); ?></p>

      <?php if (!empty($resume["skills"])): ?>
        <p>Skills: <?php echo htmlspecialchars($resume["skills"]); ?></p>
      <?php endif; ?>

      <?php if (!empty($resume["about"])): ?>
        <p><?php echo htmlspecialchars($resume["about"]); ?></p>
      <?php endif; ?>

      <div class="resume-actions">
        <a href="../view_resume.php?id=<?php echo (int)$resume["id"]; ?>" target="_blank" class="action-btn view">View</a>
        <a href="../view_resume.php?id=<?php echo (int)$resume["id"]; ?>&dl=1" class="action-btn">Download</a>
      </div>
    </div>

  <?php endwhile; ?>
<?php else: ?>
  <p class="empty-state">No public resumes found.</p>
<?php endif; ?>

</div>

</div>

<script src="script.js?v=20260402-photo"></script>
</body>
</html>
