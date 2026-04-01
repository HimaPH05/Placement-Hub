<?php
require_once __DIR__ . "/auth-check.php";
require_once __DIR__ . "/../db.php";
include_once __DIR__ . "/../database_setup.php";
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
$shortlistedCount = fetch_total($conn, "SELECT COUNT(*) AS total FROM applications WHERE status = 'Shortlisted'");
$placementCount = fetch_total($conn, "SELECT COUNT(*) AS total FROM applications WHERE status = 'Placed'");
$adminProfile = get_admin_profile();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Home - Placement Hub</title>
  <link rel="stylesheet" href="astyle.css?v=20260401">
  <link rel="manifest" href="../manifest.webmanifest">
  <meta name="theme-color" content="#0e4ccf">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Placement Hub">
  <link rel="apple-touch-icon" href="../icons/apple-touch-icon.png">
  <link rel="icon" href="../icons/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="../icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../icons/favicon-16.png">
  <script defer src="script.js?v=13"></script>
  <script defer src="../pwa-register.js"></script>
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
    <a href="students.php" class="stat-card blue stat-link">
      <p>Students</p>
      <h2 id="studentCount"><?php echo $studentCount; ?></h2>
    </a>

    <a href="company.php" class="stat-card green stat-link">
      <p>Companies</p>
      <h2 id="companyCount"><?php echo $companyCount; ?></h2>
    </a>

    <a href="resume.php" class="stat-card purple stat-link">
      <p>Public Resumes</p>
      <h2 id="resumeCount"><?php echo $publicResumeCount; ?></h2>
    </a>

    <button type="button" class="stat-card teal stat-filter-dashboard" data-filter="shortlisted">
      <p>Shortlisted</p>
      <h2 id="shortlistedCount"><?php echo $shortlistedCount; ?></h2>
    </button>

    <button type="button" class="stat-card orange stat-filter-dashboard" data-filter="placed">
      <p>Placements</p>
      <h2 id="placementCount"><?php echo $placementCount; ?></h2>
      <p class="placement-msg">Live count from company applicant updates.</p>
    </button>
  </div>

  <div id="dashboardApplicationPanel" class="table-wrap admin-table-wrap" style="display:none;">
    <div class="table-head">
      <div>
        <h3 id="dashboardApplicationTitle">Applications</h3>
        <p class="filter-note" id="dashboardApplicationNote"></p>
      </div>
      <div class="filter-row dashboard-filter-row">
        <label for="dashboardCompanyFilter" class="filter-label">Company</label>
        <select id="dashboardCompanyFilter" class="search compact-select">
          <option value="all">All Companies</option>
        </select>
        <button type="button" id="dashboardExportBtn" class="primary" onclick="downloadDashboardApplicationPdf()" disabled>Download PDF</button>
      </div>
    </div>

    <table class="app-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>Company</th>
          <th>Job Role</th>
          <th>Reg No</th>
          <th>Applied On</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="dashboardApplicationList">
        <tr>
          <td colspan="6">Select Shortlisted or Placements to view students.</td>
        </tr>
      </tbody>
    </table>
  </div>

<div class="info-grid">
  <div class="info-card">

    <h3>About Placement Team</h3>
    <p>
      We connect students with top recruiters through campus hiring,
      resume verification, interview scheduling and placement tracking.
    </p>
  </div>

  <div class="info-card">
   
    <h3>Contact Details</h3>
    <p><b>Name:</b> <?php echo htmlspecialchars($adminProfile["name"]); ?></p>
    <p><b>Email:</b> <?php echo htmlspecialchars($adminProfile["email"]); ?></p>
    <p><b>Phone:</b> <?php echo htmlspecialchars($adminProfile["phone"]); ?></p>
    <p><b>Office:</b> <?php echo htmlspecialchars($adminProfile["department"]); ?></p>
  </div>
</div>

<section class="link-admin-section">
  <div class="page-head">
    <h2>Application Links</h2>
  </div>

  <div class="link-admin-card">
    <div class="link-admin-form">
      <input type="text" id="linkTitle" placeholder="Title (example: Off-campus Drive)">
      <input type="text" id="linkCompany" placeholder="Company name">
      <input type="url" id="linkUrl" placeholder="Application URL">
      <input type="number" id="linkMinCgpa" placeholder="Minimum CGPA" min="0" max="10" step="0.01">
      <input type="date" id="linkDeadline">
      <textarea id="linkDescription" placeholder="Short description or instructions"></textarea>
      <button type="button" class="primary" onclick="addAdminLink()">Post Link</button>
    </div>
    <p id="adminLinkMsg" class="profile-msg"></p>
  </div>

  <div id="adminLinkList" class="link-admin-grid"></div>
</section>

</div>
</body>
</html>
