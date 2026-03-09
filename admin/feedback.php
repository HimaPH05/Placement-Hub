<?php
require_once __DIR__ . "/auth-check.php";

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    die("Database connection failed");
}

$conn->query("CREATE TABLE IF NOT EXISTS student_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    username VARCHAR(120) NOT NULL,
    feedback TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_created (student_id, created_at)
)");

$rows = [];
$result = $conn->query("SELECT id, student_id, username, feedback, created_at FROM student_feedback ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Student Feedback</title>
  <link rel="stylesheet" href="astyle.css">
  <script defer src="script.js?v=10"></script>
</head>

<body>
<header class="navbar">
  <div class="logo">Placement Hub Admin</div>

  <nav>
    <a href="index.php">Home</a>
    <a href="company.php">Company</a>
    <a href="resume.php">Resumes</a>
    <a href="feedback.php" class="active">Feedback</a>
    <a href="profile.php">Admin Profile</a>
    <a href="../logout.php" class="logout" id="logoutBtn">Logout</a>
  </nav>
</header>

<div class="container">
  <div class="page-head">
    <h2>Student Feedback</h2>
  </div>

  <?php if (count($rows) === 0): ?>
    <div class="info-card">
      <h3>No feedback yet</h3>
      <p>Student feedback entries will appear here after submission.</p>
    </div>
  <?php else: ?>
    <div class="feedback-grid">
      <?php foreach ($rows as $item): ?>
        <div class="feedback-card">
          <div class="feedback-head">
            <h3><?php echo htmlspecialchars($item["username"]); ?></h3>
            <span><?php echo htmlspecialchars($item["created_at"]); ?></span>
          </div>
          <p class="feedback-meta">Student ID: <?php echo (int)$item["student_id"]; ?></p>
          <p class="feedback-text"><?php echo nl2br(htmlspecialchars($item["feedback"])); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
