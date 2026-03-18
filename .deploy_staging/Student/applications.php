<?php
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: ../login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

require_once __DIR__ . "/../db-config.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = (int)$_SESSION["user_id"];
$lifeId = $student_id;
require_once __DIR__ . "/../student-lifecycle.php";
[$active, $expiryMsg] = enforce_student_not_expired($conn, $lifeId);
if (!$active) {
    session_destroy();
    header("Location: ../login.php?expired=1");
    exit();
}
$flashMessage = "";
$flashType = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_application_id"])) {
    $applicationId = (int)$_POST["cancel_application_id"];

    $checkStmt = $conn->prepare("
        SELECT status
        FROM applications
        WHERE id = ? AND student_id = ?
        LIMIT 1
    ");
    $checkStmt->bind_param("ii", $applicationId, $student_id);
    $checkStmt->execute();
    $appRow = $checkStmt->get_result()->fetch_assoc();

    if (!$appRow) {
        $flashType = "error";
        $flashMessage = "Application not found.";
    } else {
        $currentStatus = trim((string)$appRow["status"]);
        if (in_array($currentStatus, ["Rejected", "Cancelled"], true)) {
            $flashType = "error";
            $flashMessage = "This application cannot be cancelled.";
        } else {
            $cancelStmt = $conn->prepare("
                UPDATE applications
                SET status = 'Cancelled'
                WHERE id = ? AND student_id = ?
            ");
            $cancelStmt->bind_param("ii", $applicationId, $student_id);
            if ($cancelStmt->execute()) {
                $flashType = "success";
                $flashMessage = "Application cancelled successfully.";
            } else {
                $flashType = "error";
                $flashMessage = "Unable to cancel application.";
            }
        }
    }
}

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
    "rejected" => 0,
    "cancelled" => 0
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
    } elseif ($status === "cancelled") {
        $counts["cancelled"]++;
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
  <?php if ($flashMessage !== ""): ?>
    <div class="flash-msg <?php echo $flashType === "success" ? "flash-success" : "flash-error"; ?>">
      <?php echo htmlspecialchars($flashMessage); ?>
    </div>
  <?php endif; ?>

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
    <div class="stat-card cancelled-card">
      <h3><?php echo (int)$counts["cancelled"]; ?></h3>
      <p>Cancelled</p>
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
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($applications) > 0): ?>
          <?php foreach ($applications as $app): ?>
            <?php
              $statusClass = strtolower((string)$app["status"]);
              if (!in_array($statusClass, ["pending", "shortlisted", "rejected"], true)) {
                  if ($statusClass !== "cancelled") {
                      $statusClass = "pending";
                  }
              }
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$app["company_name"]); ?></td>
              <td><?php echo htmlspecialchars((string)$app["job_title"]); ?></td>
              <td><?php echo htmlspecialchars((string)($app["job_location"] ?? "-")); ?></td>
              <td><?php echo $app["min_cgpa"] !== null ? htmlspecialchars((string)$app["min_cgpa"]) : "No minimum"; ?></td>
              <td><?php echo htmlspecialchars((string)$app["applied_at"]); ?></td>
              <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string)$app["status"]); ?></span></td>
              <td>
                <?php if (in_array((string)$app["status"], ["Pending", "Shortlisted"], true)): ?>
                  <form method="post" onsubmit="return confirm('Cancel this application?');">
                    <input type="hidden" name="cancel_application_id" value="<?php echo (int)$app["id"]; ?>">
                    <button type="submit" class="btn danger-btn">Cancel Application</button>
                  </form>
                <?php else: ?>
                  <span class="muted-text">Not available</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" class="empty-cell">You have not applied to any jobs yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
