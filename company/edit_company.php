<?php
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "company") {
    header("Location: ../login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

require_once __DIR__ . "/../db-config.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);
require_once __DIR__ . "/../profile-helpers.php";
include_once __DIR__ . "/../database_setup.php";

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
    $removeProfilePhoto = isset($_POST["remove_profile_photo"]);
    $profilePhotoPath = trim((string)($_POST["existing_profile_photo_path"] ?? ""));
    $locationItems = array_values(array_filter(array_map("trim", preg_split('/[\r\n,]+/', (string)$location))));
    $locationCount = count($locationItems);

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

        $safeName = "company_" . preg_replace("/[^a-z0-9]/i", "_", $username) . "_" . time() . "." . $photoExt;
        $targetPath = $uploadDir . "/" . $safeName;
        $relativePath = "uploads/company_photos/" . $safeName;

        if (!move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $targetPath)) {
            die("Unable to save company profile photo.");
        }

        $profilePhotoPath = $relativePath;
    }

    $locationsColumnExists = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM companies LIKE 'locations'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $locationsColumnExists = true;
    }

    if ($locationsColumnExists) {
        $stmt = $conn->prepare("UPDATE companies 
            SET companyName=?, email=?, phone=?, location=?, industry=?, locations=?, profile_photo_path=? 
            WHERE username=?");
        $stmt->bind_param("sssssiss",
            $companyName, $email, $phone, $location, $industry, $locationCount, $profilePhotoPath, $username);
    } else {
        $stmt = $conn->prepare("UPDATE companies 
            SET companyName=?, email=?, phone=?, location=?, industry=?, profile_photo_path=? 
            WHERE username=?");
        $stmt->bind_param("sssssss",
            $companyName, $email, $phone, $location, $industry, $profilePhotoPath, $username);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: home.php");
    exit();
}

// ================= FETCH EXISTING DATA =================
$stmt = $conn->prepare("SELECT companyName, email, phone, location, industry, COALESCE(profile_photo_path, '') AS profile_photo_path
                        FROM companies WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$company = $result->fetch_assoc();
$companyPhotoUrl = placementhub_company_photo_url($company ?: [], "../");
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Company Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="company_edit.css?v=20260402-photo">
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
</nav>

<div class="container">
  <div class="card edit-card">
    <h2>Edit Company Profile</h2>
    <p class="subtitle">Keep your company details up to date for better visibility to students.</p>

    <form method="POST" enctype="multipart/form-data" class="profile-form">
      <input type="hidden" name="existing_profile_photo_path" value="<?php echo htmlspecialchars((string)($company['profile_photo_path'] ?? '')); ?>">

      <div class="field-group">
        <label>Current Profile Photo</label>
        <div class="profile-photo-row">
          <img src="<?php echo htmlspecialchars($companyPhotoUrl); ?>" alt="Company profile photo" class="profile-preview-image" id="companyProfilePreviewImage">
          <?php if (!empty($company['profile_photo_path'])): ?>
            <input type="hidden" name="remove_profile_photo" id="companyRemoveProfilePhotoInput" value="0">
            <button type="button" class="mini-remove-btn" onclick="markProfilePhotoForRemoval('companyRemoveProfilePhotoInput', 'companyProfilePreviewImage', this)">Remove Profile Pic</button>
          <?php endif; ?>
        </div>
      </div>

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

      <div class="field-group">
        <label for="profile_photo">Profile Photo</label>
        <input id="profile_photo" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
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
<script>
function markProfilePhotoForRemoval(inputId, imageId, button) {
  var hiddenInput = document.getElementById(inputId);
  var previewImage = document.getElementById(imageId);
  if (hiddenInput) {
    hiddenInput.value = "1";
  }
  if (previewImage) {
    previewImage.style.opacity = "0.35";
  }
  if (button) {
    button.textContent = "Will Remove";
    button.disabled = true;
  }
}
</script>
</html>
