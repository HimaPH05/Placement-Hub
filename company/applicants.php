<?php
session_start();
include("../db.php");   // Required for database connection
include_once("../database_setup.php"); // Ensure required tables exist

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$_SESSION['company_id'] = 1;   // TEMPORARY FOR TESTING

$company_id = $_SESSION['company_id'];

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
$query = "
SELECT applications.*, students.fullname, students.cgpa, jobs.job_title
FROM applications
JOIN students ON applications.student_id = students.id
JOIN jobs ON applications.job_id = jobs.id
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
  Applicants Dashboard
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
  <div class="stat-card">
    <h2><?php echo $total; ?></h2>
    <p>Total</p>
  </div>

  <div class="stat-card yellow">
    <h2><?php echo $pending; ?></h2>
    <p>Pending</p>
  </div>

  <div class="stat-card green">
    <h2><?php echo $shortlisted; ?></h2>
    <p>Shortlisted</p>
  </div>

  <div class="stat-card red">
    <h2><?php echo $rejected; ?></h2>
    <p>Rejected</p>
  </div>
</div>

<!-- ================= TABLE ================= -->
<div class="table-card">
  <h3>Applicant List</h3>

  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Position</th>
        <th>CGPA</th>
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
          <td><?php echo $app['cgpa']; ?></td>
          <td><?php echo $app['status']; ?></td>
          <td>
            <a href="applicants.php?action=shortlist&id=<?php echo $app['id']; ?>">Shortlist</a> |
            <a href="applicants.php?action=reject&id=<?php echo $app['id']; ?>">Reject</a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5">No applicants yet.</td>
        </tr>
      <?php endif; ?>
    </tbody>

  </table>
</div>

</div>
</body>
</html>
