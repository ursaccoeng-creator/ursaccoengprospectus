<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

/* ================= PRINT SELECTED ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['print_selected'])) {

    $students = $_POST['students'] ?? [];

    if (!empty($students)) {

        $ids = [];

        foreach ($students as $id) {
            $ids[] = (int)$id;
        }

        $id_list = implode(",", $ids);

        header("Location: student_print_batch.php?ids=$id_list");
        exit();
    }
}

/* ================= FILTERS ================= */

$search     = $_GET['search'] ?? "";
$course_id  = $_GET['course_id'] ?? "";
$section    = $_GET['section'] ?? "";

/* ================= GET COURSES ================= */

$course_query = mysqli_query(
    $conn,
    "SELECT id, course_name FROM courses ORDER BY course_name ASC"
);

/* ================= GET SECTIONS ================= */

$section_query = mysqli_query(
    $conn,
    "SELECT DISTINCT section_name FROM sections ORDER BY section_name ASC"
);

/* ================= MAIN QUERY ================= */

$query = "
SELECT s.*, c.course_name
FROM students s
LEFT JOIN courses c ON c.id = s.course_id
WHERE 1
";

if ($search != "") {
    $query .= "
    AND (
        s.student_id LIKE '%$search%'
        OR s.full_name LIKE '%$search%'
    )";
}

if ($course_id != "") {
    $query .= " AND s.course_id = '$course_id'";
}

if ($section != "") {
    $query .= " AND s.section = '$section'";
}

$query .= " ORDER BY s.student_id ASC";

$result = mysqli_query($conn, $query);

?>

<!DOCTYPE html>
<html>
<head>
<title>Student Print Form</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>

body{
    margin:0;
    font-family:Arial, Helvetica, sans-serif;
    background:#f4f7fc;
}

.content{
    margin-left:265px;
    margin-right:25px;
    padding:30px;
    padding-top:40px;
}

.page-title{
    font-size:22px;
    font-weight:600;
    margin-bottom:20px;
    color:#2c5aa0;
}

.filters{
    display:flex;
    gap:10px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.input,.select{
    padding:11px;
    border-radius:10px;
    border:1px solid #dbe6ff;
    background:white;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
    outline:none;
}

.table-card{
    background:white;
    padding:20px;
    border-radius:18px;
    overflow-x:auto;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
}

.top-action{
    display:flex;
    justify-content:flex-end;
    margin-bottom:15px;
}

.btn-print{
    padding:10px 18px;
    border:none;
    border-radius:8px;
    background:#2c5aa0;
    color:#fff;
    font-size:13px;
    cursor:pointer;
    font-weight:600;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:950px;
}

thead{
    background:#eef3ff;
}

th{
    color:#2c5aa0;
    text-align:center;
    padding:13px;
    font-size:13px;
    border-bottom:1px solid #dbe6ff;
}

td{
    padding:12px;
    border-bottom:1px solid #f0f0f0;
    font-size:13px;
    text-align:center;
}

.btn{
    padding:6px 14px;
    border-radius:8px;
    text-decoration:none;
    background:#2c5aa0;
    color:white;
    font-size:12px;
}

@media(max-width:768px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:15px;
        padding-top:90px;
    }

    .filters{
        flex-direction:column;
    }

    .btn-print{
        width:100%;
    }

    .top-action{
        justify-content:stretch;
    }

}

</style>
</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

<div class="page-title">
    Student Print Form
</div>

<form method="GET" class="filters">

<input
type="text"
name="search"
placeholder="Search Student ID / Name"
class="input"
value="<?php echo $search; ?>"
>

<select name="course_id" class="select" onchange="this.form.submit()">
<option value="">All Course</option>

<?php while($c=mysqli_fetch_assoc($course_query)){ ?>

<option
value="<?php echo $c['id']; ?>"
<?php if($course_id==$c['id']) echo "selected"; ?>
>
<?php echo $c['course_name']; ?>
</option>

<?php } ?>

</select>

<select name="section" class="select" onchange="this.form.submit()">

<option value="">All Section</option>

<?php while($s=mysqli_fetch_assoc($section_query)){ ?>

<option
value="<?php echo $s['section_name']; ?>"
<?php if($section==$s['section_name']) echo "selected"; ?>
>
<?php echo $s['section_name']; ?>
</option>

<?php } ?>

</select>

</form>

<form method="POST">

<div class="top-action">
<button type="submit" name="print_selected" class="btn-print">
Print Selected
</button>
</div>

<div class="table-card">

<table>

<thead>
<tr>
<th><input type="checkbox" onclick="toggleAll(this)"></th>
<th>Student ID</th>
<th>Name</th>
<th>Course</th>
<th>Year</th>
<th>Section</th>
<th>Status</th>
<th>PDF</th>
</tr>
</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td>
<input
type="checkbox"
name="students[]"
value="<?php echo $row['id']; ?>"
>
</td>

<td><?php echo $row['student_id']; ?></td>
<td><?php echo $row['full_name']; ?></td>
<td><?php echo $row['course_name']; ?></td>
<td><?php echo $row['year_level']; ?></td>
<td><?php echo $row['section']; ?></td>
<td><?php echo $row['current_status']; ?></td>

<td>
<a
href="admin_print_form.php?id=<?php echo $row['id']; ?>"
target="_blank"
class="btn"
>
View PDF
</a>
</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</form>

</div>

<script>

function toggleAll(source){

let checkboxes =
document.querySelectorAll('input[name="students[]"]');

checkboxes.forEach(function(box){
box.checked = source.checked;
});

}

</script>

</body>
</html>