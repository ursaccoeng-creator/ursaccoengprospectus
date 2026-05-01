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

<title>Subjects Enrolled</title>

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

.section {
    background:white;
    padding:20px;
    border-radius:8px;
    margin-bottom:20px;
}

.section-title {
    font-size:18px;
    margin-bottom:15px;
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

.badge {
    background:#1e3a5f;
    color:white;
    padding:4px 10px;
    border-radius:4px;
    font-size:12px;
}


/* MOBILE */

@media (max-width:768px){

.content {
    margin-left:0;
    padding:15px;
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
Subjects Enrolled
</div>

<?php

$query = mysqli_query(
$conn,
"
SELECT DISTINCT semester, school_year
FROM validation
WHERE student_id='$student_id'
ORDER BY school_year DESC
"
);

while($sem = mysqli_fetch_assoc($query)){

?>

<div class="section">

<div class="section-title">
<span class="badge">
<?php echo $sem['semester']; ?>
</span>

&nbsp;

<span class="badge">
<?php echo $sem['school_year']; ?>
</span>
</div>

<div class="table-wrapper">

<table>

<tr>
<th>Subject Code</th>
<th>Subject Title</th>
</tr>

<?php

$subjects = mysqli_query(
$conn,
"
SELECT validation.*, subjects.subject_title
FROM validation
JOIN subjects
ON validation.subject_code = subjects.subject_code
WHERE validation.student_id='$student_id'
AND validation.semester='".$sem['semester']."'
AND validation.school_year='".$sem['school_year']."'
"
);

while($row = mysqli_fetch_assoc($subjects)){
?>

<tr>

<td>
<?php echo $row['subject_code']; ?>
</td>

<td>
<?php echo $row['subject_title']; ?>
</td>

</tr>

<?php } ?>

</table>

</div>

</div>

<?php } ?>

</div>

</body>
</html>