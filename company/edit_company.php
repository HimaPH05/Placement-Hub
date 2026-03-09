<?php
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "company") {
    header("Location: ../login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$conn = new mysqli("localhost", "root", "", "detailsdb");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION["username"];

// ================= UPDATE LOGIC =================
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $companyName = $_POST["companyName"];
    $email = $_POST["email"];
    $phone = $_POST["phone"];
    $location = $_POST["location"];
    $industry = $_POST["industry"];
    $locationItems = array_values(array_filter(array_map("trim", preg_split('/[\r\n,]+/', (string)$location))));
    $locationCount = count($locationItems);

    $locationsColumnExists = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM companies LIKE 'locations'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $locationsColumnExists = true;
    }

    if ($locationsColumnExists) {
        $stmt = $conn->prepare("UPDATE companies 
            SET companyName=?, email=?, phone=?, location=?, industry=?, locations=? 
            WHERE username=?");
        $stmt->bind_param("sssssis",
            $companyName, $email, $phone, $location, $industry, $locationCount, $username);
    } else {
        $stmt = $conn->prepare("UPDATE companies 
            SET companyName=?, email=?, phone=?, location=?, industry=? 
            WHERE username=?");
        $stmt->bind_param("ssssss",
            $companyName, $email, $phone, $location, $industry, $username);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: home.php");
    exit();
}

// ================= FETCH EXISTING DATA =================
$stmt = $conn->prepare("SELECT companyName, email, phone, location, industry 
                        FROM companies WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$company = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Company Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="company_edit.css">
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
  <div class="card edit-card">
    <h2>Edit Company Profile</h2>
    <p class="subtitle">Keep your company details up to date for better visibility to students.</p>

    <form method="POST" class="profile-form">
      <div class="field-group">
        <label for="companyName">Company Name</label>
        <input id="companyName" type="text" name="companyName"
          value="<?php echo htmlspecialchars($company['companyName']); ?>" required>
      </div>

      <div class="field-group">
        <label for="email">Email</label>
        <input id="email" type="email" name="email"
          value="<?php echo htmlspecialchars($company['email']); ?>" required>
      </div>

      <div class="field-group">
        <label for="phone">Phone</label>
        <input id="phone" type="text" name="phone"
          value="<?php echo htmlspecialchars($company['phone']); ?>" required>
      </div>

      <div class="field-group">
        <label for="location">Location</label>
        <input id="location" type="text" name="location"
          value="<?php echo htmlspecialchars($company['location']); ?>" required>
      </div>

      <div class="field-group">
        <label for="industry">Industry</label>
        <input id="industry" type="text" name="industry"
          value="<?php echo htmlspecialchars($company['industry']); ?>" required>
      </div>

      <div class="actions">
        <button type="submit" class="btn">Update Profile</button>
        <a href="../company-password.php?mode=change" class="btn">Change Password</a>
        <a href="company.php" class="cancel-btn">Cancel</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>
