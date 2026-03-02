<?php
session_start();
include("../db.php");

if(!isset($_SESSION['company_id'])){
    die("Unauthorized Access");
}

$search = "";

if(isset($_GET['search'])){
    $search = $_GET['search'];
    $stmt = $conn->prepare("
    SELECT * FROM students 
    WHERE fullname LIKE ?
    AND resume_file IS NOT NULL
    AND resume_file != ''
");
    $search_param = "%".$search."%";
    $stmt->bind_param("s", $search_param);
} else {
    $stmt = $conn->prepare("
    SELECT * FROM students 
    WHERE resume_file IS NOT NULL 
    AND resume_file != ''
");
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Resumes - Placement Hub</title>
  <link rel="stylesheet" href="company_resume.css">
</head>

<body>

<header class="topbar">
  Student Resume Database
</header>

<nav class="navbar">
  <a href="home.php">Home</a>
  <a href="company.php">Company</a>
  <a href="applicants.php">Applicants</a>
  <a href="resumes.php" class="active">Resumes</a>
</nav>

<div class="container">

<!-- ================= SEARCH ================= -->
<div class="search-box">
  <form method="GET">
    <input
      type="text"
      name="search"
      placeholder="🔍 Search student name..."
      value="<?php echo htmlspecialchars($search); ?>"
    >
  </form>
</div>

<!-- ================= RESUME GRID ================= -->
<div class="resume-grid">

<?php if($result->num_rows > 0): ?>
  <?php while($student = $result->fetch_assoc()): ?>
    
    <div class="resume-card">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="resume-avatar">

      <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
<p>Register No: <?php echo htmlspecialchars($student['regno']); ?></p>
      <p>CGPA: <?php echo $student['cgpa']; ?></p>

      <div class="resume-actions">
    <?php if(!empty($student['resume_file'])): ?>
        <a href="../<?php echo $student['resume_file']; ?>" target="_blank" class="action-btn view">View</a>
        <a href="../<?php echo $student['resume_file']; ?>" download class="action-btn">Download</a>
    <?php else: ?>
        <span>No Resume Uploaded</span>
    <?php endif; ?>
</div>
    </div>

  <?php endwhile; ?>
<?php else: ?>
  <p>No students found.</p>
<?php endif; ?>

</div>

</div>

</body>
</html>