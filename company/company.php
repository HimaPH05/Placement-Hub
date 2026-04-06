<?php
session_start();
include("../db.php"); // correct path
include_once("../database_setup.php"); // ensure required tables (including jobs) exist
require_once __DIR__ . "/../profile-helpers.php";

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

$company_id = (int)$_SESSION["company_id"];

/* =========================
   UPDATE COMPANY DETAILS
========================= */
if(isset($_POST['update_company'])){
    $name = $_POST['companyName'] ?? '';
    $industryValue = trim($_POST['industry'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $emp  = isset($_POST['employees']) ? (int)$_POST['employees'] : 0;
    $removeProfilePhoto = isset($_POST['remove_profile_photo']);
    $profilePhotoPath = trim((string)($_POST['existing_profile_photo_path'] ?? ''));
    $locationItemsRaw = $_POST['location_items'] ?? [];
    if(!is_array($locationItemsRaw)){
      $locationItemsRaw = [];
    }
    $locationItems = [];
    foreach($locationItemsRaw as $item){
      $item = trim((string)$item);
      if($item !== ''){
        $locationItems[] = $item;
      }
    }
    $locationText = implode(', ', $locationItems);
    $loc  = isset($_POST['locations']) && $_POST['locations'] !== '' ? (int)$_POST['locations'] : null;
    if($loc === null && count($locationItems) > 0){
      $loc = count($locationItems);
    }

    if ($removeProfilePhoto) {
      $profilePhotoPath = "";
    }

    if (isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES["profile_photo"]["error"] !== UPLOAD_ERR_OK) {
        die("Failed to upload company profile photo.");
      }

      $allowedImageExt = ["jpg", "jpeg", "png", "webp"];
      $photoExt = strtolower(pathinfo((string)$_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
      $photoSize = (int)($_FILES["profile_photo"]["size"] ?? 0);
      $photoInfo = @getimagesize($_FILES["profile_photo"]["tmp_name"]);

      if (!in_array($photoExt, $allowedImageExt, true) || $photoInfo === false) {
        die("Only JPG, PNG, or WEBP company profile photos are allowed.");
      }
      if ($photoSize > 2 * 1024 * 1024) {
        die("Company profile photo must be 2 MB or smaller.");
      }

      $uploadDir = __DIR__ . "/../uploads/company_photos";
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }

      $safeName = "company_" . $company_id . "_" . time() . "." . $photoExt;
      $targetPath = $uploadDir . "/" . $safeName;
      $relativePath = "uploads/company_photos/" . $safeName;

      if (!move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $targetPath)) {
        die("Unable to save company profile photo.");
      }

      $profilePhotoPath = $relativePath;
    }

    // Keep update compatible with different companies-table schemas.
    $setParts = ["companyName=?"];
    $types = "s";
    $params = [$name];

    $optionalColumns = [
      "description" => ["s", $desc],
      "industry"    => ["s", $industryValue],
      "employees"   => ["i", $emp],
      "location"    => ["s", $locationText],
      "profile_photo_path" => ["s", $profilePhotoPath]
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
    $max_supplies = isset($_POST['max_supplies']) && $_POST['max_supplies'] !== '' ? (int)$_POST['max_supplies'] : null;
    $location = $_POST['location'] ?? '';

    $stmt = $conn->prepare("INSERT INTO jobs 
        (company_id, job_title, job_description, openings, min_cgpa, max_supplies, location)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issidis", $company_id, $title, $desc, $openings, $min_cgpa, $max_supplies, $location);
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
    $max_supplies = isset($_POST['max_supplies']) && $_POST['max_supplies'] !== '' ? (int)$_POST['max_supplies'] : null;
    $location = $_POST['location'] ?? '';

    $stmt = $conn->prepare("UPDATE jobs 
        SET job_title=?, job_description=?, openings=?, min_cgpa=?, max_supplies=?, location=? 
        WHERE id=? AND company_id=?");
    $stmt->bind_param("ssidisii", $title, $desc, $openings, $min_cgpa, $max_supplies, $location, $job_id, $company_id);
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
$companyPhotoUrl = placementhub_company_photo_url($company, "../");
$companyDescription = $company['description'] ?? ($company['industry'] ?? '');
$companyEmployees = isset($company['employees']) ? (int)$company['employees'] : 0;
$companyLocationText = $company['location'] ?? '';
$companyLocationListRaw = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)$companyLocationText))));
$storedLocationsCount = isset($company['locations']) ? (int)$company['locations'] : 0;
$companyLocations = count($companyLocationListRaw) > 0 ? count($companyLocationListRaw) : $storedLocationsCount;
$companyLocationList = $companyLocationListRaw;
if (empty($companyLocationList)) {
  $companyLocationList = [''];
}
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
  <link rel="stylesheet" href="company_com.css?v=20260402-photo">
</head>

<body>

<header class="topbar">
  Company Dashboard
</header>

<nav class="navbar">
  <div class="nav-links">
    <a href="home.php">Home</a>
    <a href="company.php" class="active">Company</a>
    <a href="applicants.php">Applicants</a>
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

<!-- ================= COMPANY PROFILE ================= -->
<section class="card company-profile">

  <img src="<?php echo htmlspecialchars($companyPhotoUrl); ?>" class="company-logo" alt="Company profile photo">

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
        $jobMaxSupplies = array_key_exists('max_supplies', $job) && $job['max_supplies'] !== null ? (int)$job['max_supplies'] : null;
      ?>
      <div class="job">
        <div class="job-main">
          <h4><?php echo htmlspecialchars($job['job_title']); ?></h4>
          <p class="job-line"><?php echo $job['openings']; ?> openings | <?php echo htmlspecialchars($jobLocation); ?></p>
          <p class="job-requirement">Min CGPA Requirement: <?php echo htmlspecialchars($jobMinCgpa !== null ? (string)$jobMinCgpa : 'No minimum'); ?></p>
          <p class="job-requirement">Max Supplies Allowed: <?php echo htmlspecialchars($jobMaxSupplies !== null ? (string)$jobMaxSupplies : 'No limit'); ?></p>
        </div>

        <div class="job-actions">
          <button class="edit-btn"
            onclick="openEditModal(
              <?php echo $job['id']; ?>,
              '<?php echo addslashes($job['job_title']); ?>',
              '<?php echo addslashes($job['job_description']); ?>',
              <?php echo $job['openings']; ?>,
              <?php echo $jobMinCgpa !== null ? $jobMinCgpa : 0; ?>,
              <?php echo $jobMaxSupplies !== null ? $jobMaxSupplies : 'null'; ?>,
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
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="existing_profile_photo_path" value="<?php echo htmlspecialchars((string)($company['profile_photo_path'] ?? '')); ?>">
      <div class="field">
        <label for="company_name">Company Name</label>
        <input id="company_name" name="companyName" value="<?php echo htmlspecialchars($companyName); ?>" required>
      </div>

      <div class="field">
        <label for="industry">Industry Type</label>
        <select id="industry" name="industry" required>
          <option value="" disabled <?php echo ($company['industry'] ?? '') === '' ? 'selected' : ''; ?>>Select Industry</option>
          <?php foreach($industryOptions as $opt): ?>
            <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($company['industry'] ?? '') === $opt ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($opt); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="company_description">Company Description</label>
        <textarea id="company_description" name="description" rows="4" placeholder="Add company overview, culture, and hiring focus..."><?php echo htmlspecialchars($company['description'] ?? ''); ?></textarea>
      </div>

      <div class="field">
        <label>Locations</label>
        <div class="locations-builder" id="locationsBuilder">
          <?php foreach($companyLocationList as $idx => $locName): ?>
            <div class="location-item">
              <span class="location-index"><?php echo $idx + 1; ?>.</span>
              <input name="location_items[]" value="<?php echo htmlspecialchars($locName); ?>" placeholder="Enter location name">
              <button type="button" class="remove-location-btn" onclick="removeLocationRow(this)">Remove</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="add-location-btn" onclick="addLocationRow()">+ Add Another Location</button>
        <div class="location-row">
          <input id="company_locations_count" name="locations" type="number" min="1" placeholder="No. of Locations" value="<?php echo $companyLocations > 0 ? $companyLocations : ''; ?>" readonly>
        </div>
      </div>

      <div class="field">
        <label for="company_employees">Number of Employees</label>
        <input id="company_employees" name="employees" type="number" min="0" value="<?php echo $companyEmployees; ?>">
      </div>

      <div class="field">
        <label for="company_profile_photo">Profile Photo</label>
        <input id="company_profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        <?php if (!empty($company['profile_photo_path'])): ?>
          <input type="hidden" name="remove_profile_photo" id="companyModalRemoveProfilePhotoInput" value="0">
          <button type="button" class="mini-remove-btn" onclick="markProfilePhotoForRemoval('companyModalRemoveProfilePhotoInput', null, this)">Remove Profile Pic</button>
        <?php endif; ?>
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
        <div>
          <label for="job_max_supplies">Maximum Supplies</label>
          <input id="job_max_supplies" name="max_supplies" type="number" min="0" placeholder="Leave blank for no limit">
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
      <input name="max_supplies" id="edit_max_supplies" type="number" min="0" placeholder="Leave blank for no limit">
      <input name="location" id="edit_location">
      <textarea name="desc" id="edit_desc"></textarea>

      <div class="modal-actions">
        <button type="submit" name="edit_job" class="apply-btn">Save</button>
        <button type="button" onclick="closeEditModal()" class="cancel-btn">Cancel</button>
      </div>
    </form>
  </div>
</div>


<script src="script.js?v=20260402-photo"></script>
<script>
function openModal(){
  document.getElementById("addModal").style.display="flex";
}
function closeModal(){
  document.getElementById("addModal").style.display="none";
}

function openEditModal(id,title,desc,openings,minCgpa,maxSupplies,location){
  document.getElementById("editModal").style.display="flex";
  document.getElementById("edit_id").value=id;
  document.getElementById("edit_title").value=title;
  document.getElementById("edit_desc").value=desc;
  document.getElementById("edit_openings").value=openings;
  document.getElementById("edit_min_cgpa").value=minCgpa;
  document.getElementById("edit_max_supplies").value=maxSupplies === null ? "" : maxSupplies;
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

function updateLocationIndices(){
  const rows = document.querySelectorAll("#locationsBuilder .location-item");
  rows.forEach((row, index) => {
    const marker = row.querySelector(".location-index");
    if(marker) marker.textContent = (index + 1) + ".";
  });
  const countInput = document.getElementById("company_locations_count");
  if(countInput){
    countInput.value = rows.length ? rows.length : "";
  }
}

function addLocationRow(value = ""){
  const wrapper = document.getElementById("locationsBuilder");
  if(!wrapper) return;

  const row = document.createElement("div");
  row.className = "location-item";
  row.innerHTML = `
    <span class="location-index"></span>
    <input name="location_items[]" placeholder="Enter location name">
    <button type="button" class="remove-location-btn">Remove</button>
  `;
  const input = row.querySelector("input");
  if(input) input.value = value;
  const removeBtn = row.querySelector(".remove-location-btn");
  if(removeBtn){
    removeBtn.addEventListener("click", function(){
      removeLocationRow(removeBtn);
    });
  }
  wrapper.appendChild(row);
  updateLocationIndices();
}

function removeLocationRow(btn){
  const wrapper = document.getElementById("locationsBuilder");
  if(!wrapper || !btn) return;
  const rows = wrapper.querySelectorAll(".location-item");
  if(rows.length <= 1){
    const onlyInput = rows[0] ? rows[0].querySelector("input") : null;
    if(onlyInput) onlyInput.value = "";
    updateLocationIndices();
    return;
  }
  const row = btn.closest(".location-item");
  if(row) row.remove();
  updateLocationIndices();
}

document.addEventListener("DOMContentLoaded", function(){
  const existingRemoveButtons = document.querySelectorAll(".remove-location-btn");
  existingRemoveButtons.forEach((button) => {
    button.addEventListener("click", function(){
      removeLocationRow(button);
    });
  });
  updateLocationIndices();
});

function markProfilePhotoForRemoval(inputId, imageId, button){
  var hiddenInput = document.getElementById(inputId);
  if(hiddenInput){
    hiddenInput.value = "1";
  }
  if(imageId){
    var previewImage = document.getElementById(imageId);
    if(previewImage){
      previewImage.style.opacity = "0.35";
    }
  }
  if(button){
    button.textContent = "Will Remove";
    button.disabled = true;
  }
}
</script>

</body>
</html>
