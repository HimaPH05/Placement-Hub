<?php
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "company") {
    header("Location: ../login.html");
    exit();
}

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

    $stmt = $conn->prepare("UPDATE companies 
        SET companyName=?, email=?, phone=?, location=?, industry=? 
        WHERE username=?");

    $stmt->bind_param("ssssss",
        $companyName, $email, $phone, $location, $industry, $username);

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
    <title>Edit Company Profile</title>
    <link rel="stylesheet" href="company_home.css">
</head>
<body>

<div class="edit-wrapper">
    <div class="edit-card">
        <h2>Edit Company Profile</h2>

        <form method="POST">

            <label>Company Name</label>
            <input type="text" name="companyName"
                value="<?php echo htmlspecialchars($company['companyName']); ?>" required>

            <label>Email</label>
            <input type="email" name="email"
                value="<?php echo htmlspecialchars($company['email']); ?>" required>

            <label>Phone</label>
            <input type="text" name="phone"
                value="<?php echo htmlspecialchars($company['phone']); ?>" required>

            <label>Location</label>
            <input type="text" name="location"
                value="<?php echo htmlspecialchars($company['location']); ?>" required>

            <label>Industry</label>
            <input type="text" name="industry"
                value="<?php echo htmlspecialchars($company['industry']); ?>" required>

            <button type="submit" class="btn">Update</button>

        </form>
    </div>
</div>

</body>