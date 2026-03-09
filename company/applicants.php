<?php
session_start();
include("../db.php");   // Required for database connection
include_once("../database_setup.php"); // Ensure required tables exist

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "company") {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION["company_id"]) && isset($_SESSION["username"])) {
    $stmt = $conn->prepare("SELECT id FROM companies WHERE username=?");
    $stmt->bind_param("s", $_SESSION["username"]);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $_SESSION["company_id"] = (int)$row["id"];
    }
}

if (!isset($_SESSION["company_id"])) {
    die("Unauthorized Access");
}

$company_id = (int)$_SESSION['company_id'];

$hasScorecardColumn = false;
$hasEmailColumn = false;
$scorecardCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'ktu_scorecard_path'");
if ($scorecardCheck && $scorecardCheck->num_rows > 0) {
    $hasScorecardColumn = true;
}
$emailCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
if ($emailCheck && $emailCheck->num_rows > 0) {
    $hasEmailColumn = true;
}

/* =========================
   UPDATE STATUS
========================= */
if(isset($_GET['action']) && isset($_GET['id'])){
    $app_id = $_GET['id'];
    $action = $_GET['action'];

    if($action == "shortlist"){
        $status = "Shortlisted";
    } elseif($action == "reject"){
        $status = "Rejected";
    } else {
        $status = "Pending";
    }

    $stmt = $conn->prepare("UPDATE applications 
                            SET status=? 
                            WHERE id=? AND company_id=?");
    $stmt->bind_param("sii", $status, $app_id, $company_id);
    $stmt->execute();
}

/* =========================
   FETCH APPLICATIONS
========================= */
$scorecardSelect = $hasScorecardColumn ? "students.ktu_scorecard_path," : "'' AS ktu_scorecard_path,";
$emailSelect = $hasEmailColumn ? "students.email," : "'' AS email,";

$query = "
SELECT
    applications.*,
    students.fullname,
    students.username,
    {$emailSelect}
    students.regno,
    students.dob,
    students.cgpa,
    {$scorecardSelect}
    jobs.job_title,
    sr.id AS resume_id,
    sr.file_name AS resume_file_name
FROM applications
JOIN students ON applications.student_id = students.id
JOIN jobs ON applications.job_id = jobs.id
LEFT JOIN student_resumes sr ON sr.id = (
    SELECT sr2.id
    FROM student_resumes sr2
    WHERE sr2.student_id = applications.student_id
      AND sr2.visibility = 'public'
    ORDER BY sr2.created_at DESC, sr2.id DESC
    LIMIT 1
)
WHERE applications.company_id = ?
ORDER BY applications.applied_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   COUNT STATS
========================= */
$total = 0;
$pending = 0;
$shortlisted = 0;
$rejected = 0;

$applications = [];

while($row = $result->fetch_assoc()){
    $applications[] = $row;
    $total++;

    if($row['status'] == "Pending") $pending++;
    if($row['status'] == "Shortlisted") $shortlisted++;
    if($row['status'] == "Rejected") $rejected++;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Applicants - Placement Hub</title>
  <link rel="stylesheet" href="company_appli.css">
</head>
<body>

<header class="topbar">
  Company Dashboard
</header>

<nav class="navbar">
  <a href="home.php">Home</a>
  <a href="company.php">Company</a>
  <a href="applicants.php" class="active">Applicants</a>
  <a href="resumes.php">Resumes</a>
</nav>

<div class="container">

<!-- ================= STATS ================= -->
<div class="stats">
  <div class="stat-card card">
    <h2><?php echo $total; ?></h2>
    <p>Total</p>
  </div>

  <div class="stat-card yellow card">
    <h2><?php echo $pending; ?></h2>
    <p>Pending</p>
  </div>

  <div class="stat-card green card">
    <h2><?php echo $shortlisted; ?></h2>
    <p>Shortlisted</p>
  </div>

  <div class="stat-card red card">
    <h2><?php echo $rejected; ?></h2>
    <p>Rejected</p>
  </div>
</div>

<!-- ================= TABLE ================= -->
<div class="table-card card">
  <h3>Applicant List</h3>

  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Position</th>
        <th>Applicant Details</th>
        <th>Resume</th>
        <th>KTU Scorecard</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>

    <tbody>
      <?php if($total > 0): ?>
        <?php foreach($applications as $app): ?>
        <tr>
          <td><?php echo htmlspecialchars($app['fullname']); ?></td>
          <td><?php echo htmlspecialchars($app['job_title']); ?></td>
          <td>
            <div class="applicant-details">
            <div><strong>CGPA:</strong> <?php echo htmlspecialchars((string)$app['cgpa']); ?></div>
              <div><strong>Username:</strong> <?php echo htmlspecialchars((string)$app['username']); ?></div>
              <div><strong>Email:</strong> <?php echo htmlspecialchars((string)$app['email']); ?></div>
              <div><strong>Reg No:</strong> <?php echo htmlspecialchars((string)$app['regno']); ?></div>
              <div><strong>DOB:</strong> <?php echo htmlspecialchars((string)$app['dob']); ?></div>
              <div><strong>Applied:</strong> <?php echo htmlspecialchars((string)$app['applied_at']); ?></div>
            </div>
          </td>
          <td>
            <?php if (!empty($app['resume_id'])): ?>
              <a href="../view_resume.php?id=<?php echo (int)$app['resume_id']; ?>" target="_blank" class="action-btn view">View Resume</a>
            <?php else: ?>
              <span class="muted">No public resume</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($app['ktu_scorecard_path'])): ?>
              <a href="../<?php echo htmlspecialchars($app['ktu_scorecard_path']); ?>" target="_blank" class="action-btn view">View Scorecard</a>
            <?php else: ?>
              <span class="muted">Not uploaded</span>
            <?php endif; ?>
          </td>
          <td>
            <?php
              $statusClass = strtolower((string)$app['status']);
              if (!in_array($statusClass, ["pending", "shortlisted", "rejected"], true)) {
                  $statusClass = "pending";
              }
            ?>
            <span class="badge <?php echo $statusClass; ?>">
              <?php echo htmlspecialchars((string)$app['status']); ?>
            </span>
          </td>
          <td>
            <div class="table-actions">
              <a class="action-btn" href="applicants.php?action=shortlist&id=<?php echo $app['id']; ?>">Shortlist</a>
              <a class="action-btn reject" href="applicants.php?action=reject&id=<?php echo $app['id']; ?>">Reject</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7">No applicants yet.</td>
        </tr>
      <?php endif; ?>
    </tbody>

  </table>
</div>

</div>
</body>
</html>
