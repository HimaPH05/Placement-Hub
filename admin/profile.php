<?php
require_once __DIR__ . "/auth-check.php";
require_once __DIR__ . "/../admin-credentials.php";
require_once __DIR__ . "/../profile-helpers.php";

$profileMessage = "";
$profileMessageClass = "";
$teamMessage = "";
$teamMessageClass = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {
  $name = trim($_POST["name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $roleTitle = trim($_POST["role_title"] ?? "");
  $department = trim($_POST["department"] ?? "");
  $phone = trim($_POST["phone"] ?? "");
  $removeProfilePhoto = isset($_POST["remove_profile_photo"]);
  $currentProfile = get_admin_profile();
  $profilePhotoPath = trim((string)($currentProfile["profile_photo_path"] ?? ""));

  if ($name === "" || $email === "") {
    $profileMessage = "Name and email are required.";
    $profileMessageClass = "error";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $profileMessage = "Enter a valid email address.";
    $profileMessageClass = "error";
  } else {
    if ($removeProfilePhoto) {
      $profilePhotoPath = "";
    }

    if (isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES["profile_photo"]["error"] !== UPLOAD_ERR_OK) {
        $profileMessage = "Failed to upload profile photo.";
        $profileMessageClass = "error";
      } else {
        $allowedImageExt = ["jpg", "jpeg", "png", "webp"];
        $photoExt = strtolower(pathinfo((string)$_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
        $photoSize = (int)($_FILES["profile_photo"]["size"] ?? 0);
        $photoInfo = @getimagesize($_FILES["profile_photo"]["tmp_name"]);

        if (!in_array($photoExt, $allowedImageExt, true) || $photoInfo === false) {
          $profileMessage = "Only JPG, PNG, or WEBP profile photos are allowed.";
          $profileMessageClass = "error";
        } elseif ($photoSize > 2 * 1024 * 1024) {
          $profileMessage = "Profile photo must be 2 MB or smaller.";
          $profileMessageClass = "error";
        } else {
          $uploadDir = __DIR__ . "/../uploads/admin_photos";
          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
          }

          $safeName = "admin_profile_" . time() . "." . $photoExt;
          $targetPath = $uploadDir . "/" . $safeName;
          $relativePath = "uploads/admin_photos/" . $safeName;

          if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $targetPath)) {
            $profilePhotoPath = $relativePath;
          } else {
            $profileMessage = "Unable to save profile photo.";
            $profileMessageClass = "error";
          }
        }
      }
    }

    if ($profileMessageClass === "error") {
      $profile = array_merge($currentProfile, [
        "name" => $name,
        "email" => $email,
        "role_title" => $roleTitle,
        "department" => $department,
        "phone" => $phone,
        "profile_photo_path" => $profilePhotoPath
      ]);
    } else {
    $saved = save_admin_profile([
      "name" => $name,
      "email" => $email,
      "role_title" => $roleTitle,
      "department" => $department,
      "phone" => $phone,
      "profile_photo_path" => $profilePhotoPath
    ]);

    if ($saved) {
      $profileMessage = "Profile updated successfully.";
      $profileMessageClass = "success";
    } else {
      $profileMessage = "Unable to update profile.";
      $profileMessageClass = "error";
    }
    }
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_team"])) {
  $names = $_POST["team_name"] ?? [];
  $roles = $_POST["team_role"] ?? [];
  $mobiles = $_POST["team_mobile"] ?? [];
  $existingPhotos = $_POST["team_existing_photo"] ?? [];
  $members = [];

  if (is_array($names) && is_array($roles) && is_array($mobiles)) {
    $count = max(count($names), count($roles), count($mobiles));
    for ($i = 0; $i < $count; $i++) {
      $memberName = trim((string)($names[$i] ?? ""));
      $memberRole = trim((string)($roles[$i] ?? ""));
      $memberMobile = trim((string)($mobiles[$i] ?? ""));
      $memberPhoto = trim((string)($existingPhotos[$i] ?? ""));
      if ($memberName === "" && $memberRole === "" && $memberMobile === "") {
        continue;
      }

      if (isset($_FILES["team_photo"]["name"][$i]) && (string)$_FILES["team_photo"]["name"][$i] !== "") {
        $uploadError = (int)($_FILES["team_photo"]["error"][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
          $teamMessage = "Failed to upload one of the team member photos.";
          $teamMessageClass = "error";
          $members = [];
          break;
        }

        $tmpName = (string)($_FILES["team_photo"]["tmp_name"][$i] ?? "");
        $photoExt = strtolower(pathinfo((string)($_FILES["team_photo"]["name"][$i] ?? ""), PATHINFO_EXTENSION));
        $photoSize = (int)($_FILES["team_photo"]["size"][$i] ?? 0);
        $photoInfo = $tmpName !== "" ? @getimagesize($tmpName) : false;
        $allowedImageExt = ["jpg", "jpeg", "png", "webp"];

        if (!in_array($photoExt, $allowedImageExt, true) || $photoInfo === false) {
          $teamMessage = "Only JPG, PNG, or WEBP team member photos are allowed.";
          $teamMessageClass = "error";
          $members = [];
          break;
        }

        if ($photoSize > 2 * 1024 * 1024) {
          $teamMessage = "Each team member photo must be 2 MB or smaller.";
          $teamMessageClass = "error";
          $members = [];
          break;
        }

        $uploadDir = __DIR__ . "/../uploads/admin_team_photos";
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0777, true);
        }

        $safeName = "team_member_" . $i . "_" . time() . "." . $photoExt;
        $targetPath = $uploadDir . "/" . $safeName;
        $relativePath = "uploads/admin_team_photos/" . $safeName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
          $teamMessage = "Unable to save one of the team member photos.";
          $teamMessageClass = "error";
          $members = [];
          break;
        }

        $memberPhoto = $relativePath;
      }

      $members[] = [
        "name" => $memberName,
        "role" => $memberRole,
        "mobile" => $memberMobile,
        "profile_photo_path" => $memberPhoto
      ];
    }
  }

  if ($teamMessageClass === "error") {
    $teamMembers = $members;
  } elseif (count($members) === 0) {
    $teamMessage = "Add at least one team member.";
    $teamMessageClass = "error";
  } else {
    if (save_admin_team_members($members)) {
      $teamMessage = "Placement team updated successfully.";
      $teamMessageClass = "success";
    } else {
      $teamMessage = "Unable to update placement team.";
      $teamMessageClass = "error";
    }
  }
}

$profile = get_admin_profile();
$teamMembers = get_admin_team_members();
$adminPhotoUrl = placementhub_admin_photo_url($profile, "../");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Profile</title>
  <link rel="stylesheet" href="astyle.css?v=20260402-photo">
  <script defer src="script.js?v=20260402-photo"></script>
</head>

<body>
<header class="topbar">Admin Dashboard</header>
<nav class="navbar">
  <div class="logo">Placement Hub Admin</div>

  <div class="links">
    <a href="index.php">Home</a>
    <a href="company.php">Company</a>
    <a href="resume.php">Resumes</a>
    <a href="feedback.php">Feedback</a>
    <a href="profile.php" class="active">Admin Profile</a>
    <a href="logout.php" class="logout" id="logoutBtn">Logout</a>
  </div>
</nav>

<div class="container">
  <h2 class="page-title">Admin Profile</h2>

  <div class="profile-card">
    <img src="<?php echo htmlspecialchars($adminPhotoUrl); ?>" class="profile-avatar" alt="Admin profile photo">

    <div class="profile-info">
      <h3><?php echo htmlspecialchars($profile["name"]); ?></h3>
      <p><b>Email:</b> <?php echo htmlspecialchars($profile["email"]); ?></p>
      <p><b>Role:</b> <?php echo htmlspecialchars($profile["role_title"]); ?></p>
      <p><b>Department:</b> <?php echo htmlspecialchars($profile["department"]); ?></p>
      <p><b>Phone:</b> <?php echo htmlspecialchars($profile["phone"]); ?></p>
    </div>
  </div>

  <div class="info-card profile-edit-card">
    <h3>Edit Profile</h3>
    <form method="POST" enctype="multipart/form-data" class="profile-form">
      <input type="hidden" name="update_profile" value="1">

      <label for="name">Name</label>
      <input id="name" name="name" value="<?php echo htmlspecialchars($profile["name"]); ?>" required>

      <label for="email">Email</label>
      <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($profile["email"]); ?>" required>

      <label for="role_title">Role</label>
      <input id="role_title" name="role_title" value="<?php echo htmlspecialchars($profile["role_title"]); ?>">

      <label for="department">Department</label>
      <input id="department" name="department" value="<?php echo htmlspecialchars($profile["department"]); ?>">

      <label for="phone">Phone</label>
      <input id="phone" name="phone" value="<?php echo htmlspecialchars($profile["phone"]); ?>">

      <label for="profile_photo">Profile Photo</label>
      <input id="profile_photo" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
      <div class="profile-photo-row">
        <img src="<?php echo htmlspecialchars($adminPhotoUrl); ?>" alt="Admin profile preview" class="profile-avatar" id="adminProfilePreviewImage">
        <?php if (!empty($profile["profile_photo_path"])): ?>
          <input type="hidden" name="remove_profile_photo" id="adminRemoveProfilePhotoInput" value="0">
          <button type="button" class="mini-remove-btn" onclick="markProfilePhotoForRemoval('adminRemoveProfilePhotoInput', 'adminProfilePreviewImage', this)">Remove Profile Pic</button>
        <?php endif; ?>
      </div>
      <div class="hint">This photo will be shown anywhere the admin profile is displayed.</div>

      <div class="actions">
        <button type="submit" class="primary">Save Profile</button>
        <a href="../admin-password.php?mode=change" class="cancel">Change Password</a>
      </div>
      <?php if ($profileMessage !== ""): ?>
        <p class="profile-msg <?php echo $profileMessageClass; ?>"><?php echo htmlspecialchars($profileMessage); ?></p>
      <?php endif; ?>
    </form>
  </div>

  <h3 class="section-title">Placement Team Members</h3>

  <div class="team-grid">
    <?php foreach ($teamMembers as $member): ?>
      <div class="team-card">
        <img src="<?php echo htmlspecialchars(placementhub_media_url((string)($member["profile_photo_path"] ?? ""), "../")); ?>" class="profile-avatar" alt="Team member photo">
        <h4><?php echo htmlspecialchars($member["name"] ?? ""); ?></h4>
        <p><?php echo htmlspecialchars($member["role"] ?? ""); ?></p>
        <p><?php echo htmlspecialchars($member["mobile"] ?? ""); ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="info-card profile-edit-card">
    <h3>Edit Placement Team Members</h3>
    <form method="POST" enctype="multipart/form-data" class="profile-form" id="teamForm">
      <input type="hidden" name="update_team" value="1">

      <div id="teamMembersContainer">
        <?php foreach ($teamMembers as $index => $member): ?>
          <div class="team-member-group" data-team-member>
            <div class="team-member-head">
              <span class="team-member-title">Member <?php echo $index + 1; ?></span>
              <button type="button" class="remove-member-btn" data-remove-member>Remove</button>
            </div>

            <label>Member Name</label>
            <input
              name="team_name[]"
              value="<?php echo htmlspecialchars($member["name"] ?? ""); ?>"
              placeholder="Team member name">

            <label>Member Photo</label>
            <input type="hidden" name="team_existing_photo[]" value="<?php echo htmlspecialchars((string)($member["profile_photo_path"] ?? "")); ?>">
            <input name="team_photo[]" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <img src="<?php echo htmlspecialchars(placementhub_media_url((string)($member["profile_photo_path"] ?? ""), "../")); ?>" class="profile-avatar" alt="Team member preview">

            <label>Member Role</label>
            <input
              name="team_role[]"
              value="<?php echo htmlspecialchars($member["role"] ?? ""); ?>"
              placeholder="Team member role">

            <label>Member Mobile Number</label>
            <input
              name="team_mobile[]"
              value="<?php echo htmlspecialchars($member["mobile"] ?? ""); ?>"
              placeholder="+91 9876543210">
          </div>
        <?php endforeach; ?>
      </div>

      <button type="button" class="cancel add-member-btn" id="addTeamMemberBtn">Add Member</button>

      <button type="submit" class="primary">Save Team Members</button>
      <?php if ($teamMessage !== ""): ?>
        <p class="profile-msg <?php echo $teamMessageClass; ?>"><?php echo htmlspecialchars($teamMessage); ?></p>
      <?php endif; ?>
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
