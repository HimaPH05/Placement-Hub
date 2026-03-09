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
    <title>Edit Company Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Manrope", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 15% 20%, rgba(13, 71, 161, 0.18), transparent 40%),
                radial-gradient(circle at 85% 5%, rgba(0, 150, 136, 0.12), transparent 35%),
                linear-gradient(145deg, #eef3fb 0%, #f8fbff 55%, #eaf3f2 100%);
            color: #1e2a39;
            padding: 36px 16px;
        }

        .edit-wrapper {
            max-width: 760px;
            margin: 0 auto;
        }

        .edit-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(13, 71, 161, 0.14);
            border-radius: 20px;
            padding: 34px 30px;
            box-shadow: 0 22px 40px rgba(11, 44, 87, 0.14);
            backdrop-filter: blur(2px);
        }

        .edit-card h2 {
            font-size: 2rem;
            line-height: 1.2;
            margin-bottom: 8px;
            color: #0f2f5b;
            letter-spacing: 0.2px;
        }

        .subtitle {
            color: #4d607a;
            font-size: 0.96rem;
            margin-bottom: 24px;
        }

        .profile-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field-group label {
            font-size: 0.92rem;
            font-weight: 700;
            color: #27446f;
            letter-spacing: 0.15px;
        }

        .field-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #c8d3e1;
            border-radius: 10px;
            background: #f9fcff;
            font-size: 1rem;
            color: #1b2a3d;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .field-group input:focus {
            outline: none;
            border-color: #0d47a1;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.15);
        }

        .actions {
            margin-top: 6px;
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 13px 18px;
            font-size: 1rem;
            font-weight: 700;
            color: #ffffff;
            cursor: pointer;
            background: linear-gradient(135deg, #0d47a1 0%, #1565c0 55%, #1e88e5 100%);
            box-shadow: 0 12px 20px rgba(21, 101, 192, 0.24);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px rgba(21, 101, 192, 0.28);
        }

        @media (max-width: 640px) {
            body {
                padding: 20px 12px;
            }

            .edit-card {
                padding: 24px 18px;
                border-radius: 16px;
            }

            .edit-card h2 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>

<div class="edit-wrapper">
    <div class="edit-card">
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
            </div>

        </form>
    </div>
</div>

</body>
