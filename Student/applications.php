<?php
session_start();

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

$query = "
SELECT
    a.id,
    a.status,
    a.applied_at,
    j.job_title,
    j.location AS job_location,
    j.min_cgpa,
    COALESCE(NULLIF(c.companyName, ''), c.username) AS company_name
FROM applications a
JOIN jobs j ON a.job_id = j.id
JOIN companies c ON a.company_id = c.id
WHERE a.student_id = ?
ORDER BY a.applied_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$applications = [];
$counts = [
    "total" => 0,
    "pending" => 0,
    "shortlisted" => 0,
    "rejected" => 0
];

while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
    $counts["total"]++;

    $status = strtolower((string)$row["status"]);
    if ($status === "pending") {
        $counts["pending"]++;
    } elseif ($status === "shortlisted") {
        $counts["shortlisted"]++;
    } elseif ($status === "rejected") {
        $counts["rejected"]++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>My Applications | Placement Hub</title>
  <link rel="stylesheet" href="style.css?v=20260309">
</head>
<body>

<nav class="navbar">
  <h2 class="logo">Placement Hub</h2>

  <div class="links">
    <a href="home.php">Home</a>
    <a href="companies.html">Companies</a>
    <a href="applications.php" class="active">My Applications</a>
    <a href="wishlist.html">Wishlist</a>
    <a href="feedback.html">Feedback</a>
    <a href="resumes.html">Resumes</a>
  </div>
</nav>

<div class="container">
  <div class="page-head">
    <div>
      <h1>My Applications</h1>
      <p class="sub-text">Track the status of every job you applied for.</p>
    </div>
  </div>

  <div class="stats">
    <div class="stat-card">
      <h3><?php echo (int)$counts["total"]; ?></h3>
      <p>Total</p>
    </div>
    <div class="stat-card pending-card">
      <h3><?php echo (int)$counts["pending"]; ?></h3>
      <p>Pending</p>
    </div>
    <div class="stat-card shortlisted-card">
      <h3><?php echo (int)$counts["shortlisted"]; ?></h3>
      <p>Shortlisted</p>
    </div>
    <div class="stat-card rejected-card">
      <h3><?php echo (int)$counts["rejected"]; ?></h3>
      <p>Rejected</p>
    </div>
  </div>

  <div class="table-wrap">
    <table class="app-table">
      <thead>
        <tr>
          <th>Company</th>
          <th>Job Role</th>
          <th>Location</th>
          <th>Min CGPA</th>
          <th>Applied On</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($applications) > 0): ?>
          <?php foreach ($applications as $app): ?>
            <?php
              $statusClass = strtolower((string)$app["status"]);
              if (!in_array($statusClass, ["pending", "shortlisted", "rejected"], true)) {
                  $statusClass = "pending";
              }
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$app["company_name"]); ?></td>
              <td><?php echo htmlspecialchars((string)$app["job_title"]); ?></td>
              <td><?php echo htmlspecialchars((string)($app["job_location"] ?? "-")); ?></td>
              <td><?php echo $app["min_cgpa"] !== null ? htmlspecialchars((string)$app["min_cgpa"]) : "No minimum"; ?></td>
              <td><?php echo htmlspecialchars((string)$app["applied_at"]); ?></td>
              <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string)$app["status"]); ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="empty-cell">You have not applied to any jobs yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
