<?php
require_once "database.php";

$mode = ($_GET["mode"] ?? "change") === "forgot" ? "forgot" : "change";
$message = "";
$messageClass = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mode = ($_POST["mode"] ?? "change") === "forgot" ? "forgot" : "change";
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $currentPassword = trim($_POST["current_password"] ?? "");
    $newPassword = trim($_POST["new_password"] ?? "");
    $confirmPassword = trim($_POST["confirm_password"] ?? "");

    if ($username === "" || $newPassword === "" || $confirmPassword === "") {
        $message = "Username, new password and confirm password are required.";
        $messageClass = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New password and confirm password do not match.";
        $messageClass = "error";
    } elseif (strlen($newPassword) < 6) {
        $message = "New password must be at least 6 characters.";
        $messageClass = "error";
    } else {
        $stmt = $conn->prepare("SELECT id, email, password FROM companies WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $message = "Company account not found.";
            $messageClass = "error";
        } else {
            $stmt->bind_result($companyId, $dbEmail, $dbPassword);
            $stmt->fetch();
            $allowReset = false;

            if ($mode === "change") {
                if ($currentPassword === "") {
                    $message = "Current password is required for change password.";
                    $messageClass = "error";
                } elseif (password_verify($currentPassword, $dbPassword)) {
                    $allowReset = true;
                } else {
                    $message = "Current password is incorrect.";
                    $messageClass = "error";
                }
            } else {
                if ($email === "") {
                    $message = "Registered email is required for forgot password.";
                    $messageClass = "error";
                } elseif (strcasecmp($email, $dbEmail) === 0) {
                    $allowReset = true;
                } else {
                    $message = "Username and registered email do not match.";
                    $messageClass = "error";
                }
            }

            if ($allowReset) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE companies SET password = ? WHERE id = ?");
                $update->bind_param("si", $newHash, $companyId);
                if ($update->execute()) {
                    $message = "Password updated successfully. You can login now.";
                    $messageClass = "success";
                } else {
                    $message = "Failed to update password. Try again.";
                    $messageClass = "error";
                }
                $update->close();
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Company Password</title>
  <style>
    * { box-sizing: border-box; font-family: Arial, sans-serif; }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(120deg, #2563eb, #14b8a6);
      padding: 20px;
    }
    .card {
      width: 100%;
      max-width: 430px;
      background: #fff;
      padding: 28px;
      border-radius: 12px;
      box-shadow: 0 10px 24px rgba(0,0,0,0.2);
    }
    h2 { margin: 0 0 8px; text-align: center; }
    .sub { margin: 0 0 12px; text-align: center; color: #444; font-size: 14px; }
    label { display: block; margin: 12px 0 6px; font-weight: 600; }
    input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    .password-wrapper { position: relative; }
    .password-wrapper input { padding-right: 40px; }
    .toggle-pass {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      color: #333;
      cursor: pointer;
      width: auto;
      margin: 0;
      padding: 0;
      font-size: 16px;
      z-index: 2;
    }
    .btn {
      width: 100%;
      margin-top: 18px;
      padding: 11px;
      border: none;
      border-radius: 6px;
      color: #fff;
      background: #2563eb;
      cursor: pointer;
      font-size: 15px;
    }
    .msg { margin-top: 14px; text-align: center; font-size: 14px; }
    .error { color: #b91c1c; }
    .success { color: #15803d; }
    .back {
      display: block;
      margin-top: 14px;
      text-align: center;
      color: #2563eb;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <form class="card" method="POST">
    <h2>Company Password</h2>
    <p class="sub"><?php echo $mode === "forgot" ? "Forgot Password" : "Change Password"; ?></p>
    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">

    <label for="username">Username</label>
    <input type="text" id="username" name="username" required>

    <?php if ($mode === "forgot"): ?>
      <label for="email">Registered Email</label>
      <input type="email" id="email" name="email" required>
    <?php else: ?>
      <label for="current_password">Current Password</label>
      <div class="password-wrapper">
        <input type="password" id="current_password" name="current_password" required>
        <button type="button" class="toggle-pass" id="toggleCurrentPassword">&#128065;</button>
      </div>
    <?php endif; ?>

    <label for="new_password">New Password</label>
    <div class="password-wrapper">
      <input type="password" id="new_password" name="new_password" required>
      <button type="button" class="toggle-pass" id="toggleNewPassword">&#128065;</button>
    </div>

    <label for="confirm_password">Confirm New Password</label>
    <div class="password-wrapper">
      <input type="password" id="confirm_password" name="confirm_password" required>
      <button type="button" class="toggle-pass" id="toggleConfirmPassword">&#128065;</button>
    </div>

    <button class="btn" type="submit">Update Password</button>

    <?php if ($message !== ""): ?>
      <div class="msg <?php echo $messageClass; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <a class="back" href="login.html">Back to Login</a>
  </form>

  <script>
    const cp = document.getElementById("current_password");
    const n = document.getElementById("new_password");
    const c = document.getElementById("confirm_password");
    const tcp = document.getElementById("toggleCurrentPassword");
    const tn = document.getElementById("toggleNewPassword");
    const tc = document.getElementById("toggleConfirmPassword");

    if (tcp && cp) {
      tcp.addEventListener("click", function () {
        cp.type = cp.type === "password" ? "text" : "password";
      });
    }
    if (tn && n) {
      tn.addEventListener("click", function () {
        n.type = n.type === "password" ? "text" : "password";
      });
    }
    if (tc && c) {
      tc.addEventListener("click", function () {
        c.type = c.type === "password" ? "text" : "password";
      });
    }
  </script>
</body>
</html>
