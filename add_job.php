<?php
include "db.php";
$company_id = $_SESSION['company_id'];

if(isset($_POST['add'])){
    $title = $_POST['title'];
    $desc = $_POST['desc'];
    $openings = $_POST['openings'];
    $min_cgpa = $_POST['min_cgpa'];
    $salary = $_POST['salary'];
    $location = $_POST['location'];
    $deadline = $_POST['deadline'];

    $stmt = $conn->prepare("INSERT INTO jobs
        (company_id, job_title, job_description, openings, min_cgpa, salary, location, deadline)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issidsss", $company_id, $title, $desc, $openings, $min_cgpa, $salary, $location, $deadline);
    $stmt->execute();

    header("Location: company.php");
}
?>

<form method="POST">
<input type="text" name="title" placeholder="Job Title"><br>
<textarea name="desc" placeholder="Job Description"></textarea><br>
<input type="number" name="openings" placeholder="Openings"><br>
<input type="number" step="0.01" min="0" max="10" name="min_cgpa" placeholder="Minimum CGPA"><br>
<input type="text" name="salary" placeholder="Salary"><br>
<input type="text" name="location" placeholder="Location"><br>
<input type="date" name="deadline"><br>
<button name="add">Add Job</button>
</form>
