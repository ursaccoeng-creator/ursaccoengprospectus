<?php

session_start();
include "includes/db.php";

/* ================= FORCE HTTPS ================= */

if (
    empty($_SERVER['HTTPS']) ||
    $_SERVER['HTTPS'] === "off"
) {

    header(
        "Location: https://" .
        $_SERVER['HTTP_HOST'] .
        $_SERVER['REQUEST_URI']
    );

    exit();
}

/* ================= LOGIN CHECK ================= */

if (!isset($_SESSION['student_id'])) {

    header(
        "Location: https://" .
        $_SERVER['HTTP_HOST'] .
        "/index.php"
    );

    exit();
}

$student_id = $_SESSION['student_id'];

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
    WHERE s.student_id = '$student_id'
    LIMIT 1
    "
);

$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    die("Student not found.");
}

/* ================= CURRICULUM =================
Priority:
1. student curriculum_id
2. active curriculum of course
================================================ */

$curriculum_id = 0;

if (!empty($student['curriculum_id'])) {

    $curriculum_id =
        (int) $student['curriculum_id'];

} else {

    $curriculum_query = mysqli_query(
        $conn,
        "
        SELECT id
        FROM curricula
        WHERE course_id = '{$student['course_id']}'
        AND is_active = 1
        LIMIT 1
        "
    );

    if ($cur = mysqli_fetch_assoc($curriculum_query)) {
        $curriculum_id = (int) $cur['id'];
    }
}

if ($curriculum_id <= 0) {
    die("No curriculum assigned.");
}

/* ================= GET CURRENT SETTINGS ================= */

$settings_query = mysqli_query(
    $conn,
    "
    SELECT *
    FROM academic_settings
    WHERE id = 1
    LIMIT 1
    "
);

$settings = mysqli_fetch_assoc($settings_query);

/* ================= SUBJECTS ENROLLED ================= */
/* includes newly enrolled + completed + pending grades */

$subjects_query = mysqli_query(
    $conn,
    "
    SELECT
        sh.subject_code,
        s.subject_title,
        s.units,

        '{$student['section']}' AS section,

        CASE
            WHEN CAST(s.semester AS UNSIGNED) = 1
                THEN '1st Semester'
            WHEN CAST(s.semester AS UNSIGNED) = 2
                THEN '2nd Semester'
            ELSE 'Intersemester'
        END AS semester,

        '{$settings['school_year']}' AS school_year,

        CASE
            WHEN sh.grade IS NULL
                 OR sh.grade = ''
                THEN 'Enrolled'

            WHEN sh.is_confirmed = 1
                THEN 'Completed'

            ELSE 'Pending Grade'
        END AS status

    FROM student_subject_history sh

    INNER JOIN curriculum_subjects s
        ON TRIM(s.subject_code) =
           TRIM(sh.subject_code)

        AND s.curriculum_id =
            '$curriculum_id'

    WHERE sh.student_id =
        '{$student['id']}'

    ORDER BY

        CASE
            WHEN sh.grade IS NULL
                 OR sh.grade = ''
                THEN 1

            WHEN sh.is_confirmed = 1
                THEN 2

            ELSE 3
        END,

        CAST(s.year_level AS UNSIGNED),
        CAST(s.semester AS UNSIGNED),
        s.subject_code ASC
    "
);

if (!$subjects_query) {
    die(
        "Query Error: " .
        mysqli_error($conn)
    );
}

/* ================= TOTALS ================= */

$total_subjects = 0;
$total_units    = 0;

while (
    $row = mysqli_fetch_assoc(
        $subjects_query
    )
) {

    $total_subjects++;
    $total_units +=
        (int) $row['units'];
}

mysqli_data_seek(
    $subjects_query,
    0
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
>

<meta name="format-detection" content="telephone=no">

<title>Student Dashboard</title>

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

/* ================= CONTENT ================= */

.content{
    margin-left:265px;
    margin-right:20px;
    padding:30px;
    padding-top:40px;
    position:relative;
    z-index:1;
}

/* ================= WELCOME ================= */

.welcome{
    font-size:24px;
    font-weight:700;
    margin-bottom:22px;
    color:#2c5aa0;
    line-height:1.3;
}

/* ================= CARDS ================= */

.card-container{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:25px;
    position:relative;
    z-index:1;
}

.card{
    background:#fff;
    padding:18px;
    border-radius:16px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
    transition:background .2s ease;
    position:relative;
    z-index:1;
}

/* FIX HOVER GOING ABOVE SIDEBAR */
.card:hover{
    background:#f9fbff;
    transform:none;
    z-index:1;
}

.card-title{
    font-size:12px;
    color:#777;
    margin-bottom:6px;
}

.card-value{
    font-size:15px;
    font-weight:700;
    color:#2c5aa0;
    word-break:break-word;
}

/* ================= SECTION ================= */

.section{
    background:#fff;
    padding:22px;
    border-radius:18px;
    margin-bottom:20px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
    position:relative;
    z-index:1;
}

.section h3{
    margin:0;
    color:#2c5aa0;
    font-size:20px;
    font-weight:700;
}

/* ================= TABLE HEAD ================= */

.table-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    flex-wrap:wrap;
    margin-bottom:16px;
}

.table-head h3{
    flex:1;
    min-width:180px;
}

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
    background:#fff;
    outline:none;
}

.search-box input:focus{
    border-color:#2c5aa0;
    box-shadow:0 0 0 3px rgba(44,90,160,.08);
}

/* ================= TABLE ================= */

.table-wrapper{
    width:100%;
    overflow-x:auto;
    border-radius:14px;
    position:relative;
    z-index:1;
}

table{
    width:100%;
    min-width:560px;
    border-collapse:collapse;
    background:#fff;
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
    white-space:nowrap;
    border-bottom:1px solid #dbe6ff;
}

td{
    padding:12px;
    font-size:13px;
    text-align:center;
    white-space:nowrap;
    border-bottom:1px solid #f0f0f0;
}

tbody tr{
    transition:background .15s ease;
}

tbody tr:hover{
    background:#f9fbff;
}

/* ================= BADGES ================= */

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

/* ================= SUMMARY ================= */

.summary{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
}

.summary-card{
    background:#fff;
    padding:22px;
    border-radius:16px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
    text-align:center;
}

.summary-card b{
    display:block;
    margin-top:8px;
    font-size:22px;
    color:#2c5aa0;
}

/* ================= SIDEBAR ALWAYS ON TOP ================= */

.sidebar{
    z-index:9999 !important;
}

.overlay{
    z-index:9998 !important;
}

.mobile-header{
    z-index:9997 !important;
}

.burger{
    z-index:10000 !important;
}

/* ================= TABLET ================= */

@media (max-width:1024px){

    .content{
        margin-left:220px;
        margin-right:12px;
        padding:20px;
        padding-top:30px;
    }

    .card-container{
        grid-template-columns:repeat(2,1fr);
    }

    .welcome{
        font-size:22px;
    }

    .section h3{
        font-size:18px;
    }

    .search-box{
        width:260px;
    }

}

/* ================= MOBILE ================= */

@media (max-width:768px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:15px;
        padding-top:86px;
        z-index:1;
    }

    .welcome{
        font-size:20px;
        margin-bottom:16px;
    }

    .card-container,
    .summary{
        grid-template-columns:1fr;
        gap:12px;
    }

    .card,
    .summary-card,
    .section{
        padding:16px;
        border-radius:14px;
    }

    .section h3{
        font-size:18px;
    }

    .table-head{
        flex-direction:column;
        align-items:stretch;
        gap:10px;
    }

    .table-head h3{
        min-width:auto;
    }

    .search-box{
        width:100%;
    }

    table{
        min-width:520px;
    }

    th,
    td{
        font-size:12px;
        padding:10px;
    }

}

/* ================= SMALL MOBILE ================= */

@media (max-width:480px){

    .content{
        padding:12px;
        padding-top:82px;
    }

    .welcome{
        font-size:18px;
    }

    .card-value{
        font-size:14px;
    }

    .summary-card b{
        font-size:18px;
    }

    table{
        min-width:500px;
    }

}

</style>
</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="content">

    <div class="welcome">
        Welcome, <?php echo htmlspecialchars($student['full_name']); ?>
    </div>

    <!-- ================= INFO CARDS ================= -->

    <div class="card-container">

        <div class="card">
            <div class="card-title">Student ID</div>
            <div class="card-value">
                <?php echo htmlspecialchars($student['student_id']); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Course</div>
            <div class="card-value">
                <?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Year Level</div>
            <div class="card-value">
                <?php echo htmlspecialchars($student['year_level']); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Section</div>
            <div class="card-value">
                <?php echo htmlspecialchars($student['section']); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Entry School Year</div>
            <div class="card-value">
                <?php echo htmlspecialchars($student['entry_sy']); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Current Semester</div>
            <div class="card-value">
                <?php echo htmlspecialchars($settings['current_semester'] ?? '-'); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Academic Year</div>
            <div class="card-value">
                <?php echo htmlspecialchars($settings['school_year'] ?? '-'); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Current Status</div>
            <div class="card-value">
                <?php echo htmlspecialchars($student['current_status']); ?>
            </div>
        </div>

    </div>


    <!-- ================= SUBJECTS ENROLLED ================= -->

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
                        <td colspan="5" style="text-align:center;padding:15px;">
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

                        <tr data-priority="<?php echo $priority; ?>">

                            <td>
                                <?php echo htmlspecialchars($row['subject_code']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['subject_title']); ?>
                            </td>

                            <td>
                                <?php echo (int)$row['units']; ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['semester']); ?>
                            </td>

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


    <!-- ================= SUMMARY ================= -->

    <div class="section">

        <h3>Summary</h3>

        <div class="summary">

            <div class="summary-card">
                Total Subjects
                <b><?php echo $total_subjects; ?></b>
            </div>

            <div class="summary-card">
                Total Units
                <b><?php echo $total_units; ?></b>
            </div>

        </div>

    </div>

</div>


<script>

function filterTable() {

    let input  = document.getElementById("searchInput");
    let filter = input.value.toLowerCase();

    let tbody = document.querySelector("#subjectTable tbody");
    let rows  = tbody.querySelectorAll("tr.data-row");

    let found = false;

    rows.forEach(function(row) {

        let text = row.textContent.toLowerCase();

        if (text.indexOf(filter) > -1) {
            row.style.display = "";
            found = true;
        } else {
            row.style.display = "none";
        }

    });

    let noRow = document.getElementById("noResultRow");

    if (!found) {

        if (!noRow) {

            noRow = document.createElement("tr");
            noRow.id = "noResultRow";

            noRow.innerHTML = `
                <td colspan="5" style="
                    text-align:center;
                    padding:18px;
                    color:#888;
                    font-style:italic;
                ">
                    You haven't taken that subject yet.
                </td>
            `;

            tbody.appendChild(noRow);
        }

    } else {

        if (noRow) {
            noRow.remove();
        }

    }

}


/* keep enrolled first */

document.addEventListener("DOMContentLoaded", function () {

    let tbody = document.querySelector("#subjectTable tbody");
    let rows  = Array.from(tbody.querySelectorAll("tr"));

    rows.forEach(function(row) {
        row.classList.add("data-row");
    });

    rows.sort(function(a, b) {
        return a.dataset.priority - b.dataset.priority;
    });

    rows.forEach(function(row) {
        tbody.appendChild(row);
    });

});

</script>

</body>
</html>