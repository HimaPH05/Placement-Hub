<?php
include "db.php";
$company_id = $_SESSION['company_id'];

if(isset($_POST['add'])){
    $title = $_POST['title'];
    $desc = $_POST['desc'];
    $openings = $_POST['openings'];
    $salary = $_POST['salary'];
    $location = $_POST['location'];
    $deadline = $_POST['deadline'];

    $stmt = $conn->prepare("INSERT INTO jobs
        (company_id, job_title, job_description, openings, salary, location, deadline)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $company_id, $title, $desc, $openings, $salary, $location, $deadline);
    $stmt->execute();

    header("Location: company.php");
}
?>

<form method="POST">
<input type="text" name="title" placeholder="Job Title"><br>
<textarea name="desc" placeholder="Job Description"></textarea><br>
<input type="number" name="openings" placeholder="Openings"><br>
<input type="text" name="salary" placeholder="Salary"><br>
<input type="text" name="location" placeholder="Location"><br>
<input type="date" name="deadline"><br>
<button name="add">Add Job</button>
</form>