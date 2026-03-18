<?php
session_start();
require_once __DIR__ . "/../admin-credentials.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "company") {
    header("Location: ../login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$companyUsername = $_SESSION["username"];

require_once __DIR__ . "/../db-config.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT companyName, email, phone, location, industry FROM companies WHERE username = ?");
$stmt->bind_param("s", $companyUsername);
$stmt->execute();
$result = $stmt->get_result();
$company = $result->fetch_assoc();

if ($company) {
    $companyName = $company["companyName"];
    $email = $company["email"];
    $phone = $company["phone"];
    $location = $company["location"];
    $industry = $company["industry"];
} else {
    $companyName = "Company";
    $email = $phone = $location = $industry = "N/A";
}

$stmt->close();
$conn->close();

$adminProfile = get_admin_profile();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Company Dashboard</title>
    <link rel="stylesheet" href="company_home.css?v=20260309">
    <link rel="manifest" href="../manifest.webmanifest">
    <meta name="theme-color" content="#0e4ccf">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Placement Hub">
    <link rel="apple-touch-icon" href="../icons/apple-touch-icon.png">
    <link rel="icon" href="../icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../icons/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../icons/favicon-16.png">
    <script defer src="../pwa-register.js"></script>
</head>

<body>

<header class="topbar">
    <div>Company Dashboard</div>

    <div class="topbar-right">
        <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png"
             class="top-profile-icon"
             onclick="toggleProfile()">

        <div id="profileDropdown" class="top-profile-dropdown">
            <p><strong><?php echo htmlspecialchars($companyName); ?></strong></p>
            <hr>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($location); ?></p>
            <p><strong>Industry:</strong> <?php echo htmlspecialchars($industry); ?></p>
            <hr>
            <a href="edit_company.php">Edit Profile</a><br>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</header>

<nav class="navbar">
    <a href="home.php" class="active">Home</a>
    <a href="company.php">Company</a>
    <a href="applicants.php">Applicants</a>
    <a href="resumes.php">Resumes</a>
</nav>

<div class="container">

    <section class="card hero">
        <div class="hero-text">
            <h1>Welcome, <?php echo htmlspecialchars($companyName); ?></h1>
            <p>Your gateway to hiring top students quickly and efficiently.</p>
            <a class="btn" href="company.php">Explore Company</a>
        </div>

        <img src="https://cdn-icons-png.flaticon.com/512/3135/3135755.png" class="hero-img">
    </section>


    <section class="card officer-section">
      <h2>Placement Officer</h2>

      <div class="officer-card">
        <div class="officer-avatar-wrap">
            <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="officer-avatar">
        </div>

        <div class="officer-info">
            <h3><?php echo htmlspecialchars($adminProfile["name"]); ?></h3>
            <p class="designation"><?php echo htmlspecialchars($adminProfile["role_title"]); ?></p>
            <p>📧 <?php echo htmlspecialchars($adminProfile["email"]); ?></p>
            <p>📞 <?php echo htmlspecialchars($adminProfile["phone"]); ?></p>
            <p>🏢 <?php echo htmlspecialchars($adminProfile["department"]); ?></p>
        </div>
      </div>
    </section>
</div>

<script>
function toggleProfile() {
    const dropdown = document.getElementById("profileDropdown");
    dropdown.style.display =
        dropdown.style.display === "block" ? "none" : "block";
}
</script>

</body>
</html>
