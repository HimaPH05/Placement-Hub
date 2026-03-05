<?php
session_start();
include("../db.php");

if (
    (!isset($_SESSION["role"]) || $_SESSION["role"] !== "company")
    && !isset($_SESSION["company_id"])
) {
    die("Unauthorized Access");
}

$search = "";

if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
    $stmt = $conn->prepare("
        SELECT id, student_id, name, branch, gpa, about, skills, file_name, created_at
        FROM student_resumes
        WHERE visibility = 'public'
        AND (name LIKE ? OR branch LIKE ? OR skills LIKE ?)
        ORDER BY created_at DESC
    ");
    $search_param = "%" . $search . "%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} else {
    $stmt = $conn->prepare("
        SELECT id, student_id, name, branch, gpa, about, skills, file_name, created_at
        FROM student_resumes
        WHERE visibility = 'public'
        ORDER BY created_at DESC
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

<div class="search-box">
  <form method="GET">
    <input
      type="text"
      name="search"
      placeholder="Search by name, branch, skill..."
      value="<?php echo htmlspecialchars($search); ?>"
    >
  </form>
</div>

<div class="resume-grid">

<?php if ($result->num_rows > 0): ?>
  <?php while ($resume = $result->fetch_assoc()): ?>

    <div class="resume-card">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="resume-avatar">

      <h4><?php echo htmlspecialchars($resume["name"]); ?></h4>
      <p>Branch: <?php echo htmlspecialchars($resume["branch"]); ?></p>
      <p>GPA: <?php echo htmlspecialchars($resume["gpa"]); ?></p>

      <?php if (!empty($resume["skills"])): ?>
        <p>Skills: <?php echo htmlspecialchars($resume["skills"]); ?></p>
      <?php endif; ?>

      <?php if (!empty($resume["about"])): ?>
        <p><?php echo htmlspecialchars($resume["about"]); ?></p>
      <?php endif; ?>

      <div class="resume-actions">
        <a href="../view_resume.php?id=<?php echo (int)$resume["id"]; ?>" target="_blank" class="action-btn view">View</a>
        <a href="../view_resume.php?id=<?php echo (int)$resume["id"]; ?>&dl=1" class="action-btn">Download</a>
      </div>
    </div>

  <?php endwhile; ?>
<?php else: ?>
  <p>No public resumes found.</p>
<?php endif; ?>

</div>

</div>

</body>
</html>
