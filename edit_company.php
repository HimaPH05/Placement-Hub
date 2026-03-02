<?php
include "db.php";
$company_id = $_SESSION['company_id'];

if(isset($_POST['update'])){

    $desc = $_POST['description'];
    $emp = $_POST['employees'];
    $loc = $_POST['locations'];
    $hr_name = $_POST['hr_name'];
    $hr_email = $_POST['hr_email'];
    $hr_phone = $_POST['hr_phone'];

    /* UPDATE PROFILE */
    $stmt = $conn->prepare("REPLACE INTO company_profiles 
        (company_id, description, employees_count, locations_count)
        VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isii", $company_id, $desc, $emp, $loc);
    $stmt->execute();

    /* UPDATE HR */
    $stmt = $conn->prepare("REPLACE INTO hr_contacts 
        (company_id, hr_name, hr_email, hr_phone)
        VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $company_id, $hr_name, $hr_email, $hr_phone);
    $stmt->execute();

    header("Location: company.php");
}
?>

<form method="POST">
<textarea name="description" placeholder="Company Description"></textarea><br>
<input type="number" name="employees" placeholder="Employees Count"><br>
<input type="number" name="locations" placeholder="Locations Count"><br>

<h4>HR Details</h4>
<input type="text" name="hr_name" placeholder="HR Name"><br>
<input type="email" name="hr_email" placeholder="HR Email"><br>
<input type="text" name="hr_phone" placeholder="HR Phone"><br>

<button name="update">Update</button>
</form>