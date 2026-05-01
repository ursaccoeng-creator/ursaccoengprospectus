<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Invalid student.");
}


/* ================= GET STUDENT ================= */

$student_query = mysqli_query(
    $conn,
    "
    SELECT
        s.*,
        c.course_name
    FROM students s
    LEFT JOIN courses c
        ON c.id = s.course_id
    WHERE s.id = '$id'
    LIMIT 1
    "
);

$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    die("Student not found.");
}


/* ================= SETTINGS ================= */

$settings_query = mysqli_query(
    $conn,
    "SELECT * FROM academic_settings WHERE id = 1 LIMIT 1"
);

$settings = mysqli_fetch_assoc($settings_query);


/* ================= SUBJECTS ENROLLED ================= */

$subjects_query = mysqli_query(
    $conn,
    "
    SELECT
        sh.subject_code,
        s.subject_title,
        s.units,

        '{$student['section']}' AS section,

        CASE
            WHEN CAST(s.semester AS UNSIGNED) = 1 THEN '1st Semester'
            WHEN CAST(s.semester AS UNSIGNED) = 2 THEN '2nd Semester'
            ELSE 'Intersemester'
        END AS semester,

        '{$settings['school_year']}' AS school_year,

        CASE
            WHEN sh.grade IS NULL OR sh.grade = ''
                THEN 'Enrolled'

            WHEN sh.is_confirmed = 1
                THEN 'Completed'

            ELSE 'Pending Grade'
        END AS status

    FROM student_subject_history sh

    INNER JOIN subjects s
        ON s.subject_code = sh.subject_code
        AND s.course_id = '{$student['course_id']}'

    WHERE sh.student_id = '{$student['id']}'

    ORDER BY
        CASE
            WHEN sh.grade IS NULL OR sh.grade = '' THEN 1
            WHEN sh.is_confirmed = 1 THEN 2
            ELSE 3
        END,
        CAST(s.year_level AS UNSIGNED) ASC,
        CAST(s.semester AS UNSIGNED) ASC,
        s.subject_code ASC
    "
);

if (!$subjects_query) {
    die("Query Error: " . mysqli_error($conn));
}


/* ================= TOTALS ================= */

$total_subjects = 0;
$total_units = 0;

while ($row = mysqli_fetch_assoc($subjects_query)) {

    $total_subjects++;
    $total_units += (int)$row['units'];
}

mysqli_data_seek($subjects_query, 0);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
>

<meta
    name="format-detection"
    content="telephone=no"
>

<title>Student Enrollment</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html,
body{
    width:100%;
    min-height:100%;
    overflow-x:hidden;
    -webkit-text-size-adjust:100%;
}

body{
    font-family:Arial, Helvetica, sans-serif;
    background:#f4f7fc;
    color:#333;
}

.content{
    margin-left:265px;
    margin-right:25px;
    padding:30px;
    padding-top:40px;
}

/* HEADER */

.top-bar{
    display:flex;
    align-items:center;
    gap:15px;
    margin-bottom:22px;
    flex-wrap:wrap;
}

.page-title{
    font-size:24px;
    font-weight:700;
    color:#2c5aa0;
    margin:0;
    line-height:1.3;
}

.btn-back{
    background:#eef3ff;
    color:#2c5aa0;
    padding:10px 16px;
    border-radius:10px;
    text-decoration:none;
    font-size:13px;
    border:1px solid #dbe6ff;
    transition:.2s;
}

.btn-back:hover{
    background:#dfeaff;
}

/* STUDENT INFO */

.student-box{
    background:#fff;
    padding:18px;
    border-radius:16px;
    margin-bottom:18px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
    line-height:1.8;
    font-size:13px;
}

/* TABLE SECTION */

.section{
    background:#fff;
    padding:22px;
    border-radius:18px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
}

.table-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    flex-wrap:wrap;
    margin-bottom:16px;
}

.table-head h3{
    margin:0;
    color:#2c5aa0;
    font-size:20px;
    line-height:1.3;
}

/* SEARCH */

.search-box{
    width:320px;
    max-width:100%;
}

.search-box input{
    width:100%;
    height:42px;
    padding:0 14px;
    border:1px solid #dbe6ff;
    border-radius:10px;
    font-size:13px;
    outline:none;
    background:#fff;
}

.search-box input:focus{
    border-color:#2c5aa0;
    box-shadow:0 0 0 3px rgba(44,90,160,.08);
}

/* TABLE */

.table-wrapper{
    width:100%;
    overflow-x:auto;
    border-radius:14px;
    -webkit-overflow-scrolling:touch;
}

table{
    width:100%;
    min-width:560px;
    border-collapse:collapse;
}

thead{
    background:#eef3ff;
}

th{
    color:#2c5aa0;
    padding:13px;
    font-size:13px;
    text-align:center;
    font-weight:700;
    border-bottom:1px solid #dbe6ff;
    white-space:nowrap;
}

td{
    padding:12px;
    font-size:13px;
    text-align:center;
    border-bottom:1px solid #f0f0f0;
    white-space:nowrap;
}

tr:last-child td{
    border-bottom:none;
}

tbody tr:hover{
    background:#f9fbff;
}

/* BADGES */

.badge{
    display:inline-block;
    padding:6px 11px;
    border-radius:30px;
    font-size:12px;
    font-weight:700;
}

.badge-enrolled{
    background:#eaf6ff;
    color:#2c5aa0;
}

.badge-completed{
    background:#e6f7ee;
    color:#27ae60;
}

.badge-pending{
    background:#fff8df;
    color:#d68910;
}

/* SUMMARY */

.summary{
    margin-top:18px;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px;
}

.summary-box{
    background:#fff;
    padding:18px;
    border-radius:14px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
    text-align:center;
    font-size:14px;
}

.summary-box b{
    display:block;
    margin-top:8px;
    color:#2c5aa0;
    font-size:22px;
}

/* TABLET */

@media (max-width:1024px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:22px;
        padding-top:88px;
    }

}

/* MOBILE */

@media (max-width:768px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:15px;
        padding-top:85px;
    }

    .top-bar{
        flex-direction:row;
        align-items:center;
        gap:10px;
    }

    .page-title{
        font-size:22px;
    }

    .student-box{
        padding:16px;
    }

    .section{
        padding:16px;
        border-radius:15px;
    }

    .table-head{
        flex-direction:column;
        align-items:stretch;
    }

    .table-head h3{
        font-size:18px;
    }

    .search-box{
        width:100%;
    }

    table{
        min-width:540px;
    }

    .summary{
        grid-template-columns:1fr;
    }

}

/* SMALL MOBILE */

@media (max-width:480px){

    .content{
        padding:12px;
        padding-top:80px;
    }

    .page-title{
        font-size:19px;
    }

    .student-box,
    .section,
    .summary-box{
        padding:14px;
    }

    th,
    td{
        font-size:12px;
        padding:9px;
    }

}

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <!-- TOP BAR -->
    <div class="top-bar">

        <a
            href="student_view.php?id=<?php echo (int)$id; ?>"
            class="btn-back"
        >
            ← Back
        </a>

        <div class="page-title">
            Student Enrollment
        </div>

    </div>


    <!-- STUDENT INFO -->
    <div class="student-box">

        <b>Name:</b>
        <?php echo htmlspecialchars($student['full_name']); ?><br>

        <b>Student ID:</b>
        <?php echo htmlspecialchars($student['student_id']); ?><br>

        <b>Course:</b>
        <?php echo htmlspecialchars($student['course_name']); ?><br>

        <b>Year Level:</b>
        <?php echo htmlspecialchars($student['year_level']); ?><br>

        <b>Section:</b>
        <?php echo htmlspecialchars($student['section']); ?>

    </div>


    <!-- TABLE -->
    <div class="section">

        <div class="table-head">

            <h3>Subjects Enrolled</h3>

            <div class="search-box">
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search subject..."
                    onkeyup="filterTable()"
                >
            </div>

        </div>

        <div class="table-wrapper">

            <table id="subjectTable">

                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Title</th>
                        <th>Units</th>
                        <th>Semester</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (mysqli_num_rows($subjects_query) == 0) { ?>

                    <tr>
                        <td colspan="5" style="padding:15px;">
                            No subjects found
                        </td>
                    </tr>

                <?php } else { ?>

                    <?php while ($row = mysqli_fetch_assoc($subjects_query)) { ?>

                        <?php
                        $status = strtolower($row['status']);

                        if ($status == 'enrolled') {
                            $badge = 'badge-enrolled';
                            $priority = 1;
                        } elseif ($status == 'completed') {
                            $badge = 'badge-completed';
                            $priority = 2;
                        } else {
                            $badge = 'badge-pending';
                            $priority = 3;
                        }
                        ?>

                        <tr class="data-row" data-priority="<?php echo $priority; ?>">

                            <td><?php echo htmlspecialchars($row['subject_code']); ?></td>

                            <td><?php echo htmlspecialchars($row['subject_title']); ?></td>

                            <td><?php echo (int)$row['units']; ?></td>

                            <td><?php echo htmlspecialchars($row['semester']); ?></td>

                            <td>
                                <span class="badge <?php echo $badge; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>

                        </tr>

                    <?php } ?>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- SUMMARY BELOW TABLE -->
    <div class="summary">

        <div class="summary-box">
            Total Subjects
            <b><?php echo $total_subjects; ?></b>
        </div>

        <div class="summary-box">
            Total Units
            <b><?php echo $total_units; ?></b>
        </div>

    </div>

</div>


<script>

function filterTable(){

    let input = document.getElementById("searchInput");
    let filter = input.value.toLowerCase();

    let tbody = document.querySelector("#subjectTable tbody");
    let rows = tbody.querySelectorAll("tr.data-row");

    let found = false;

    rows.forEach(function(row){

        let text = row.innerText.toLowerCase();

        if(text.indexOf(filter) > -1){
            row.style.display = "";
            found = true;
        }else{
            row.style.display = "none";
        }

    });

    let noRow = document.getElementById("noResultRow");

    if(!found){

        if(!noRow){

            noRow = document.createElement("tr");
            noRow.id = "noResultRow";

            noRow.innerHTML =
            '<td colspan="5" style="padding:16px;color:#888;">You haven\\'t taken that subject yet.</td>';

            tbody.appendChild(noRow);

        }else{
            noRow.style.display = "";
        }

    }else{

        if(noRow){
            noRow.style.display = "none";
        }
    }
}


/* SORT STATUS: ENROLLED FIRST */

document.addEventListener("DOMContentLoaded", function(){

    let tbody = document.querySelector("#subjectTable tbody");

    let rows = Array.from(
        tbody.querySelectorAll("tr.data-row")
    );

    rows.sort(function(a,b){

        let p1 = parseInt(a.dataset.priority);
        let p2 = parseInt(b.dataset.priority);

        return p1 - p2;
    });

    rows.forEach(function(row){
        tbody.appendChild(row);
    });

});
</script>

</body>
</html>