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
$flashMessage = "";
$flashType = "success";

$hasScorecardColumn = false;
$hasEmailColumn = false;
$hasApplicationResumeIdColumn = false;
$scorecardCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'ktu_scorecard_path'");
if ($scorecardCheck && $scorecardCheck->num_rows > 0) {
    $hasScorecardColumn = true;
}
$emailCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
if ($emailCheck && $emailCheck->num_rows > 0) {
    $hasEmailColumn = true;
}
$applicationResumeIdCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'resume_id'");
if ($applicationResumeIdCheck && $applicationResumeIdCheck->num_rows > 0) {
    $hasApplicationResumeIdColumn = true;
}

/* =========================
   UPDATE STATUS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action_status"], $_POST["app_id"])) {
    $app_id = (int)$_POST["app_id"];
    $action = trim((string)$_POST["action_status"]);
    $mailMessage = trim((string)($_POST["mail_message"] ?? ""));

    if ($action === "shortlist") {
        $status = "Shortlisted";
        if ($mailMessage === "") {
            $mailMessage = "Congratulations. You are shortlisted. Further procedure details will be shared with you soon.";
        }
    } elseif ($action === "reject") {
        $status = "Rejected";
        if ($mailMessage === "") {
            $mailMessage = "Thank you for applying. We are unable to move ahead with your profile at this stage.";
        }
    } else {
        $status = "Pending";
    }

    $appInfoStmt = $conn->prepare("
        SELECT
            a.id,
            a.status AS current_status,
            s.fullname AS student_name,
            " . ($hasEmailColumn ? "s.email AS student_email," : "'' AS student_email,") . "
            j.job_title,
            c.companyName,
            c.email AS company_email
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN jobs j ON a.job_id = j.id
        JOIN companies c ON a.company_id = c.id
        WHERE a.id = ? AND a.company_id = ?
        LIMIT 1
    ");
    $appInfoStmt->bind_param("ii", $app_id, $company_id);
    $appInfoStmt->execute();
    $appInfo = $appInfoStmt->get_result()->fetch_assoc();

    if (!$appInfo) {
        $flashType = "error";
        $flashMessage = "Applicant record not found.";
    } else {
        $currentStatus = trim((string)($appInfo["current_status"] ?? "Pending"));
        $isLockedTransition = (
            ($currentStatus === "Shortlisted" && $status === "Rejected") ||
            ($currentStatus === "Rejected" && $status === "Shortlisted")
        );

        $isCancelled = ($currentStatus === "Cancelled");

        if ($isCancelled) {
            $flashType = "error";
            $flashMessage = "This application was cancelled by the student and cannot be updated.";
        } elseif ($isLockedTransition) {
            $flashType = "error";
            $flashMessage = "Status is locked. You cannot change from {$currentStatus} to {$status}.";
        } else {
        $updateStmt = $conn->prepare("UPDATE applications SET status=? WHERE id=? AND company_id=?");
        $updateStmt->bind_param("sii", $status, $app_id, $company_id);
        $updated = $updateStmt->execute();

        if (!$updated) {
            $flashType = "error";
            $flashMessage = "Status update failed. Try again.";
        } else {
            $flashType = "success";
            $flashMessage = "Status updated. Gmail compose is used for sending mail.";
        }
        }
    }
}

/* =========================
   FETCH APPLICATIONS
========================= */
$scorecardSelect = $hasScorecardColumn ? "students.ktu_scorecard_path," : "'' AS ktu_scorecard_path,";
$emailSelect = $hasEmailColumn ? "students.email," : "'' AS email,";

$resumeJoinExpr = $hasApplicationResumeIdColumn
    ? "COALESCE(
        applications.resume_id,
        (
            SELECT sr2.id
            FROM student_resumes sr2
            WHERE sr2.student_id = applications.student_id
              AND sr2.visibility = 'public'
            ORDER BY sr2.created_at DESC, sr2.id DESC
            LIMIT 1
        )
    )"
    : "(
        SELECT sr2.id
        FROM student_resumes sr2
        WHERE sr2.student_id = applications.student_id
          AND sr2.visibility = 'public'
        ORDER BY sr2.created_at DESC, sr2.id DESC
        LIMIT 1
    )";

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
    {$resumeJoinExpr}
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
<?php if ($flashMessage !== ""): ?>
  <div class="flash <?php echo $flashType === "success" ? "flash-success" : "flash-error"; ?>">
    <?php echo htmlspecialchars($flashMessage); ?>
  </div>
<?php endif; ?>

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
              if (!in_array($statusClass, ["pending", "shortlisted", "rejected", "cancelled"], true)) {
                  $statusClass = "pending";
              }
            ?>
            <span class="badge <?php echo $statusClass; ?>">
              <?php echo htmlspecialchars((string)$app['status']); ?>
            </span>
          </td>
          <td>
            <?php
              $currentStatus = (string)$app['status'];
              $studentName = (string)$app['fullname'];
              $jobTitle = (string)$app['job_title'];
              $shortTemplate = "Dear {$studentName},\n\nCongratulations! You are shortlisted for the role of {$jobTitle}.\n\nFurther procedure:\n1. \n2. \n\nRegards,\nRecruitment Team";
              $rejectTemplate = "Dear {$studentName},\n\nThank you for applying for {$jobTitle}.\n\nWe regret to inform you that your application has been rejected for the following reason:\n- \n\nRegards,\nRecruitment Team";
            ?>

            <?php if ($currentStatus === "Pending"): ?>
              <div class="table-actions">
                <button
                  type="button"
                  class="action-btn view"
                  data-app-id="<?php echo (int)$app['id']; ?>"
                  data-action="shortlist"
                  data-email="<?php echo htmlspecialchars((string)$app['email'], ENT_QUOTES); ?>"
                  data-student="<?php echo htmlspecialchars($studentName, ENT_QUOTES); ?>"
                  data-job="<?php echo htmlspecialchars($jobTitle, ENT_QUOTES); ?>"
                  data-title="Shortlist Mail"
                  onclick="openGmailCompose(this)"
                >Shortlist</button>
                <button
                  type="button"
                  class="action-btn reject"
                  data-app-id="<?php echo (int)$app['id']; ?>"
                  data-action="reject"
                  data-email="<?php echo htmlspecialchars((string)$app['email'], ENT_QUOTES); ?>"
                  data-student="<?php echo htmlspecialchars($studentName, ENT_QUOTES); ?>"
                  data-job="<?php echo htmlspecialchars($jobTitle, ENT_QUOTES); ?>"
                  data-title="Rejection Mail"
                  onclick="openGmailCompose(this)"
                >Reject</button>
              </div>
            <?php elseif ($currentStatus === "Shortlisted"): ?>
              <div class="locked-msg">Shortlisted. Rejection is locked.</div>
              <button
                type="button"
                class="action-btn view"
                data-app-id="<?php echo (int)$app['id']; ?>"
                data-action="shortlist"
                data-email="<?php echo htmlspecialchars((string)$app['email'], ENT_QUOTES); ?>"
                data-student="<?php echo htmlspecialchars($studentName, ENT_QUOTES); ?>"
                data-job="<?php echo htmlspecialchars($jobTitle, ENT_QUOTES); ?>"
                data-title="Shortlist Mail"
                onclick="openGmailCompose(this)"
              >Edit + Resend Shortlist Mail</button>
            <?php elseif ($currentStatus === "Rejected"): ?>
              <div class="locked-msg">Rejected. Shortlisting is locked.</div>
              <button
                type="button"
                class="action-btn reject"
                data-app-id="<?php echo (int)$app['id']; ?>"
                data-action="reject"
                data-email="<?php echo htmlspecialchars((string)$app['email'], ENT_QUOTES); ?>"
                data-student="<?php echo htmlspecialchars($studentName, ENT_QUOTES); ?>"
                data-job="<?php echo htmlspecialchars($jobTitle, ENT_QUOTES); ?>"
                data-title="Rejection Mail"
                onclick="openGmailCompose(this)"
              >Edit + Resend Rejection Mail</button>
            <?php elseif ($currentStatus === "Cancelled"): ?>
              <div class="locked-msg">Cancelled by student.</div>
            <?php endif; ?>
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

<script>
function openGmailCompose(btn) {
  var appId = btn.getAttribute("data-app-id") || "";
  var action = btn.getAttribute("data-action") || "";
  var toEmail = btn.getAttribute("data-email") || "";
  var student = btn.getAttribute("data-student") || "Candidate";
  var job = btn.getAttribute("data-job") || "the role";
  var template = "";
  var subject = "";

  if (!toEmail) {
    alert("Student email not available.");
    return;
  }

  if (action === "shortlist") {
    subject = "Application Update: Shortlisted for " + job;
    template =
      "Dear " + student + ",\n\n" +
      "Congratulations! You are shortlisted for the role of " + job + ".\n\n" +
      "Further procedure:\n1. \n2. \n\n" +
      "Regards,\nRecruitment Team";
  } else {
    subject = "Application Update for " + job;
    template =
      "Dear " + student + ",\n\n" +
      "Thank you for applying for " + job + ".\n\n" +
      "We regret to inform you that your application has been rejected for the following reason:\n- \n\n" +
      "Regards,\nRecruitment Team";
  }

  var gmailUrl =
    "https://mail.google.com/mail/?view=cm&fs=1" +
    "&to=" + encodeURIComponent(toEmail) +
    "&su=" + encodeURIComponent(subject) +
    "&body=" + encodeURIComponent(template);

  window.open(gmailUrl, "_blank");

  var form = document.createElement("form");
  form.method = "POST";
  form.action = "applicants.php";

  var appIdInput = document.createElement("input");
  appIdInput.type = "hidden";
  appIdInput.name = "app_id";
  appIdInput.value = appId;
  form.appendChild(appIdInput);

  var actionInput = document.createElement("input");
  actionInput.type = "hidden";
  actionInput.name = "action_status";
  actionInput.value = action;
  form.appendChild(actionInput);

  var messageInput = document.createElement("input");
  messageInput.type = "hidden";
  messageInput.name = "mail_message";
  messageInput.value = template;
  form.appendChild(messageInput);

  document.body.appendChild(form);
  form.submit();
}
</script>
</body>
</html>
