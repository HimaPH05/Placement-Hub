<?php
session_start();
include("../db.php");   // Required for database connection
include_once("../database_setup.php"); // Ensure required tables exist
require_once __DIR__ . "/../profile-helpers.php";

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
$hasProfilePhotoColumn = false;
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
$profilePhotoCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'profile_photo_path'");
if ($profilePhotoCheck && $profilePhotoCheck->num_rows > 0) {
    $hasProfilePhotoColumn = true;
}

$companyProfileStmt = $conn->prepare("SELECT companyName, COALESCE(profile_photo_path, '') AS profile_photo_path FROM companies WHERE id = ? LIMIT 1");
$companyPhotoUrl = placementhub_default_profile_photo();
if ($companyProfileStmt) {
    $companyProfileStmt->bind_param("i", $company_id);
    $companyProfileStmt->execute();
    $companyProfile = $companyProfileStmt->get_result()->fetch_assoc() ?: [];
    $companyPhotoUrl = placementhub_company_photo_url($companyProfile, "../");
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
    } elseif ($action === "place") {
        $status = "Placed";
        if ($mailMessage === "") {
            $mailMessage = "Congratulations. You have been placed for this role after the interview process. Further onboarding details will be shared with you soon.";
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
            ($currentStatus === "Rejected" && $status === "Shortlisted") ||
            ($currentStatus === "Rejected" && $status === "Placed") ||
            ($currentStatus === "Pending" && $status === "Placed") ||
            ($currentStatus === "Placed" && $status !== "Placed")
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
$photoSelect = $hasProfilePhotoColumn ? "COALESCE(students.profile_photo_path, '') AS profile_photo_path," : "'' AS profile_photo_path,";

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
    {$photoSelect}
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
$placed = 0;
$rejected = 0;

$applications = [];
$jobOptions = [];

while($row = $result->fetch_assoc()){
    $applications[] = $row;
    $total++;

    $jobId = (int)($row['job_id'] ?? 0);
    if ($jobId > 0 && !isset($jobOptions[$jobId])) {
        $jobOptions[$jobId] = (string)$row['job_title'];
    }

    if($row['status'] == "Pending") $pending++;
    if($row['status'] == "Shortlisted") $shortlisted++;
    if($row['status'] == "Placed") $placed++;
    if($row['status'] == "Rejected") $rejected++;
}

asort($jobOptions, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Applicants - Placement Hub</title>
  <link rel="stylesheet" href="company_appli.css?v=20260402-photo">
</head>
<body>

<header class="topbar">
  Company Dashboard
</header>

<nav class="navbar">
  <div class="nav-links">
    <a href="home.php">Home</a>
    <a href="company.php">Company</a>
    <a href="applicants.php" class="active">Applicants</a>
    <a href="resumes.php">Resumes</a>
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
<?php if ($flashMessage !== ""): ?>
  <div class="flash <?php echo $flashType === "success" ? "flash-success" : "flash-error"; ?>">
    <?php echo htmlspecialchars($flashMessage); ?>
  </div>
<?php endif; ?>

<!-- ================= STATS ================= -->
<div class="stats">
  <button type="button" class="stat-card card stat-filter active" data-filter="all">
    <h2><?php echo $total; ?></h2>
    <p>Total</p>
  </button>

  <button type="button" class="stat-card yellow card stat-filter" data-filter="pending">
    <h2><?php echo $pending; ?></h2>
    <p>Pending</p>
  </button>

  <button type="button" class="stat-card green card stat-filter" data-filter="shortlisted">
    <h2><?php echo $shortlisted; ?></h2>
    <p>Shortlisted</p>
  </button>

  <button type="button" class="stat-card blue card stat-filter" data-filter="placed">
    <h2><?php echo $placed; ?></h2>
    <p>Placed</p>
  </button>

  <button type="button" class="stat-card red card stat-filter" data-filter="rejected">
    <h2><?php echo $rejected; ?></h2>
    <p>Rejected</p>
  </button>
</div>

<!-- ================= TABLE ================= -->
<div class="table-card card">
  <div class="table-head">
    <div>
      <h3 id="applicantListTitle">Applicant List</h3>
      <p class="filter-note" id="filterNote">Showing all applicants.</p>
    </div>
    <div class="filter-controls">
      <label for="jobFilter" class="filter-label">Job Role</label>
      <select id="jobFilter" class="filter-select">
        <option value="all">All Jobs</option>
        <?php foreach ($jobOptions as $jobOptionId => $jobOptionTitle): ?>
          <option value="<?php echo (int)$jobOptionId; ?>"><?php echo htmlspecialchars($jobOptionTitle); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

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
        <tr
          class="applicant-row"
          data-status="<?php echo htmlspecialchars(strtolower((string)$app['status'])); ?>"
          data-job-id="<?php echo (int)$app['job_id']; ?>"
          data-job-title="<?php echo htmlspecialchars((string)$app['job_title'], ENT_QUOTES); ?>"
        >
          <td><?php echo htmlspecialchars($app['fullname']); ?></td>
          <td><?php echo htmlspecialchars($app['job_title']); ?></td>
          <td>
            <div class="applicant-details">
            <img src="<?php echo htmlspecialchars(placementhub_media_url((string)($app['profile_photo_path'] ?? ''), '../')); ?>" alt="Student photo" class="applicant-avatar">
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
              if (!in_array($statusClass, ["pending", "shortlisted", "placed", "rejected", "cancelled"], true)) {
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
              <div class="locked-msg">Shortlisted. You can now mark this applicant as placed after interview.</div>
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
                >Edit + Resend Shortlist Mail</button>
                <button
                  type="button"
                  class="action-btn placed"
                  data-app-id="<?php echo (int)$app['id']; ?>"
                  data-action="place"
                  data-email="<?php echo htmlspecialchars((string)$app['email'], ENT_QUOTES); ?>"
                  data-student="<?php echo htmlspecialchars($studentName, ENT_QUOTES); ?>"
                  data-job="<?php echo htmlspecialchars($jobTitle, ENT_QUOTES); ?>"
                  data-title="Placement Mail"
                  onclick="openGmailCompose(this)"
                >Mark as Placed</button>
              </div>
            <?php elseif ($currentStatus === "Placed"): ?>
              <div class="locked-msg">Placed after interview.</div>
              <button
                type="button"
                class="action-btn placed"
                data-app-id="<?php echo (int)$app['id']; ?>"
                data-action="place"
                data-email="<?php echo htmlspecialchars((string)$app['email'], ENT_QUOTES); ?>"
                data-student="<?php echo htmlspecialchars($studentName, ENT_QUOTES); ?>"
                data-job="<?php echo htmlspecialchars($jobTitle, ENT_QUOTES); ?>"
                data-title="Placement Mail"
                onclick="openGmailCompose(this)"
              >Edit + Resend Placement Mail</button>
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
        <tr id="emptyApplicantsRow">
          <td colspan="7">No applicants yet.</td>
        </tr>
      <?php endif; ?>
      <tr id="filteredEmptyRow" style="display:none;">
        <td colspan="7">No applicants found for this filter.</td>
      </tr>
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
  } else if (action === "place") {
    subject = "Application Update: Placed for " + job;
    template =
      "Dear " + student + ",\n\n" +
      "Congratulations! You have been placed for the role of " + job + " after the interview process.\n\n" +
      "Further onboarding details will be shared with you soon.\n\n" +
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

var filterButtons = document.querySelectorAll(".stat-filter");
var applicantRows = document.querySelectorAll(".applicant-row");
var filterNote = document.getElementById("filterNote");
var applicantListTitle = document.getElementById("applicantListTitle");
var filteredEmptyRow = document.getElementById("filteredEmptyRow");
var jobFilter = document.getElementById("jobFilter");
var currentStatusFilter = "all";
var statusLabels = {
  all: "All",
  pending: "Pending",
  shortlisted: "Shortlisted",
  placed: "Placed",
  rejected: "Rejected"
};

function buildFilterMessage(statusFilter, jobTitle) {
  if (statusFilter === "all" && !jobTitle) {
    return "Showing all applicants.";
  }

  if (statusFilter === "all" && jobTitle) {
    return "Showing applicants for " + jobTitle + ".";
  }

  if (statusFilter !== "all" && !jobTitle) {
    return "Showing only " + statusLabels[statusFilter].toLowerCase() + " applicants.";
  }

  return "Showing " + statusLabels[statusFilter].toLowerCase() + " applicants for " + jobTitle + ".";
}

function buildFilterTitle(statusFilter, jobTitle) {
  if (statusFilter === "all" && !jobTitle) {
    return "Applicant List";
  }

  if (statusFilter === "all" && jobTitle) {
    return jobTitle + " Applicants";
  }

  if (statusFilter !== "all" && !jobTitle) {
    return statusLabels[statusFilter] + " Applicants";
  }

  return statusLabels[statusFilter] + " - " + jobTitle;
}

function applyApplicantFilter(statusFilter) {
  currentStatusFilter = statusFilter;
  var visibleCount = 0;
  var selectedJobId = jobFilter ? jobFilter.value : "all";
  var selectedJobTitle = "";

  if (jobFilter && jobFilter.selectedIndex >= 0 && selectedJobId !== "all") {
    selectedJobTitle = jobFilter.options[jobFilter.selectedIndex].text;
  }

  filterButtons.forEach(function(btn) {
    btn.classList.toggle("active", btn.getAttribute("data-filter") === statusFilter);
  });

  applicantRows.forEach(function(row) {
    var rowStatus = row.getAttribute("data-status") || "";
    var rowJobId = row.getAttribute("data-job-id") || "";
    var matchesStatus = statusFilter === "all" || rowStatus === statusFilter;
    var matchesJob = selectedJobId === "all" || rowJobId === selectedJobId;
    var showRow = matchesStatus && matchesJob;
    row.style.display = showRow ? "" : "none";
    if (showRow) {
      visibleCount++;
    }
  });

  if (applicantListTitle) {
    applicantListTitle.textContent = buildFilterTitle(statusFilter, selectedJobTitle);
  }

  if (filterNote) {
    filterNote.textContent = buildFilterMessage(statusFilter, selectedJobTitle);
  }

  if (filteredEmptyRow) {
    filteredEmptyRow.style.display = visibleCount === 0 ? "" : "none";
  }
}

filterButtons.forEach(function(btn) {
  btn.addEventListener("click", function() {
    applyApplicantFilter(btn.getAttribute("data-filter") || "all");
  });
});

if (jobFilter) {
  jobFilter.addEventListener("change", function() {
    applyApplicantFilter(currentStatusFilter);
  });
}
</script>
<script src="script.js?v=20260402-photo"></script>
</body>
</html>
