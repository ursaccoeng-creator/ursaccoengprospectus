<?php

session_start();
include "includes/db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

$student_id = mysqli_real_escape_string($conn, $_SESSION['student_id']);

/* ================= GET STUDENT ================= */

$student_query = mysqli_query($conn, "
    SELECT s.*, c.course_name
    FROM students s
    LEFT JOIN courses c
        ON c.id = s.course_id
    WHERE s.student_id = '$student_id'
    LIMIT 1
");

$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    die("Student not found.");
}

/* ================= CURRICULUM =================
Priority:
1. student curriculum_id
2. active curriculum by course
================================================ */

$curriculum_id = 0;

if (!empty($student['curriculum_id'])) {

    $curriculum_id = (int)$student['curriculum_id'];

} else {

    $cur = mysqli_query($conn, "
        SELECT id
        FROM curricula
        WHERE course_id = '{$student['course_id']}'
        AND is_active = 1
        LIMIT 1
    ");

    if ($cr = mysqli_fetch_assoc($cur)) {
        $curriculum_id = (int)$cr['id'];
    }
}

if ($curriculum_id <= 0) {
    die("No curriculum assigned to this student.");
}

/* ================= FILTERS ================= */

$selected_year = isset($_GET['year'])
    ? (int) filter_var($_GET['year'], FILTER_SANITIZE_NUMBER_INT)
    : 1;

$selected_sem = isset($_GET['sem'])
    ? (int) $_GET['sem']
    : 1;

if (!in_array($selected_sem, [1,2,3])) {
    $selected_sem = 1;
}

/* ================= MESSAGE ================= */

$success_message = '';
$error_message   = '';

/* ================= CURRENT SEM CONFIRMED ================= */

$is_confirmed_all = false;

$confirm_check = mysqli_query($conn, "
    SELECT
        COUNT(s.subject_code) AS total_subjects,
        SUM(
            CASE
                WHEN h.grade IS NOT NULL
                AND h.grade <> ''
                AND h.is_confirmed = 1
                THEN 1 ELSE 0
            END
        ) AS confirmed_subjects
    FROM curriculum_subjects s
    LEFT JOIN student_subject_history h
        ON h.subject_code = s.subject_code
        AND h.student_id = '{$student['id']}'
    WHERE s.curriculum_id = '$curriculum_id'
    AND CAST(s.year_level AS UNSIGNED) = '$selected_year'
    AND CAST(s.semester AS UNSIGNED) = '$selected_sem'
");

$cc = mysqli_fetch_assoc($confirm_check);

$totalSubjects     = (int)($cc['total_subjects'] ?? 0);
$confirmedSubjects = (int)($cc['confirmed_subjects'] ?? 0);

if ($totalSubjects > 0 && $totalSubjects == $confirmedSubjects) {
    $is_confirmed_all = true;
}

/* ================= ENROLL SUBJECTS ================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['enroll_subjects']) &&
    $is_confirmed_all
) {

    $subjects = $_POST['subjects'] ?? [];

    if (!empty($subjects) && is_array($subjects)) {

        $saved = 0;

        foreach ($subjects as $code) {

            $code = mysqli_real_escape_string($conn, $code);

            $check = mysqli_query($conn, "
                SELECT id
                FROM student_subject_history
                WHERE student_id = '{$student['id']}'
                AND subject_code = '$code'
                LIMIT 1
            ");

            if (mysqli_num_rows($check) == 0) {

                mysqli_query($conn, "
                    INSERT INTO student_subject_history
                    (
                        student_id,
                        subject_code,
                        grade,
                        is_confirmed
                    )
                    VALUES
                    (
                        '{$student['id']}',
                        '$code',
                        NULL,
                        0
                    )
                ");

                $saved++;
            }
        }

        if ($saved > 0) {
            $success_message = "Subjects enrolled successfully.";
        } else {
            $error_message = "Selected subjects are already enrolled.";
        }

    } else {
        $error_message = "Please select subject(s).";
    }
}

/* ================= IRREGULAR CHECK ================= */

$is_irregular = false;

$irregular_q = mysqli_query($conn, "
    SELECT s.subject_code, h.grade
    FROM curriculum_subjects s
    LEFT JOIN student_subject_history h
        ON h.subject_code = s.subject_code
        AND h.student_id = '{$student['id']}'
    WHERE s.curriculum_id = '$curriculum_id'
    AND (
        CAST(s.year_level AS UNSIGNED) < '$selected_year'
        OR (
            CAST(s.year_level AS UNSIGNED) = '$selected_year'
            AND CAST(s.semester AS UNSIGNED) < '$selected_sem'
        )
    )
");

while ($ir = mysqli_fetch_assoc($irregular_q)) {

    $g = $ir['grade'];

    if (
        $g === null ||
        $g === '' ||
        $g === 'INC' ||
        $g === 'DROP' ||
        (is_numeric($g) && (float)$g > 3.00)
    ) {
        $is_irregular = true;
        break;
    }
}

/* ================= YEAR STANDING ================= */

$yearStanding = [];

for ($y = 1; $y <= 4; $y++) {

    $res = mysqli_query($conn, "
        SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN h.grade IS NOT NULL
                    AND h.is_confirmed = 1
                    AND h.grade REGEXP '^[0-9.]+$'
                    AND CAST(h.grade AS DECIMAL(5,2)) <= 3.00
                    THEN 1 ELSE 0
                END
            ) AS passed
        FROM curriculum_subjects s
        LEFT JOIN student_subject_history h
            ON s.subject_code = h.subject_code
            AND h.student_id = '{$student['id']}'
        WHERE s.curriculum_id = '$curriculum_id'
        AND CAST(s.year_level AS UNSIGNED) = '$y'
    ");

    $r = mysqli_fetch_assoc($res);

    $total  = (int)$r['total'];
    $passed = (int)$r['passed'];

    $yearStanding[$y] = ($total > 0)
        ? ($passed / $total) * 100
        : 0;
}

/* ================= TABLE ARRAYS ================= */

$possibleSubjects   = [];
$didNotTakeSubjects = [];
$nonPreReqSubjects  = [];

/* ================= NEXT SEM FOR POSSIBLE ================= */

$next_year = $selected_year;
$next_sem  = $selected_sem + 1;

if ($selected_sem == 2 || $selected_sem == 3) {
    $next_year = $selected_year + 1;
    $next_sem  = 1;
}

/* ================= ALL SUBJECTS ================= */

$all_subjects = mysqli_query($conn, "
    SELECT *
    FROM curriculum_subjects
    WHERE curriculum_id = '$curriculum_id'
    ORDER BY CAST(year_level AS UNSIGNED),
             CAST(semester AS UNSIGNED),
             subject_code
");

while ($row = mysqli_fetch_assoc($all_subjects)) {

    $code  = $row['subject_code'];
    $title = $row['subject_title'];
    $year  = (int)$row['year_level'];
    $sem   = (int)$row['semester'];
    $units = (int)$row['units'];

    if ($year == 1 && $sem == 1) {
        continue;
    }

    $enrolled_check = mysqli_query($conn, "
        SELECT id
        FROM student_subject_history
        WHERE student_id = '{$student['id']}'
        AND subject_code = '$code'
        LIMIT 1
    ");

    $isEnrolled = mysqli_num_rows($enrolled_check) > 0;

    $locked     = false;
    $hasPrereq  = false;
    $hasYearReq = false;
    $status     = "No Pre-requisite";

    /* ================= REQUIREMENTS ================= */

    $prq = mysqli_query($conn, "
        SELECT sp.*, s.subject_code
        FROM subject_prerequisites sp
        LEFT JOIN subjects s
            ON s.id = sp.prereq_id
        WHERE sp.subject_id = '{$row['subject_id']}'
    ");

    while ($p = mysqli_fetch_assoc($prq)) {

        if (!empty($p['subject_code'])) {

            $hasPrereq = true;

            $check = mysqli_query($conn, "
                SELECT grade, is_confirmed
                FROM student_subject_history
                WHERE student_id = '{$student['id']}'
                AND subject_code = '{$p['subject_code']}'
                LIMIT 1
            ");

            $pre = mysqli_fetch_assoc($check);

            $g = $pre['grade'] ?? null;
            $c = (int)($pre['is_confirmed'] ?? 0);

            if (!$pre || $g === null || $g === '') {
                $locked = true;
                $status = "No Grade { {$p['subject_code']} }";
                break;
            }

            if ($g === 'INC') {
                $locked = true;
                $status = "Incomplete { {$p['subject_code']} }";
                break;
            }

            if ($g === 'DROP') {
                $locked = true;
                $status = "Dropped { {$p['subject_code']} }";
                break;
            }

            if (is_numeric($g) && (float)$g > 3.00) {
                $locked = true;
                $status = "Failed { {$p['subject_code']} }";
                break;
            }

            if ($is_confirmed_all && $c != 1) {
                $locked = true;
                $status = "Not Confirmed { {$p['subject_code']} }";
                break;
            }

            $status = "Passed { {$p['subject_code']} }";
        }

        if (!empty($p['year_required'])) {

            $hasYearReq = true;

            $prev = (int)$p['year_required'] - 1;

            if (($yearStanding[$prev] ?? 0) < 75) {
                $locked = true;
                $status = "Not Met Year Standing";
                break;
            }

            if (!$locked) {
                $status = "Met Year Standing";
            }
        }
    }

    /* ================= POSSIBLE / DID NOT TAKE ================= */

    if ($year == $next_year && $sem == $next_sem) {

        $data = [
            'code'       => $code,
            'title'      => $title,
            'units'      => $units,
            'year'       => $year,
            'sem'        => $sem,
            'status'     => $status,
            'isEnrolled' => $isEnrolled
        ];

        if (!$is_confirmed_all) {

            $possibleSubjects[] = $data;

        } else {

            if ($isEnrolled || !$locked) {
                $possibleSubjects[] = $data;
            } else {
                $didNotTakeSubjects[] = $data;
            }
        }
    }

    /* ================= NON PRE REQ ================= */

    if (
        !$hasPrereq &&
        !$hasYearReq &&
        !($year == 1 && $sem == 1)
    ) {

        $alreadyShown = ($year == $next_year && $sem == $next_sem);

        if (!$alreadyShown || $isEnrolled) {

            $nonPreReqSubjects[] = [
                'code'       => $code,
                'title'      => $title,
                'units'      => $units,
                'year'       => $year,
                'sem'        => $sem,
                'isEnrolled' => $isEnrolled
            ];
        }
    }
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

<meta
    name="format-detection"
    content="telephone=no"
>

<title>Suggested Subjects</title>

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
}

/* ================= CONTENT ================= */

.content{
    margin-left:265px;
    margin-right:25px;
    padding:30px;
    padding-top:40px;
    position:relative;
    z-index:1;
}

.page-title{
    font-size:24px;
    margin-bottom:20px;
    font-weight:700;
    color:#2c5aa0;
    line-height:1.3;
}

/* ================= FILTER BOX ================= */

.filter-box{
    background:#fff;
    padding:18px;
    border-radius:18px;
    margin-bottom:20px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
}

.filter-row{
    display:flex;
    align-items:center;
    gap:18px;
    flex-wrap:wrap;
}

.filter-item{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:14px;
    color:#333;
    font-weight:500;
}

/* ================= REMOVE FLOAT BUTTON ================= */

.sidebar-toggle,
.toggle,
.toggle-btn{
    display:none !important;
}

/* ================= SELECT ================= */

select{
    height:38px;
    min-width:160px;
    padding:6px 32px 6px 12px;
    font-size:13px;
    border-radius:10px;
    border:1px solid #dbe6ff;
    background:#fff;
    cursor:pointer;
    transition:.2s ease;
}

select:focus{
    border-color:#2c5aa0;
    outline:none;
    box-shadow:0 0 0 3px rgba(44,90,160,.08);
}

/* ================= TABLE ================= */

.table-wrapper{
    background:#fff;
    border-radius:18px;
    overflow-x:auto;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
    margin-bottom:22px;
    position:relative;
    z-index:1;
}

table{
    width:100%;
    min-width:900px;
    border-collapse:collapse;
}

thead{
    background:#eef3ff;
}

th{
    color:#2c5aa0;
    padding:14px;
    font-size:13px;
    text-align:center;
    border-bottom:1px solid #dbe6ff;
    font-weight:700;
}

td{
    padding:12px;
    border-bottom:1px solid #f0f0f0;
    font-size:13px;
    text-align:center;
    vertical-align:middle;
    color:#333;
}

tbody tr{
    transition:.15s ease;
}

tbody tr:hover{
    background:#f9fbff;
}

/* ================= STATUS ================= */

.status-badge{
    display:inline-block;
    padding:6px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:700;
    white-space:nowrap;
}

.passed{
    background:#e6f7ee;
    color:#27ae60;
}

.failed{
    background:#fdecea;
    color:#e74c3c;
}

.locked{
    background:#f2f2f2;
    color:#999;
}

.available{
    background:#eaf6ff;
    color:#2c5aa0;
}

.no-grade{
    background:#fff8df;
    color:#d68910;
}

/* ================= BUTTON ================= */

.edit-btn{
    background:#2c5aa0;
    color:#fff;
    border:none;
    padding:10px 18px;
    border-radius:10px;
    cursor:pointer;
    transition:.2s ease;
    font-size:14px;
    font-weight:600;
    position:relative;
    z-index:1;
}

.edit-btn:hover{
    background:#1f4a8a;
}

.edit-btn:disabled{
    background:#ccc;
    cursor:not-allowed;
}

/* ================= CHECKBOX ================= */

.subject-checkbox{
    width:17px;
    height:17px;
    cursor:pointer;
    accent-color:#2c5aa0;
}

.subject-checkbox:disabled{
    cursor:not-allowed;
    opacity:.5;
}

/* ================= TOTAL UNITS ================= */

.total-bar{
    display:flex;
    justify-content:flex-end;
    align-items:center;
    gap:6px;
    padding:12px 16px;
    border-top:1px solid #eef1f5;
    font-size:15px;
    font-weight:600;
    color:#2c5aa0;
}

#totalUnits{
    font-size:17px;
    font-weight:700;
    color:#27ae60;
}

/* ================= TABLET ================= */

@media (max-width:1024px){

    .content{
        margin-left:220px;
        margin-right:15px;
        padding:22px;
        padding-top:35px;
    }

    .page-title{
        font-size:22px;
    }

    .filter-row{
        gap:14px;
    }

    table{
        min-width:820px;
    }

    th,
    td{
        font-size:12px;
        padding:11px 8px;
    }

}

/* ================= MOBILE ================= */

@media (max-width:768px){

    /* PAGE BELOW HEADER */
    .content{
        margin-left:0 !important;
        margin-right:0 !important;
        padding:88px 14px 20px !important;
        position:relative;
        z-index:1 !important;
    }

    /* SIDENAV ABOVE EVERYTHING */
    .sidebar{
        position:fixed !important;
        top:75px !important;
        left:-260px;
        width:230px;
        height:calc(100vh - 90px);
        max-height:calc(100vh - 90px);
        z-index:9999 !important;
        overflow-y:auto;
        overflow-x:hidden;
    }

    .sidebar.active{
        left:15px !important;
    }

    .overlay{
        z-index:9998 !important;
    }

    .page-title{
        font-size:20px;
        margin-bottom:16px;
    }

    .filter-box{
        padding:14px;
        border-radius:14px;
    }

    .filter-row{
        flex-direction:column;
        align-items:stretch;
        gap:12px;
    }

    .filter-item{
        width:100%;
        justify-content:space-between;
        font-size:13px;
    }

    select{
        width:170px;
        min-width:170px;
        font-size:13px;
    }

    .table-wrapper{
        border-radius:14px;
    }

    table{
        min-width:720px;
    }

    th,
    td{
        font-size:12px;
        padding:10px 8px;
        white-space:nowrap;
    }

    .status-badge{
        font-size:11px;
        padding:5px 10px;
    }

    /* FIX ENROLL BUTTON OVERLAP */
    .edit-btn{
        width:100%;
        padding:12px;
        font-size:14px;
        z-index:1 !important;
    }

    .total-bar{
        justify-content:center;
        font-size:14px;
    }

    #totalUnits{
        font-size:16px;
    }

}

/* ================= SMALL MOBILE ================= */

@media (max-width:480px){

    .page-title{
        font-size:18px;
    }

    select{
        width:150px;
        min-width:150px;
    }

    table{
        min-width:680px;
    }

    .filter-item{
        font-size:12px;
    }

    .total-bar{
        font-size:13px;
    }

    #totalUnits{
        font-size:15px;
    }

}

</style>
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<div class="content">

    <div class="page-title">
        Prospectus - <?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?>
    </div>

    <?php if (!empty($success_message)) { ?>
        <div class="success-box">
            <?php echo $success_message; ?>
        </div>
    <?php } ?>

    <?php if (!empty($error_message)) { ?>
        <div class="error-box">
            <?php echo $error_message; ?>
        </div>
    <?php } ?>

    <div class="filter-box">

        <form method="GET">

            <div class="filter-row">

                <div class="filter-item">

                    Year:

                    <select name="year" onchange="this.form.submit()">

                        <?php
                        $yrs = mysqli_query($conn, "
                            SELECT DISTINCT year_level
                            FROM curriculum_subjects
                            WHERE curriculum_id = '$curriculum_id'
                            ORDER BY CAST(year_level AS UNSIGNED)
                        ");

                        while ($y = mysqli_fetch_assoc($yrs)) {

                            $year_value = (int) filter_var(
                                $y['year_level'],
                                FILTER_SANITIZE_NUMBER_INT
                            );
                        ?>

                            <option
                                value="<?php echo $year_value; ?>"
                                <?php if ($selected_year == $year_value) echo "selected"; ?>
                            >
                                Year <?php echo htmlspecialchars($y['year_level']); ?>
                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="filter-item">

                    Semester:

                    <select name="sem" onchange="this.form.submit()">

                        <option value="1" <?php if ($selected_sem == 1) echo "selected"; ?>>
                            1st Semester
                        </option>

                        <option value="2" <?php if ($selected_sem == 2) echo "selected"; ?>>
                            2nd Semester
                        </option>

                        <option value="3" <?php if ($selected_sem == 3) echo "selected"; ?>>
                            Intersemester
                        </option>

                    </select>

                </div>

            </div>

        </form>

    </div>

    <form method="POST">

        <!-- ================= POSSIBLE SUBJECTS ================= -->
        <div class="table-wrapper" style="margin-top:25px;">

            <table>

                <thead>

                    <tr>
                        <th colspan="7">Possible Subjects</th>
                    </tr>

                    <tr>
                        <th>Select</th>
                        <th>Code</th>
                        <th>Subject Title</th>
                        <th>Units</th>
                        <th>Year</th>
                        <th>Semester</th>
                        <th>Status</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($possibleSubjects)) { ?>

                    <?php foreach ($possibleSubjects as $s) { ?>

                        <?php
                        $statusText = htmlspecialchars($s['status']);

                        if (
                            stripos($s['status'], 'Failed') !== false ||
                            stripos($s['status'], 'Dropped') !== false ||
                            stripos($s['status'], 'Incomplete') !== false ||
                            stripos($s['status'], 'Not Met') !== false
                        ) {
                            $class = "failed";
                        } elseif (
                            stripos($s['status'], 'No Grade') !== false ||
                            stripos($s['status'], 'Not Confirmed') !== false
                        ) {
                            $class = "no-grade";
                        } else {
                            $class = "passed";
                        }
                        ?>

                        <tr>

                            <td>

                                <?php if ($s['isEnrolled']) { ?>

                                    <span class="status-badge available">
                                        Enrolled
                                    </span>

                                <?php } elseif ($is_confirmed_all) { ?>

                                    <input
                                        type="checkbox"
                                        class="subject-checkbox"
                                        name="subjects[]"
                                        value="<?php echo htmlspecialchars($s['code']); ?>"
                                        data-units="<?php echo (int)$s['units']; ?>"
                                    >

                                <?php } else { ?>

                                    <span class="status-badge locked">
                                        Pending
                                    </span>

                                <?php } ?>

                            </td>

                            <td><?php echo htmlspecialchars($s['code']); ?></td>

                            <td><?php echo htmlspecialchars($s['title']); ?></td>

                            <td class="unit">
                                <?php echo (int)$s['units']; ?>
                            </td>

                            <td>
                                Year <?php echo (int)$s['year']; ?>
                            </td>

                            <td>
                                <?php
                                echo $s['sem'] == 1
                                    ? "1st Semester"
                                    : ($s['sem'] == 2 ? "2nd Semester" : "Intersemester");
                                ?>
                            </td>

                            <td>

                                <span class="status-badge <?php echo $class; ?>">
                                    <?php echo $statusText; ?>
                                </span>

                            </td>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>
                        <td colspan="7">
                            No possible subjects available.
                        </td>
                    </tr>

                <?php } ?>

                </tbody>

            </table>

            <div class="total-bar">
                Total Units:
                <span id="totalUnits">0</span>
            </div>

            <div style="display:flex;justify-content:flex-end;padding:10px;">

                <button
                    type="submit"
                    name="enroll_subjects"
                    class="edit-btn"
                    <?php echo (!$is_confirmed_all ? "disabled" : ""); ?>
                >
                    Enroll Selected
                </button>

            </div>

        </div>



        <!-- ================= DID NOT TAKE SUBJECTS ================= -->
        <div class="table-wrapper" style="margin-top:25px;">

            <table>

                <thead>

                    <tr>
                        <th colspan="7">
                            Did Not Take Subjects (Failed)
                        </th>
                    </tr>

                    <tr>
                        <th>Status</th>
                        <th>Code</th>
                        <th>Subject Title</th>
                        <th>Units</th>
                        <th>Year</th>
                        <th>Semester</th>
                        <th>Reason</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($didNotTakeSubjects)) { ?>

                    <?php foreach ($didNotTakeSubjects as $s) { ?>

                        <tr>

                            <td>
                                <span class="status-badge failed">
                                    Not Taken
                                </span>
                            </td>

                            <td><?php echo htmlspecialchars($s['code']); ?></td>

                            <td><?php echo htmlspecialchars($s['title']); ?></td>

                            <td><?php echo (int)$s['units']; ?></td>

                            <td>
                                Year <?php echo (int)$s['year']; ?>
                            </td>

                            <td>
                                <?php
                                echo $s['sem'] == 1
                                    ? "1st Semester"
                                    : ($s['sem'] == 2 ? "2nd Semester" : "Intersemester");
                                ?>
                            </td>

                            <td>
                                <span class="status-badge no-grade">
                                    <?php echo htmlspecialchars($s['status']); ?>
                                </span>
                            </td>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>
                        <td colspan="7">
                            No subjects in this section.
                        </td>
                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>



        <!-- ================= NON PRE REQ SUBJECTS ================= -->
        <div class="table-wrapper" style="margin-top:25px;">

            <table>

                <thead>

                    <tr>
                        <th colspan="6">
                            Non Pre-Req Subjects
                        </th>
                    </tr>

                    <tr>
                        <th>Select</th>
                        <th>Code</th>
                        <th>Subject Title</th>
                        <th>Units</th>
                        <th>Year</th>
                        <th>Semester</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($nonPreReqSubjects)) { ?>

                    <?php foreach ($nonPreReqSubjects as $s) { ?>

                        <tr>

                            <td>

                                <?php if ($s['isEnrolled']) { ?>

                                    <span class="status-badge available">
                                        Enrolled
                                    </span>

                                <?php } elseif ($is_confirmed_all) { ?>

                                    <input
                                        type="checkbox"
                                        class="subject-checkbox"
                                        name="subjects[]"
                                        value="<?php echo htmlspecialchars($s['code']); ?>"
                                        data-units="<?php echo (int)$s['units']; ?>"
                                    >

                                <?php } else { ?>

                                    <span class="status-badge locked">
                                        Pending
                                    </span>

                                <?php } ?>

                            </td>

                            <td><?php echo htmlspecialchars($s['code']); ?></td>

                            <td><?php echo htmlspecialchars($s['title']); ?></td>

                            <td class="unit">
                                <?php echo (int)$s['units']; ?>
                            </td>

                            <td>
                                Year <?php echo (int)$s['year']; ?>
                            </td>

                            <td>
                                <?php
                                echo $s['sem'] == 1
                                    ? "1st Semester"
                                    : ($s['sem'] == 2 ? "2nd Semester" : "Intersemester");
                                ?>
                            </td>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>
                        <td colspan="6">
                            No non pre-requisite subjects available.
                        </td>
                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </form>

</div>

<!-- TOTAL UNITS SCRIPT -->
<script>

document.addEventListener("DOMContentLoaded", function () {

    const checkboxes   = document.querySelectorAll(".subject-checkbox");
    const totalDisplay = document.getElementById("totalUnits");
    const enrollBtn    = document.querySelector("button[name='enroll_subjects']");

    function updateTotalUnits() {

        let total = 0;
        let selectedCount = 0;

        checkboxes.forEach(function (cb) {

            if (cb.checked) {
                total += parseInt(cb.dataset.units) || 0;
                selectedCount++;
            }

        });

        /* ================= TOTAL UNITS ================= */
        if (totalDisplay) {

            totalDisplay.textContent = total;

            if (total >= 27) {
                totalDisplay.style.color = "#e74c3c";
            } else if (total >= 21) {
                totalDisplay.style.color = "#f39c12";
            } else {
                totalDisplay.style.color = "#27ae60";
            }

        }

        /* ================= BUTTON LOGIC ================= */
        if (enrollBtn) {

            if (selectedCount > 0) {

                enrollBtn.disabled = false;
                enrollBtn.style.opacity = "1";
                enrollBtn.style.cursor = "pointer";
                enrollBtn.style.pointerEvents = "auto";

            } else {

                enrollBtn.disabled = true;
                enrollBtn.style.opacity = "0.6";
                enrollBtn.style.cursor = "not-allowed";
                enrollBtn.style.pointerEvents = "none";

            }

        }

    }

    /* ================= CHECKBOX EVENTS ================= */
    checkboxes.forEach(function (cb) {
        cb.addEventListener("change", updateTotalUnits);
        cb.addEventListener("click", updateTotalUnits);
    });

    /* ================= PREVENT DOUBLE SUBMIT ================= */
    if (enrollBtn) {

        enrollBtn.addEventListener("click", function () {

            if (!enrollBtn.disabled) {

                enrollBtn.innerText = "Processing...";
                enrollBtn.style.opacity = "0.7";
                enrollBtn.style.cursor = "wait";

            }

        });

    }

    updateTotalUnits();

});

</script>

</form>

</div>

</body>
</html>