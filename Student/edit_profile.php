<?php
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student") {
    header("Location: ../login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "detailsdb");

$student_id = $_SESSION["user_id"];

/* UPDATE LOGIC */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname = $_POST["fullname"];
    $email    = $_POST["email"];
    $regno    = $_POST["regno"];
    $cgpa     = $_POST["cgpa"];

    $stmt = $conn->prepare("UPDATE students SET fullname=?, email=?, regno=?, cgpa=? WHERE id=?");
    $stmt->bind_param("sssdi", $fullname, $email, $regno, $cgpa, $student_id);
    $stmt->execute();

    header("Location: home.php");
    exit();
}

/* FETCH CURRENT DATA */
$stmt = $conn->prepare("SELECT fullname, email, regno, cgpa FROM students WHERE id=?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($fullname, $email, $regno, $cgpa);
$stmt->fetch();
?>

<!DOCTYPE html>
<html>
<head>

  <title>Edit Profile</title>
  <style>

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f4f6f9;
    }

    .edit-container {
      max-width: 500px;
      margin: 80px auto;
      background: white;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }

    .edit-container h2 {
      text-align: center;
      margin-bottom: 30px;
      color: #0d47a1;
    }

    .form-group {
      margin-bottom: 20px;
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
      transition: 0.3s;
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

  </style>

</head>
<body>


<div class="edit-container">
  <h2>Edit Profile</h2>

  <form method="POST">

    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="fullname" value="<?php echo $fullname; ?>" required>
    </div>

    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" value="<?php echo $email; ?>" required>
    </div>

    <div class="form-group">
      <label>Register Number</label>
      <input type="text" name="regno" value="<?php echo $regno; ?>" required>
    </div>

    <div class="form-group">
      <label>CGPA</label>
      <input type="number" step="0.01" name="cgpa" value="<?php echo $cgpa; ?>" required>
    </div>

    <button type="submit" class="btn">Update Profile</button>

  </form>

  <a href="home.php" class="back-link">← Back to Dashboard</a>
</div>
</body>
</html>