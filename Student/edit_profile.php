<?php
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: ../login.html");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$conn = new mysqli("localhost", "root", "", "detailsdb");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = (int)$_SESSION["user_id"];
$error = "";
$hasScorecardColumn = false;
$hasEmailColumn = false;

$colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'ktu_scorecard_path'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasScorecardColumn = true;
}
$emailColCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
if ($emailColCheck && $emailColCheck->num_rows > 0) {
    $hasEmailColumn = true;
}

/* FETCH CURRENT DATA */
if ($hasScorecardColumn) {
    if ($hasEmailColumn) {
        $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa, ktu_scorecard_path FROM students WHERE id=?");
    } else {
        $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa, ktu_scorecard_path FROM students WHERE id=?");
    }
} else {
    if ($hasEmailColumn) {
        $stmt = $conn->prepare("SELECT fullname, email, regno, cgpa FROM students WHERE id=?");
    } else {
        $stmt = $conn->prepare("SELECT fullname, '' AS email, regno, cgpa FROM students WHERE id=?");
    }
}
$stmt->bind_param("i", $student_id);
$stmt->execute();
if ($hasScorecardColumn) {
    $stmt->bind_result($fullname, $email, $regno, $cgpa, $ktu_scorecard_path);
} else {
    $stmt->bind_result($fullname, $email, $regno, $cgpa);
    $ktu_scorecard_path = "";
}
$stmt->fetch();
$stmt->close();

/* UPDATE LOGIC */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST["fullname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $regno = trim($_POST["regno"] ?? "");
    $cgpa = (float)($_POST["cgpa"] ?? 0);

    $newScorecardPath = $ktu_scorecard_path;

    if ($hasScorecardColumn && isset($_FILES["ktu_scorecard"]) && $_FILES["ktu_scorecard"]["error"] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES["ktu_scorecard"]["error"] !== UPLOAD_ERR_OK) {
            $error = "Failed to upload scorecard file.";
        } else {
            $allowedExt = ["pdf", "jpg", "jpeg", "png"];
            $original = $_FILES["ktu_scorecard"]["name"];
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                $error = "Only PDF/JPG/PNG scorecards are allowed.";
            } else {
                $uploadDir = __DIR__ . "/../uploads/scorecards";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $safeName = "scorecard_" . $student_id . "_" . time() . "." . $ext;
                $targetPath = $uploadDir . "/" . $safeName;
                $relativePath = "uploads/scorecards/" . $safeName;

                if (move_uploaded_file($_FILES["ktu_scorecard"]["tmp_name"], $targetPath)) {
                    $newScorecardPath = $relativePath;
                } else {
                    $error = "Unable to save scorecard file.";
                }
            }
        }
    }

    if ($error === "") {
        if ($hasScorecardColumn) {
            if ($hasEmailColumn) {
                $stmt = $conn->prepare("UPDATE students SET fullname=?, email=?, regno=?, cgpa=?, ktu_scorecard_path=? WHERE id=?");
                $stmt->bind_param("sssdsi", $fullname, $email, $regno, $cgpa, $newScorecardPath, $student_id);
            } else {
                $stmt = $conn->prepare("UPDATE students SET fullname=?, regno=?, cgpa=?, ktu_scorecard_path=? WHERE id=?");
                $stmt->bind_param("ssdsi", $fullname, $regno, $cgpa, $newScorecardPath, $student_id);
            }
        } else {
            if ($hasEmailColumn) {
                $stmt = $conn->prepare("UPDATE students SET fullname=?, email=?, regno=?, cgpa=? WHERE id=?");
                $stmt->bind_param("sssdi", $fullname, $email, $regno, $cgpa, $student_id);
            } else {
                $stmt = $conn->prepare("UPDATE students SET fullname=?, regno=?, cgpa=? WHERE id=?");
                $stmt->bind_param("ssdi", $fullname, $regno, $cgpa, $student_id);
            }
        }
        $stmt->execute();
        $stmt->close();

        header("Location: home.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Edit Profile</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f4f6f9;
    }

    .edit-container {
      max-width: 520px;
      margin: 60px auto;
      background: white;
      padding: 34px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }

    .edit-container h2 {
      text-align: center;
      margin-bottom: 22px;
      color: #0d47a1;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #333;
    }

    .form-group input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }

    .form-group input:focus {
      border-color: #0d47a1;
      outline: none;
    }

    .btn {
      width: 100%;
      padding: 12px;
      background: #0d47a1;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 15px;
      cursor: pointer;
    }

    .btn:hover {
      background: #08306b;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 15px;
      text-decoration: none;
      color: #0d47a1;
      font-size: 14px;
    }

    .hint {
      font-size: 12px;
      color: #666;
      margin-top: 4px;
    }

    .error {
      background: #ffebee;
      color: #b71c1c;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 14px;
      font-size: 14px;
    }
  </style>
</head>
<body>

<div class="edit-container">
  <h2>Edit Profile</h2>

  <?php if ($error !== ""): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>" required>
    </div>

    <?php if ($hasEmailColumn): ?>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
      </div>
    <?php endif; ?>

    <div class="form-group">
      <label>Register Number</label>
      <input type="text" name="regno" value="<?php echo htmlspecialchars($regno); ?>" required>
    </div>

    <div class="form-group">
      <label>CGPA</label>
      <input type="number" step="0.01" min="0" max="10" name="cgpa" value="<?php echo htmlspecialchars($cgpa); ?>" required>
    </div>

    <?php if ($hasScorecardColumn): ?>
      <div class="form-group">
        <label>KTU Scorecard (PDF/JPG/PNG)</label>
        <input type="file" name="ktu_scorecard" accept=".pdf,.jpg,.jpeg,.png">
        <div class="hint">Upload latest KTU scorecard to become eligible for applications.</div>
        <?php if (!empty($ktu_scorecard_path)): ?>
          <div class="hint">Current: <a href="../<?php echo htmlspecialchars($ktu_scorecard_path); ?>" target="_blank">View uploaded scorecard</a></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn">Update Profile</button>
  </form>

  <a href="home.php" class="back-link">Back to Dashboard</a>
</div>

</body>
</html>
