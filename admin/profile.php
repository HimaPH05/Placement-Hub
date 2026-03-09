<?php
require_once __DIR__ . "/auth-check.php";
require_once __DIR__ . "/../admin-credentials.php";

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

  if ($name === "" || $email === "") {
    $profileMessage = "Name and email are required.";
    $profileMessageClass = "error";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $profileMessage = "Enter a valid email address.";
    $profileMessageClass = "error";
  } else {
    $saved = save_admin_profile([
      "name" => $name,
      "email" => $email,
      "role_title" => $roleTitle,
      "department" => $department,
      "phone" => $phone
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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_team"])) {
  $names = $_POST["team_name"] ?? [];
  $roles = $_POST["team_role"] ?? [];
  $members = [];

  if (is_array($names) && is_array($roles)) {
    $count = max(count($names), count($roles));
    for ($i = 0; $i < $count; $i++) {
      $memberName = trim((string)($names[$i] ?? ""));
      $memberRole = trim((string)($roles[$i] ?? ""));
      if ($memberName === "" && $memberRole === "") {
        continue;
      }
      $members[] = [
        "name" => $memberName,
        "role" => $memberRole
      ];
    }
  }

  if (count($members) === 0) {
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
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Profile</title>
  <link rel="stylesheet" href="astyle.css?v=20260309">
  <script defer src="script.js?v=10"></script>
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
    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="profile-avatar">

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
    <form method="POST" class="profile-form">
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
        <h4><?php echo htmlspecialchars($member["name"] ?? ""); ?></h4>
        <p><?php echo htmlspecialchars($member["role"] ?? ""); ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="info-card profile-edit-card">
    <h3>Edit Placement Team Members</h3>
    <form method="POST" class="profile-form">
      <input type="hidden" name="update_team" value="1">

      <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="team-member-group">
          <label for="team_name_<?php echo $i; ?>">Member <?php echo $i + 1; ?> Name</label>
          <input
            id="team_name_<?php echo $i; ?>"
            name="team_name[]"
            value="<?php echo htmlspecialchars($teamMembers[$i]["name"] ?? ""); ?>"
            placeholder="Team member name">

          <label for="team_role_<?php echo $i; ?>">Member <?php echo $i + 1; ?> Role</label>
          <input
            id="team_role_<?php echo $i; ?>"
            name="team_role[]"
            value="<?php echo htmlspecialchars($teamMembers[$i]["role"] ?? ""); ?>"
            placeholder="Team member role">
        </div>
      <?php endfor; ?>

      <button type="submit" class="primary">Save Team Members</button>
      <?php if ($teamMessage !== ""): ?>
        <p class="profile-msg <?php echo $teamMessageClass; ?>"><?php echo htmlspecialchars($teamMessage); ?></p>
      <?php endif; ?>
    </form>
  </div>

</div>

</body>
</html>
