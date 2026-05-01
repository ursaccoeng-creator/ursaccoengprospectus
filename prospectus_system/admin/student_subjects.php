<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {
    die("Invalid student.");
}

/* ================= STUDENT ================= */

$student_query = mysqli_query(
    $conn,
    "
    SELECT *
    FROM students
    WHERE id = '$id'
    LIMIT 1
    "
);

$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    die("Student not found.");
}

/* ================= SETTINGS ================= */

$current = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM academic_settings
        WHERE id = 1
        LIMIT 1
        "
    )
);

/* ================= COURSE ================= */

$course_id = !empty($student['course_id'])
    ? (int) $student['course_id']
    : 0;

/* ================= DEFAULT YEAR ================= */

$default_year_query = mysqli_query(
    $conn,
    "
    SELECT MIN(CAST(year_name AS UNSIGNED)) AS year
    FROM year_levels
    WHERE course_id = '$course_id'
    "
);

$default_year = mysqli_fetch_assoc($default_year_query);

$student_year =
    isset($default_year['year']) &&
    $default_year['year'] !== null
        ? (int) $default_year['year']
        : 1;

/* ================= FILTERS ================= */

$semester = $_GET['semester'] ?? "ALL";

$year = isset($_GET['year'])
    ? (int) $_GET['year']
    : $student_year;

/* ================= COURSE NAME ================= */

$course_name = "No Course";

if ($course_id > 0) {

    $course_query = mysqli_query(
        $conn,
        "
        SELECT course_name
        FROM courses
        WHERE id = '$course_id'
        LIMIT 1
        "
    );

    $course = mysqli_fetch_assoc($course_query);

    if ($course) {
        $course_name = $course['course_name'];
    }
}

/* ================= FUNCTION ================= */

function renderTable(
    $conn,
    $student,
    $year,
    $semesterLabel,
    $semesterValue = null
) {

    $whereSemester = "";

    if ($semesterValue !== null) {

        $semesterValue = (int) $semesterValue;

        $whereSemester =
            "
            AND CAST(s.semester AS UNSIGNED)
            = '$semesterValue'
            ";
    }

    $query = mysqli_query(
        $conn,
        "
        SELECT
            s.*,
            h.grade,
            h.is_confirmed,

            GROUP_CONCAT(
                DISTINCT ps.subject_code
                ORDER BY ps.subject_code
                SEPARATOR ', '
            ) AS prereq_codes

        FROM subjects s

        LEFT JOIN student_subject_history h
            ON h.subject_code = s.subject_code
            AND h.student_id = '{$student['id']}'

        LEFT JOIN subject_prerequisites sp
            ON sp.subject_id = s.id

        LEFT JOIN subjects ps
            ON ps.id = sp.prereq_id

        WHERE s.course_id = '{$student['course_id']}'
        AND CAST(s.year_level AS UNSIGNED) = '$year'
        $whereSemester

        GROUP BY
            s.id,
            s.subject_code,
            s.subject_title,
            s.units,
            h.grade,
            h.is_confirmed

        ORDER BY
            CAST(s.year_level AS UNSIGNED),
            CAST(s.semester AS UNSIGNED),
            s.subject_code ASC
        "
    );

?>

<div class="enrollment-card">

    <div class="school-header">

        <div class="school-title">
            STUDENT PROSPECTUS
        </div>

        <div class="form-sub">
            <?php echo htmlspecialchars($semesterLabel); ?>
            |
            Year Level <?php echo (int) $year; ?>
        </div>

    </div>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Title</th>
                    <th>Units</th>
                    <th>Grade</th>
                    <th>Remarks</th>
                    <th>Pre-Requisite</th>
                </tr>
            </thead>

            <tbody>

<?php

$totalUnits = 0;

if (!$query || mysqli_num_rows($query) == 0) {

?>

<tr>
    <td colspan="6">
        No subjects found
    </td>
</tr>

<?php

} else {

while ($row = mysqli_fetch_assoc($query)) {

    $units = (int) $row['units'];
    $totalUnits += $units;

    $grade = $row['grade'] ?? '';

    if ($grade !== '' && is_numeric($grade)) {
        $grade = number_format(
            (float) $grade,
            2
        );
    }

    if ($grade === '' || $grade === null) {

        $remarks = "N/A";

    } elseif ($grade === "INC") {

        $remarks = "INC";

    } elseif ($grade === "DROP") {

        $remarks = "DROPPED";

    } elseif (is_numeric($grade)) {

        $remarks =
            (float) $grade <= 3.00
                ? "PASSED"
                : "FAILED";

    } else {

        $remarks = "N/A";
    }

    $prereq =
        !empty($row['prereq_codes'])
            ? $row['prereq_codes']
            : "None";

?>

<tr>

    <td>
        <?php echo htmlspecialchars($row['subject_code']); ?>
    </td>

    <td>
        <?php echo htmlspecialchars($row['subject_title']); ?>
    </td>

    <td>
        <?php echo $units; ?>
    </td>

    <td>
        <?php echo htmlspecialchars($grade); ?>
    </td>

    <td>
        <?php echo htmlspecialchars($remarks); ?>
    </td>

    <td>
        <?php echo htmlspecialchars($prereq); ?>
    </td>

</tr>

<?php

}

}

?>

            </tbody>

        </table>

    </div>

    <div class="total-units">
        Total Units:
        <?php echo $totalUnits; ?>
    </div>

</div>

<br>

<?php

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
>

<title>Student Grades</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, Helvetica, sans-serif;
    background:#f4f7fc;
}

.content{
    margin-left:265px;
    margin-right:25px;
    padding:30px;
    padding-top:40px;
}

.top-bar{
    display:flex;
    align-items:center;
    gap:15px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.page-title{
    font-size:22px;
    font-weight:700;
    color:#2c5aa0;
}

.btn-back{
    background:#eef3ff;
    color:#2c5aa0;
    padding:8px 14px;
    border-radius:10px;
    text-decoration:none;
    border:1px solid #dbe6ff;
    font-size:13px;
}

.student-info{
    background:#fff;
    padding:18px;
    border-radius:18px;
    margin-bottom:15px;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
    line-height:1.8;
    font-size:13px;
}

.enrollment-card{
    background:#fff;
    padding:22px;
    border-radius:18px;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
}

.school-header{
    text-align:center;
    margin-bottom:15px;
}

.school-title{
    font-size:18px;
    font-weight:700;
}

.form-sub{
    margin-top:5px;
    color:#666;
    font-size:13px;
}

.table-wrap{
    overflow-x:auto;
}

table{
    width:100%;
    min-width:700px;
    border-collapse:collapse;
}

thead{
    background:#eef3ff;
}

th,
td{
    padding:10px;
    border-bottom:1px solid #eee;
    font-size:13px;
    text-align:center;
}

th{
    color:#2c5aa0;
}

.total-units{
    padding-top:12px;
    text-align:right;
    font-weight:700;
    color:#2c5aa0;
}

.bottom-actions{
    margin-top:20px;
    display:flex;
    justify-content:center;
    gap:15px;
    flex-wrap:wrap;
}

.btn{
    width:220px;
    height:44px;
    border:none;
    border-radius:12px;
    color:#fff;
    text-decoration:none;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
}

.btn-print{
    background:#2c5aa0;
}

.btn-profile{
    background:#95a5a6;
}

@media (max-width:768px){

.content{
    margin-left:0;
    margin-right:0;
    padding:15px;
    padding-top:85px;
}

.top-bar{
    flex-direction:column;
    align-items:flex-start;
}

table{
    min-width:650px;
}

.btn{
    width:100%;
}

}

@media print{

.top-bar,
.bottom-actions,
.sidebar,
.mobile-header,
.burger{
    display:none !important;
}

.content{
    margin:0;
    padding:0;
}

body{
    background:#fff;
}

}

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

<div class="top-bar">

    <a
        href="student_view.php?id=<?php echo $id; ?>"
        class="btn-back"
    >
        ← Back
    </a>

    <div class="page-title">
        Student Prospectus
    </div>

</div>

<form
    method="GET"
    class="top-bar"
>

<input
    type="hidden"
    name="id"
    value="<?php echo $id; ?>"
>

<select
    name="semester"
    onchange="this.form.submit()"
>

<option value="ALL">All Semester</option>

<?php

$sem = mysqli_query(
    $conn,
    "SELECT * FROM semesters ORDER BY id ASC"
);

while ($s = mysqli_fetch_assoc($sem)) {

?>

<option
    value="<?php echo $s['id']; ?>"
    <?php if ($semester == $s['id']) echo "selected"; ?>
>
    <?php echo $s['semester_name']; ?>
</option>

<?php } ?>

</select>

<select
    name="year"
    onchange="this.form.submit()"
>

<?php

$yr = mysqli_query(
    $conn,
    "
    SELECT DISTINCT CAST(year_name AS UNSIGNED) AS year
    FROM year_levels
    WHERE course_id = '$course_id'
    ORDER BY year ASC
    "
);

while ($y = mysqli_fetch_assoc($yr)) {

?>

<option
    value="<?php echo $y['year']; ?>"
    <?php if ($year == $y['year']) echo "selected"; ?>
>
    Year <?php echo $y['year']; ?>
</option>

<?php } ?>

</select>

</form>

<div class="student-info">

<b>Name:</b>
<?php echo htmlspecialchars($student['full_name']); ?><br>

<b>Student ID:</b>
<?php echo htmlspecialchars($student['student_id']); ?><br>

<b>Course:</b>
<?php echo htmlspecialchars($course_name); ?><br>

<b>Year & Section:</b>
<?php echo htmlspecialchars($student['year_level']); ?>
-
<?php echo htmlspecialchars($student['section']); ?>

</div>

<?php

if ($semester == "ALL") {

    $sem = mysqli_query(
        $conn,
        "
        SELECT DISTINCT s.semester, sem.semester_name
        FROM subjects s
        LEFT JOIN semesters sem
        ON sem.id = s.semester
        WHERE s.course_id = '$course_id'
        AND CAST(s.year_level AS UNSIGNED) = '$year'
        ORDER BY CAST(s.semester AS UNSIGNED)
        "
    );

    while ($s = mysqli_fetch_assoc($sem)) {

        renderTable(
            $conn,
            $student,
            $year,
            $s['semester_name'],
            $s['semester']
        );
    }

} else {

    $label = mysqli_fetch_assoc(
        mysqli_query(
            $conn,
            "
            SELECT semester_name
            FROM semesters
            WHERE id = '$semester'
            LIMIT 1
            "
        )
    );

    renderTable(
        $conn,
        $student,
        $year,
        $label['semester_name'],
        $semester
    );
}

?>

<div class="bottom-actions">

<button
    onclick="window.print()"
    class="btn btn-print"
>
    Print Prospectus
</button>

<a
    href="student_view.php?id=<?php echo $id; ?>"
    class="btn btn-profile"
>
    Back to Profile
</a>

</div>

</div>

</body>
</html>