<?php
session_start();
include("../db.php"); // correct path
include_once("../database_setup.php"); // ensure required tables (including jobs) exist

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "company") {
    header("Location: ../login.html");
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

$company_id = (int)$_SESSION["company_id"];

/* =========================
   UPDATE COMPANY DETAILS
========================= */
if(isset($_POST['update_company'])){
    $name = $_POST['companyName'] ?? '';
    $industryValue = trim($_POST['industry'] ?? ($_POST['description'] ?? ''));
    $desc = $industryValue;
    $emp  = isset($_POST['employees']) ? (int)$_POST['employees'] : 0;
    $loc  = isset($_POST['locations']) && $_POST['locations'] !== '' ? (int)$_POST['locations'] : null;
    $locationText = $_POST['location'] ?? '';

    // Keep update compatible with different companies-table schemas.
    $setParts = ["companyName=?"];
    $types = "s";
    $params = [$name];

    $optionalColumns = [
      "description" => ["s", $desc],
      "industry"    => ["s", $industryValue],
      "employees"   => ["i", $emp],
      "location"    => ["s", $locationText]
    ];
    if($loc !== null){
      $optionalColumns["locations"] = ["i", $loc];
    }

    foreach($optionalColumns as $column => $meta){
      $colCheck = $conn->query("SHOW COLUMNS FROM companies LIKE '{$column}'");
      if($colCheck && $colCheck->num_rows > 0){
        $setParts[] = "{$column}=?";
        $types .= $meta[0];
        $params[] = $meta[1];
      }
    }

    $types .= "i";
    $params[] = $company_id;
    $sql = "UPDATE companies SET " . implode(", ", $setParts) . " WHERE id=?";

    $stmt = $conn->prepare($sql);
    $bind = [$types];
    foreach($params as $k => $v){
      $bind[] = &$params[$k];
    }
    call_user_func_array([$stmt, "bind_param"], $bind);
    $stmt->execute();
}

/* =========================
   ADD JOB
========================= */
if(isset($_POST['add_job'])){
    $title = $_POST['title'] ?? '';
    $desc = $_POST['desc'] ?? '';
    $openings = isset($_POST['openings']) ? (int)$_POST['openings'] : 0;
    $min_cgpa = isset($_POST['min_cgpa']) ? (float)$_POST['min_cgpa'] : 0;
    $location = $_POST['location'] ?? '';

    $stmt = $conn->prepare("INSERT INTO jobs 
        (company_id, job_title, job_description, openings, min_cgpa, location)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issids", $company_id, $title, $desc, $openings, $min_cgpa, $location);
    $stmt->execute();
}

/* =========================
   EDIT JOB
========================= */
if(isset($_POST['edit_job'])){
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    $title = $_POST['title'] ?? '';
    $desc = $_POST['desc'] ?? '';
    $openings = isset($_POST['openings']) ? (int)$_POST['openings'] : 0;
    $min_cgpa = isset($_POST['min_cgpa']) ? (float)$_POST['min_cgpa'] : 0;
    $location = $_POST['location'] ?? '';

    $stmt = $conn->prepare("UPDATE jobs 
        SET job_title=?, job_description=?, openings=?, min_cgpa=?, location=? 
        WHERE id=? AND company_id=?");
    $stmt->bind_param("ssidsii", $title, $desc, $openings, $min_cgpa, $location, $job_id, $company_id);
    $stmt->execute();
}

/* =========================
   DELETE JOB
========================= */
if(isset($_GET['delete'])){
    $job_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM jobs WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", $job_id, $company_id);
    $stmt->execute();
}

/* FETCH COMPANY DETAILS */
$stmt = $conn->prepare("SELECT * FROM companies WHERE id=?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc() ?: [];
$companyName = $company['companyName'] ?? 'Company';
$companyDescription = $company['description'] ?? ($company['industry'] ?? '');
$companyEmployees = isset($company['employees']) ? (int)$company['employees'] : 0;
$companyLocations = isset($company['locations']) ? (int)$company['locations'] : (!empty($company['location']) ? 1 : 0);
$companyLocationText = $company['location'] ?? '';
$industryOptions = [
  "Information Technology",
  "Software Development",
  "Artificial Intelligence",
  "Data Science & Analytics",
  "Finance & Banking",
  "FinTech",
  "Healthcare",
  "Pharmaceutical",
  "Manufacturing",
  "Automobile",
  "Electronics",
  "Telecommunications",
  "Education",
  "E-Commerce",
  "Retail",
  "Logistics & Supply Chain",
  "Media & Entertainment",
  "Marketing & Advertising",
  "Consulting",
  "Energy & Utilities",
  "Government / Public Sector",
  "Other"
];

/* FETCH JOBS */
$stmt = $conn->prepare("SELECT * FROM jobs WHERE company_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$jobs = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Company - Placement Hub</title>
  <link rel="stylesheet" href="company_com.css">
</head>

<body>

<header class="topbar">
  Company Dashboard
</header>

<nav class="navbar">
  <a href="home.php">Home</a>
  <a href="company.php" class="active">Company</a>
  <a href="applicants.php">Applicants</a>
  <a href="resumes.php">Resumes</a>
</nav>

<div class="container">

<!-- ================= COMPANY PROFILE ================= -->
<section class="card company-profile">

  <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="company-logo">

  <div class="company-info">
    <h2><?php echo htmlspecialchars($companyName); ?></h2>
    <p><?php echo htmlspecialchars($companyDescription); ?></p>
    <?php if(!empty($companyLocationText)): ?>
      <p><strong>Location:</strong> <?php echo htmlspecialchars($companyLocationText); ?></p>
    <?php endif; ?>

    <div class="company-stats">
      <span>👥 <?php echo $companyEmployees; ?> Employees</span>
      <span>📍 <?php echo $companyLocations; ?> Locations</span>
      <span>💼 <?php echo $jobs->num_rows; ?> Open Roles</span>
    </div>
  </div>

  <button class="edit-btn" onclick="openCompanyModal()">✏ Edit</button>

</section>


<!-- ================= OPEN POSITIONS ================= -->
<section class="card">

  <div class="section-head">
    <h3>Open Positions</h3>
    
    <button class="btn" onclick="openModal()">+ Add Job</button>
  </div>

  <div class="jobs">

  <?php if($jobs->num_rows > 0): ?>
    <?php while($job = $jobs->fetch_assoc()): ?>
      <?php
        $jobLocation = $job['location'] ?? '';
        $jobMinCgpa = array_key_exists('min_cgpa', $job) && $job['min_cgpa'] !== null ? (float)$job['min_cgpa'] : null;
      ?>
      <div class="job">
        <div class="job-main">
          <h4><?php echo htmlspecialchars($job['job_title']); ?></h4>
          <p class="job-line"><?php echo $job['openings']; ?> openings | <?php echo htmlspecialchars($jobLocation); ?></p>
          <p class="job-requirement">Min CGPA Requirement: <?php echo htmlspecialchars($jobMinCgpa !== null ? (string)$jobMinCgpa : 'No minimum'); ?></p>
        </div>

        <div class="job-actions">
          <button class="edit-btn"
            onclick="openEditModal(
              <?php echo $job['id']; ?>,
              '<?php echo addslashes($job['job_title']); ?>',
              '<?php echo addslashes($job['job_description']); ?>',
              <?php echo $job['openings']; ?>,
              <?php echo $jobMinCgpa !== null ? $jobMinCgpa : 0; ?>,
              '<?php echo addslashes($jobLocation); ?>'
            )">Edit</button>

          <a href="company.php?delete=<?php echo $job['id']; ?>"
             onclick="return confirm('Delete this job?')"
             class="action-btn reject">Delete</a>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p class="empty-state">No jobs added yet. Click "+ Add Job" to post your first role.</p>
  <?php endif; ?>

  </div>
</section>

</div>


<!-- ================= EDIT COMPANY MODAL ================= -->
<div class="modal" id="companyModal">
  <div class="modal-content edit-company-modal">
    <h3>Edit Company Profile</h3>
    <p class="modal-subtitle">Update your organization details to keep your company page professional.</p>
    <form method="POST">
      <div class="field">
        <label for="company_name">Company Name</label>
        <input id="company_name" name="companyName" value="<?php echo htmlspecialchars($companyName); ?>" required>
      </div>

      <div class="field">
        <label for="industry">Industry Type</label>
        <select id="industry" name="industry" required>
          <option value="" disabled <?php echo $companyDescription === '' ? 'selected' : ''; ?>>Select Industry</option>
          <?php foreach($industryOptions as $opt): ?>
            <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $companyDescription === $opt ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($opt); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="company_location">Location</label>
        <input id="company_location" name="location" value="<?php echo htmlspecialchars($companyLocationText); ?>">
      </div>

      <div class="field">
        <label for="company_employees">Number of Employees</label>
        <input id="company_employees" name="employees" type="number" min="0" value="<?php echo $companyEmployees; ?>">
      </div>

      <div class="modal-actions">
        <button type="submit" name="update_company" class="apply-btn">Save</button>
        <button type="button" onclick="closeCompanyModal()" class="cancel-btn">Cancel</button>
      </div>
    </form>
  </div>
</div>


<!-- ================= ADD JOB MODAL ================= -->
<div class="modal" id="addModal">
  <div class="modal-content add-job-modal">
    <h3>Add Job</h3>
    <p class="modal-subtitle">Post a role with clear eligibility and location details.</p>
    <form method="POST">
      <label for="job_title">Job Title</label>
      <input id="job_title" name="title" required>

      <div class="form-grid">
        <div>
          <label for="job_openings">Openings</label>
          <input id="job_openings" name="openings" type="number" required>
        </div>
        <div>
          <label for="job_min_cgpa">Minimum CGPA</label>
          <input id="job_min_cgpa" name="min_cgpa" type="number" step="0.01" min="0" max="10" required>
        </div>
      </div>

      <label for="job_location">Location</label>
      <input id="job_location" name="location">

      <label for="job_desc">Description</label>
      <textarea id="job_desc" name="desc"></textarea>

      <div class="modal-actions">
        <button type="submit" name="add_job" class="apply-btn">Add</button>
        <button type="button" onclick="closeModal()" class="cancel-btn">Cancel</button>
      </div>
    </form>
  </div>
</div>


<!-- ================= EDIT JOB MODAL ================= -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <h3>Edit Job</h3>
    <form method="POST">
      <input type="hidden" name="job_id" id="edit_id">
      <input name="title" id="edit_title" required>
      <input name="openings" id="edit_openings" type="number" required>
      <input name="min_cgpa" id="edit_min_cgpa" type="number" step="0.01" min="0" max="10" required>
      <input name="location" id="edit_location">
      <textarea name="desc" id="edit_desc"></textarea>

      <div class="modal-actions">
        <button type="submit" name="edit_job" class="apply-btn">Save</button>
        <button type="button" onclick="closeEditModal()" class="cancel-btn">Cancel</button>
      </div>
    </form>
  </div>
</div>


<script>
function openModal(){
  document.getElementById("addModal").style.display="flex";
}
function closeModal(){
  document.getElementById("addModal").style.display="none";
}

function openEditModal(id,title,desc,openings,minCgpa,location){
  document.getElementById("editModal").style.display="flex";
  document.getElementById("edit_id").value=id;
  document.getElementById("edit_title").value=title;
  document.getElementById("edit_desc").value=desc;
  document.getElementById("edit_openings").value=openings;
  document.getElementById("edit_min_cgpa").value=minCgpa;
  document.getElementById("edit_location").value=location;
}
function closeEditModal(){
  document.getElementById("editModal").style.display="none";
}

function openCompanyModal(){
  document.getElementById("companyModal").style.display="flex";
}
function closeCompanyModal(){
  document.getElementById("companyModal").style.display="none";
}
</script>

</body>
</html>
