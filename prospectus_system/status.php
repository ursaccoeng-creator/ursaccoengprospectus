<?php

session_start();
include "includes/db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
}

$student_id = $_SESSION['student_id'];

?>

<!DOCTYPE html>
<html>

<head>

<title>Enrollment Status</title>

<style>

body {
    margin:0;
    font-family: Arial, Helvetica, sans-serif;
    background:#f5f7fb;
}

.content {
    margin-left:230px;
    padding:30px;
}

.page-title {
    font-size:22px;
    margin-bottom:20px;
}

.card-container {
    display:grid;
    grid-template-columns: repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.card {
    background:white;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

.card-title {
    font-size:13px;
    color:#666;
    margin-bottom:5px;
}

.card-value {
    font-size:18px;
    font-weight:bold;
}

.section {
    background:white;
    padding:20px;
    border-radius:8px;
}

.table-wrapper {
    overflow-x:auto;
}

table {
    width:100%;
    border-collapse:collapse;
    min-width:700px;
}

table th {
    background:#1e3a5f;
    color:white;
    padding:10px;
    font-size:13px;
    text-align:left;
}

table td {
    padding:10px;
    border-bottom:1px solid #eee;
}

.pass {
    color:green;
    font-weight:bold;
}

.fail {
    color:red;
    font-weight:bold;
}

.enrolled {
    color:#1e3a5f;
    font-weight:bold;
}


/* MOBILE */

@media (max-width:768px){

.content {
    margin-left:0;
    padding:15px;
}

.card-container {
    grid-template-columns:1fr;
}

.section {
    padding:15px;
}

}

</style>

</head>

<body>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<div class="content">

<div class="page-title">
Enrollment Status
</div>

<?php

/* CURRENT STATUS */

$current = mysqli_query(
$conn,
"
SELECT *
FROM validation
WHERE student_id='$student_id'
ORDER BY id DESC
LIMIT 1
"
);

$data = mysqli_fetch_assoc($current);

$semester = $data['semester'] ?? "N/A";
$school_year = $data['school_year'] ?? "N/A";


/* TOTAL SUBJECTS */

$total = mysqli_query(
$conn,
"
SELECT COUNT(*) as total
FROM validation
WHERE student_id='$student_id'
"
);

$total = mysqli_fetch_assoc($total)['total'];


/* PASSED */

$passed = mysqli_query(
$conn,
"
SELECT COUNT(*) as passed
FROM grades
WHERE student_id='$student_id'
AND grade <= 3.00
"
);

$passed = mysqli_fetch_assoc($passed)['passed'];


/* FAILED */

$failed = mysqli_query(
$conn,
"
SELECT COUNT(*) as failed
FROM grades
WHERE student_id='$student_id'
AND grade > 3.00
"
);

$failed = mysqli_fetch_assoc($failed)['failed'];

?>


<div class="card-container">

<div class="card">
<div class="card-title">Semester</div>
<div class="card-value">
<?php echo $semester; ?>
</div>
</div>

<div class="card">
<div class="card-title">School Year</div>
<div class="card-value">
<?php echo $school_year; ?>
</div>
</div>

<div class="card">
<div class="card-title">Total Subjects</div>
<div class="card-value">
<?php echo $total; ?>
</div>
</div>

<div class="card">
<div class="card-title">Passed</div>
<div class="card-value">
<?php echo $passed; ?>
</div>
</div>

</div>



<div class="section">

<div class="table-wrapper">

<table>

<tr>
<th>Subject Code</th>
<th>Subject Title</th>
<th>Grade</th>
<th>Status</th>
</tr>

<?php

$query = mysqli_query(
$conn,
"
SELECT validation.*, subjects.subject_title
FROM validation
JOIN subjects
ON validation.subject_code = subjects.subject_code
WHERE validation.student_id='$student_id'
"
);

while($row = mysqli_fetch_assoc($query)){

$grade_query = mysqli_query(
$conn,
"
SELECT *
FROM grades
WHERE student_id='$student_id'
AND subject_code='".$row['subject_code']."'
"
);

$grade = mysqli_fetch_assoc($grade_query);

$grade_value = $grade['grade'] ?? '-';

$status = "<span class='enrolled'>ENROLLED</span>";

if($grade){

if($grade['grade'] <= 3.00){
$status = "<span class='pass'>PASSED</span>";
}else{
$status = "<span class='fail'>FAILED</span>";
}

}

?>

<tr>

<td>
<?php echo $row['subject_code']; ?>
</td>

<td>
<?php echo $row['subject_title']; ?>
</td>

<td>
<?php echo $grade_value; ?>
</td>

<td>
<?php echo $status; ?>
</td>

</tr>

<?php } ?>

</table>

</div>

</div>

</div>

</body>
</html>