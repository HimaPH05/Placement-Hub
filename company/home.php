<?php
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "company") {
    header("Location: ../login.html");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$companyUsername = $_SESSION["username"];

$conn = new mysqli("localhost", "root", "", "detailsdb");

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
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Company Dashboard</title>
    <link rel="stylesheet" href="company_home.css">
</head>

<body>

<header class="topbar">
    <div>
        placement@university.edu | +91 9876543210
    </div>

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
            <a href="../logout.php">Logout</a>
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

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-text">
            <h1>Welcome, <?php echo htmlspecialchars($companyName); ?> 👋</h1>
            <p>Your gateway to hiring top students quickly and efficiently.</p>
            <button class="btn" onclick="window.location.href='company.php'">Explore</button>
        </div>

        <img src="https://cdn-icons-png.flaticon.com/512/3135/3135755.png" class="hero-img">
    </section>


    <section style="margin:60px 0 80px 0; text-align:center;">

    <h2 style="font-size:28px; color:#0d47a1; margin-bottom:30px;">
        Placement Officer
    </h2>

    <div style="
        width:650px;
        margin:0 auto;
        background:#eef1f5;
        padding:35px 40px;
        border-radius:18px;
        display:flex;
        align-items:center;
        gap:35px;
        text-align:left;
        box-shadow:0 6px 18px rgba(0,0,0,0.08);
    ">

        <div style="
            background:#dfe6f1;
            padding:15px;
            border-radius:50%;
            flex-shrink:0;
        ">
            <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png"
                 style="width:90px; height:90px; border-radius:50%;">
        </div>

        <div>
            <h3 style="font-size:20px; color:#0d47a1; margin-bottom:6px;">
                Dr. Anjali Menon
            </h3>
            <p style="margin-bottom:10px; color:#444;">Placement Officer</p>
            <p>📧 placement@college.edu</p>
            <p>📞 +91 98765 43210</p>
            <p>🏢 Training & Placement Cell, Block A</p>
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
