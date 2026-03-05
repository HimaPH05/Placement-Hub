<?php
include("../db.php"); // correct path

/* TEMP SESSION (REMOVE AFTER LOGIN SYSTEM READY) */
if(!isset($_SESSION['company_id'])){
    $_SESSION['company_id'] = 1;
}

$company_id = $_SESSION['company_id'];

/* =========================
   UPDATE COMPANY DETAILS
========================= */
if(isset($_POST['update_company'])){
    $name = $_POST['companyName'];
    $desc = $_POST['description'];
    $emp  = $_POST['employees'];
    $loc  = $_POST['locations'];

    $stmt = $conn->prepare("UPDATE companies 
        SET companyName=?, description=?, employees=?, locations=? 
        WHERE id=?");
    $stmt->bind_param("ssiii", $name, $desc, $emp, $loc, $company_id);
    $stmt->execute();
}

/* =========================
   ADD JOB
========================= */
if(isset($_POST['add_job'])){
    $title = $_POST['title'];
    $desc = $_POST['desc'];
    $openings = $_POST['openings'];
    $min_cgpa = $_POST['min_cgpa'];
    $location = $_POST['location'];

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
    $job_id = $_POST['job_id'];
    $title = $_POST['title'];
    $desc = $_POST['desc'];
    $openings = $_POST['openings'];
    $min_cgpa = $_POST['min_cgpa'];
    $location = $_POST['location'];

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
$company = $stmt->get_result()->fetch_assoc();

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
  <a href="applicants.html">Applicants</a>
  <a href="resumes.html">Resumes</a>
</nav>

<div class="container">

<!-- ================= COMPANY PROFILE ================= -->
<section class="card company-profile">

  <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="company-logo">

  <div class="company-info">
    <h2><?php echo htmlspecialchars($company['companyName']); ?></h2>
    <p><?php echo htmlspecialchars($company['description']); ?></p>

    <div class="company-stats">
      <span>👥 <?php echo $company['employees']; ?> Employees</span>
      <span>📍 <?php echo $company['locations']; ?> Locations</span>
      <span>💼 <?php echo $jobs->num_rows; ?> Open Roles</span>
    </div>
  </div>

  <button class="edit-btn" onclick="openCompanyModal()">✏ Edit</button>

</section>


<!-- ================= OPEN POSITIONS ================= -->
<section class="card">

  <div style="display:flex; justify-content:space-between; align-items:center;">
    <h3>Open Positions</h3>
    <button class="btn" onclick="openModal()">+ Add Job</button>
  </div>

  <div class="jobs">

  <?php if($jobs->num_rows > 0): ?>
    <?php while($job = $jobs->fetch_assoc()): ?>
      <div class="job">
        <div>
          <h4><?php echo htmlspecialchars($job['job_title']); ?></h4>
          <p><?php echo $job['openings']; ?> openings | 
             <?php echo htmlspecialchars($job['location']); ?></p>
          <p>Min CGPA: <?php echo htmlspecialchars($job['min_cgpa'] !== null ? $job['min_cgpa'] : 'No minimum'); ?></p>
        </div>

        <div>
          <button class="edit-btn"
            onclick="openEditModal(
              <?php echo $job['id']; ?>,
              '<?php echo addslashes($job['job_title']); ?>',
              '<?php echo addslashes($job['job_description']); ?>',
              <?php echo $job['openings']; ?>,
              <?php echo $job['min_cgpa'] !== null ? (float)$job['min_cgpa'] : 0; ?>,
              '<?php echo addslashes($job['location']); ?>'
            )">Edit</button>

          <a href="company.php?delete=<?php echo $job['id']; ?>"
             onclick="return confirm('Delete this job?')"
             class="action-btn reject">Delete</a>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No jobs added yet.</p>
  <?php endif; ?>

  </div>
</section>

</div>


<!-- ================= EDIT COMPANY MODAL ================= -->
<div class="modal" id="companyModal">
  <div class="modal-content">
    <h3>Edit Company Profile</h3>
    <form method="POST">
      <input name="companyName" value="<?php echo $company['companyName']; ?>" required>
      <input name="description" value="<?php echo $company['description']; ?>">
      <input name="employees" type="number" value="<?php echo $company['employees']; ?>">
      <input name="locations" type="number" value="<?php echo $company['locations']; ?>">

      <div class="modal-actions">
        <button type="submit" name="update_company" class="apply-btn">Save</button>
        <button type="button" onclick="closeCompanyModal()" class="cancel-btn">Cancel</button>
      </div>
    </form>
  </div>
</div>


<!-- ================= ADD JOB MODAL ================= -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <h3>Add Job</h3>
    <form method="POST">
      <input name="title" placeholder="Job Title" required>
      <input name="openings" type="number" placeholder="Openings" required>
      <input name="min_cgpa" type="number" step="0.01" min="0" max="10" placeholder="Minimum CGPA (e.g. 7.00)" required>
      <input name="location" placeholder="Location">
      <textarea name="desc" placeholder="Description"></textarea>

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
