<?php
require_once __DIR__ . "/auth-check.php";
require_once __DIR__ . "/../admin-credentials.php";

$message = "";
$messageClass = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {
  $name = trim($_POST["name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $roleTitle = trim($_POST["role_title"] ?? "");
  $department = trim($_POST["department"] ?? "");
  $phone = trim($_POST["phone"] ?? "");

  if ($name === "" || $email === "") {
    $message = "Name and email are required.";
    $messageClass = "error";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = "Enter a valid email address.";
    $messageClass = "error";
  } else {
    $saved = save_admin_profile([
      "name" => $name,
      "email" => $email,
      "role_title" => $roleTitle,
      "department" => $department,
      "phone" => $phone
    ]);

    if ($saved) {
      $message = "Profile updated successfully.";
      $messageClass = "success";
    } else {
      $message = "Unable to update profile.";
      $messageClass = "error";
    }
  }
}

$profile = get_admin_profile();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Profile</title>
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
    <a href="feedback.php">Feedback</a>
    <a href="profile.php" class="active">Admin Profile</a>
    <a href="../logout.php" class="logout" id="logoutBtn">Logout</a>
  </nav>
</header>

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

      <button type="submit" class="primary">Save Profile</button>
      <?php if ($message !== ""): ?>
        <p class="profile-msg <?php echo $messageClass; ?>"><?php echo htmlspecialchars($message); ?></p>
      <?php endif; ?>
    </form>
  </div>

  <h3 class="section-title">Placement Team Members</h3>

  <div class="team-grid">
    <div class="team-card">
      <h4>Dr. John Smith</h4>
      <p>Head Coordinator</p>
    </div>

    <div class="team-card">
      <h4>Sarah Johnson</h4>
      <p>Assistant Coordinator</p>
    </div>

    <div class="team-card">
      <h4>Emily Davis</h4>
      <p>Industry Liaison</p>
    </div>

    <div class="team-card">
      <h4>David Wilson</h4>
      <p>Student Relations</p>
    </div>
  </div>

</div>

</body>
</html>
