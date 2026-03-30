<?php
require_once __DIR__ . "/auth-check.php";
$initialStatus = strtolower(trim((string)($_GET["status"] ?? "all")));
$allowedStatuses = ["all", "pending", "shortlisted", "placed", "rejected", "cancelled"];
if (!in_array($initialStatus, $allowedStatuses, true)) {
  $initialStatus = "all";
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Application Tracking</title>
  <link rel="stylesheet" href="astyle.css?v=20260309">
  <script defer src="script.js?v=11"></script>
</head>

<body>
<header class="topbar">Admin Dashboard</header>
<nav class="navbar">
  <div class="logo">Placement Hub Admin</div>

  <div class="links">
    <a href="index.php">Home</a>
    <a href="students.php">Students</a>
    <a href="company.php">Company</a>
    <a href="applications.php" class="active">Applications</a>
    <a href="resume.php">Resumes</a>
    <a href="feedback.php">Feedback</a>
    <a href="profile.php">Admin Profile</a>
    <a href="logout.php" class="logout" id="logoutBtn">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-head">
    <h2>Application Tracking</h2>
    <input
      type="text"
      id="searchApplications"
      class="search"
      placeholder="Search student, company, role..."
      onkeyup="searchAdminApplications()"
    >
  </div>

  <div class="stats-grid application-stats" id="adminApplicationStats" data-initial-status="<?php echo htmlspecialchars($initialStatus); ?>">
    <button type="button" class="stat-card stat-filter active" data-filter="all">
      <p>Total</p>
      <h2 id="adminTotalApplications">0</h2>
    </button>
    <button type="button" class="stat-card yellow stat-filter" data-filter="pending">
      <p>Pending</p>
      <h2 id="adminPendingApplications">0</h2>
    </button>
    <button type="button" class="stat-card green stat-filter" data-filter="shortlisted">
      <p>Shortlisted</p>
      <h2 id="adminShortlistedApplications">0</h2>
    </button>
    <button type="button" class="stat-card blue stat-filter" data-filter="placed">
      <p>Placed</p>
      <h2 id="adminPlacedApplications">0</h2>
    </button>
    <button type="button" class="stat-card red stat-filter" data-filter="rejected">
      <p>Rejected</p>
      <h2 id="adminRejectedApplications">0</h2>
    </button>
    <button type="button" class="stat-card grey stat-filter" data-filter="cancelled">
      <p>Cancelled</p>
      <h2 id="adminCancelledApplications">0</h2>
    </button>
  </div>

  <div class="table-wrap admin-table-wrap">
    <div class="table-head">
      <h3 id="adminApplicationTitle">Application List</h3>
      <p class="filter-note" id="adminApplicationNote">Showing all applications.</p>
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
      <tbody id="adminApplicationList"></tbody>
    </table>
  </div>
</div>

</body>
</html>
