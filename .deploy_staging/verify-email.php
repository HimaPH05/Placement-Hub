<?php
declare(strict_types=1);

require_once __DIR__ . "/db-config.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);
if ($conn->connect_error) {
    http_response_code(500);
    echo "Database connection failed";
    exit;
}

$token = trim((string)($_GET["token"] ?? ""));
if ($token === "") {
    http_response_code(400);
    echo "Missing token";
    exit;
}

$tokenHash = hash("sha256", $token);

$colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email_verify_token_hash'");
if (!$colCheck || $colCheck->num_rows === 0) {
    http_response_code(500);
    echo "Email verification is not enabled in the database yet. Run database setup once.";
    exit;
}

$stmt = $conn->prepare("
  SELECT id, email_verify_expires_at, COALESCE(is_email_verified, 0) AS is_email_verified
  FROM students
  WHERE email_verify_token_hash = ?
  LIMIT 1
");
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$status = "invalid";
$message = "Invalid or expired verification link.";

if ($row) {
    $isVerified = ((int)($row["is_email_verified"] ?? 0)) === 1;
    $expiresAt = (string)($row["email_verify_expires_at"] ?? "");
    $expired = false;
    if ($expiresAt !== "") {
        $expired = strtotime($expiresAt) < time();
    }

    if ($isVerified) {
        $status = "ok";
        $message = "Email already verified. You can login now.";
    } elseif ($expired) {
        $status = "expired";
        $message = "Verification link expired. Please resend verification email from login.";
    } else {
        $id = (int)$row["id"];
        $now = (new DateTimeImmutable())->format("Y-m-d H:i:s");
        $u = $conn->prepare("
          UPDATE students
          SET is_email_verified = 1,
              email_verified_at = ?,
              email_verify_token_hash = NULL,
              email_verify_expires_at = NULL
          WHERE id = ?
        ");
        $u->bind_param("si", $now, $id);
        $u->execute();
        $u->close();

        $status = "ok";
        $message = "Email verified successfully. You can login now.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Verification</title>
  <style>
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family: Arial, sans-serif;
      background: linear-gradient(120deg, #0e4ccf, #14b8a6); padding:18px; }
    .card { width:min(560px, 100%); background:#fff; border-radius:14px; padding:22px; box-shadow:0 18px 40px rgba(0,0,0,.25); }
    h2 { margin:0 0 10px; color:#0d47a1; }
    p { margin:0 0 14px; color:#24415f; line-height:1.5; }
    .pill { display:inline-block; padding:6px 12px; border-radius:999px; font-weight:800; font-size:12px; }
    .ok { background:#e9fff1; color:#1d6a39; border:1px solid #bce9cd; }
    .bad { background:#fff2f2; color:#9b1c1c; border:1px solid #f2b8b8; }
    a { display:inline-block; margin-top:10px; text-decoration:none; padding:10px 14px; border-radius:10px;
      background: linear-gradient(130deg, #0e4ccf, #2b7bff); color:#fff; font-weight:800; }
  </style>
</head>
<body>
  <div class="card">
    <h2>Placement Hub Email Verification</h2>
    <?php if ($status === "ok"): ?>
      <div class="pill ok">Verified</div>
    <?php else: ?>
      <div class="pill bad">Not Verified</div>
    <?php endif; ?>
    <p style="margin-top:10px;"><?php echo htmlspecialchars($message); ?></p>
    <a href="login.php?verify=1">Go to Login</a>
  </div>
</body>
</html>
