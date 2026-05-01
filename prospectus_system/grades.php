<?php

session_start();
include "includes/db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* ================= FILTERS ================= */

$selected_year = isset($_GET['year'])
    ? (int) filter_var($_GET['year'], FILTER_SANITIZE_NUMBER_INT)
    : 1;

$selected_sem = isset($_GET['sem'])
    ? (int) $_GET['sem']
    : 1;

if (!in_array($selected_sem, [1, 2, 3])) {
    $selected_sem = 1;
}

/* ================= MESSAGES ================= */

$success_message = "";
$error_message   = "";

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

/* ================= GET STUDENT ================= */

$student_query = mysqli_query($conn, "
    SELECT
        s.*,
        c.course_name
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
2. active curriculum of course
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
    die("No curriculum assigned.");
}

/* ================= CHECK CONFIRMED PER SEM ================= */

$hasConfirmed = false;

$check_confirmed = mysqli_query($conn, "
    SELECT 1
    FROM student_subject_history h
    INNER JOIN curriculum_subjects s
        ON s.subject_code = h.subject_code
        AND s.curriculum_id = '$curriculum_id'
    WHERE h.student_id = '{$student['id']}'
    AND h.is_confirmed = 1
    AND CAST(s.year_level AS UNSIGNED) = '$selected_year'
    AND CAST(s.semester AS UNSIGNED) = '$selected_sem'
    LIMIT 1
");

if (mysqli_num_rows($check_confirmed) > 0) {
    $hasConfirmed = true;
}

/* ================= RESET GRADES PER SEM ================= */

if (isset($_POST['reset_grades'])) {

    if ($hasConfirmed) {

        $error_message = "Reset failed. Grades for this semester are already confirmed.";

    } else {

        mysqli_query($conn, "
            DELETE h
            FROM student_subject_history h
            INNER JOIN curriculum_subjects s
                ON s.subject_code = h.subject_code
                AND s.curriculum_id = '$curriculum_id'
            WHERE h.student_id = '{$student['id']}'
            AND CAST(s.year_level AS UNSIGNED) = '$selected_year'
            AND CAST(s.semester AS UNSIGNED) = '$selected_sem'
        ");

        $_SESSION['success'] = "Semester grades have been reset.";

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}

/* ================= SAVE GRADES PER SEM ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grades'])) {

    if ($hasConfirmed) {

        $error_message = "Grades for this semester are already confirmed and cannot be modified.";

    } else {

        foreach ($_POST['grades'] as $subject_code => $grade) {

            $subject_code = mysqli_real_escape_string($conn, $subject_code);
            $grade = strtoupper(trim($grade));

            if ($grade === '') {
                continue;
            }

            $is_special = in_array($grade, ['INC', 'DROP']);

            if (!$is_special) {

                if (!preg_match('/^[1-5](\.\d{1,2})?$/', $grade)) {
                    continue;
                }

                $grade = round((float)$grade, 2);

                if ($grade < 1 || $grade > 5) {
                    continue;
                }
            }

            /* CHECK EXISTING */
            $existing_q = mysqli_query($conn, "
                SELECT grade, is_confirmed
                FROM student_subject_history
                WHERE student_id = '{$student['id']}'
                AND subject_code = '$subject_code'
                LIMIT 1
            ");

            $existing = mysqli_fetch_assoc($existing_q);

            if ($existing && (int)$existing['is_confirmed'] === 1) {
                continue;
            }

            $can_encode = true;

           /* CHECK PREREQUISITES */
$subject_q = mysqli_query($conn, "
    SELECT
        sp.prereq_id,
        sp.is_coreq,
        sp.year_required,
        ps.subject_code
    FROM curriculum_subjects s
    LEFT JOIN subject_prerequisites sp
        ON sp.subject_id = s.subject_id
    LEFT JOIN subjects ps
        ON ps.id = sp.prereq_id
    WHERE s.subject_code = '$subject_code'
    AND s.curriculum_id = '$curriculum_id'
");

/* SAFETY: detect SQL error */
if (!$subject_q) {
    die(mysqli_error($conn));
}

while ($pr = mysqli_fetch_assoc($subject_q)) {

    if (!empty($pr['subject_code'])) {

        $check_pre = mysqli_query($conn, "
            SELECT grade
            FROM student_subject_history
            WHERE student_id = '{$student['id']}'
            AND subject_code = '{$pr['subject_code']}'
            LIMIT 1
        ");

        $pre = mysqli_fetch_assoc($check_pre);

        $pre_grade = null;
        if ($pre && isset($pre['grade'])) {
            $pre_grade = $pre['grade'];
        }

        $passed = (
            $pre_grade !== null &&
            is_numeric($pre_grade) &&
            (float)$pre_grade <= 3.00
        );

        /* ===== COREQ LOGIC ===== */
if (!empty($pr['is_coreq'])) {

    $same_sem_q = mysqli_query($conn, "
        SELECT 1
        FROM curriculum_subjects
        WHERE subject_code = '{$pr['subject_code']}'
        AND curriculum_id = '$curriculum_id'
        AND CAST(year_level AS UNSIGNED) = '$selected_year'
        AND CAST(semester AS UNSIGNED) = '$selected_sem'
        LIMIT 1
    ");

    // SAFE: avoid 500 if query fails
    $isSameSemester = false;
    if ($same_sem_q) {
        $isSameSemester = mysqli_num_rows($same_sem_q) > 0;
    }

    // ✅ FIXED LOGIC (NO pre_grade dependency)
    if (!$passed && !$isSameSemester) {
        $can_encode = false;
        break;
    }

} else {

    /* STRICT PREREQ */
    if (!$passed) {
        $can_encode = false;
        break;
    }
}

}

if (!empty($pr['year_required'])) {

    $required_year = (int)$pr['year_required'];
    $previous_year = $required_year - 1;

    $yr_check = mysqli_query($conn, "
        SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN h.grade IS NOT NULL
                    AND h.grade REGEXP '^[0-9.]+$'
                    AND CAST(h.grade AS DECIMAL(5,2)) <= 3.00
                    THEN 1
                    ELSE 0
                END
            ) AS passed
        FROM curriculum_subjects s
        LEFT JOIN student_subject_history h
            ON h.subject_code = s.subject_code
            AND h.student_id = '{$student['id']}'
        WHERE s.curriculum_id = '$curriculum_id'
        AND CAST(s.year_level AS UNSIGNED) = '$previous_year'
    ");

    // SAFE
    if (!$yr_check) {
        $can_encode = false;
        break;
    }

    $yr = mysqli_fetch_assoc($yr_check);

    $total  = (int)($yr['total'] ?? 0);
    $passed = (int)($yr['passed'] ?? 0);

    $percent = ($total > 0)
        ? (($passed / $total) * 100)
        : 0;

    if ($percent < 75) {
        $can_encode = false;
        break;
    }
}
}

if (!$can_encode) {
    continue;
}

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
        '$subject_code',
        '$grade',
        0
    )
    ON DUPLICATE KEY UPDATE
        grade = VALUES(grade),
        is_confirmed = 0
");

}
$_SESSION['success'] = "Grades saved successfully.";

header("Location: " . $_SERVER['REQUEST_URI']);
exit();
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

<title>Prospectus</title>

<style>

:root{
    --primary:#2c5aa0;
    --bg:#f4f7fc;
}

/* ================= BASE ================= */

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
    background:var(--bg);
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
    font-size:22px;
    margin-bottom:20px;
    font-weight:600;
    color:var(--primary);
    line-height:1.3;
}

/* ================= FILTER ================= */

.filter-box{
    background:#fff;
    padding:18px;
    border-radius:18px;
    margin-bottom:20px;
    box-shadow:0 4px 14px rgba(0,0,0,.06);
}

.filter-row{
    display:flex;
    align-items:center;
    gap:20px;
    flex-wrap:wrap;
}

.filter-item{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:14px;
}

/* ================= SELECT ================= */

select{
    height:36px;
    padding:5px 30px 5px 10px;
    font-size:13px;
    border-radius:10px;
    border:1px solid #dbe6ff;
    background:#fff;
    cursor:pointer;
    transition:.2s;
}

select:hover{
    border-color:#bcd0ff;
}

select:focus{
    border-color:var(--primary);
    outline:none;
}

/* ================= TABLE ================= */

.table-wrapper{
    background:#fff;
    border-radius:18px;
    overflow-x:auto;
    box-shadow:0 4px 14px rgba(0,0,0,.06);
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
    color:var(--primary);
    padding:13px;
    font-size:13px;
    text-align:center;
    border-bottom:1px solid #dbe6ff;
}

td{
    padding:11px;
    border-bottom:1px solid #f0f0f0;
    font-size:13px;
    text-align:center;
    vertical-align:middle;
}

tbody tr{
    transition:.2s;
}

tbody tr:hover{
    background:#f9fbff;
}

/* ================= STATUS ================= */

.status-badge{
    display:inline-block;
    padding:5px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
}

.passed{
    background:#e6f7ee;
    color:#27ae60;
}

.failed{
    background:#fdecea;
    color:#e74c3c;
}

.no-grade{
    background:#eef1f5;
    color:#7a869a;
}

/* ================= BUTTONS ================= */

.edit-btn,
.danger-btn{
    color:#fff;
    border:none;
    padding:10px 16px;
    border-radius:10px;
    cursor:pointer;
    transition:.2s;
    font-size:13px;
    font-weight:600;
    position:relative;
    z-index:1;
}

.edit-btn{
    background:var(--primary);
}

.edit-btn:hover{
    background:#1f4a8a;
}

.danger-btn{
    background:#e74c3c;
}

.danger-btn:hover{
    background:#c0392b;
}

.edit-btn:disabled,
.danger-btn:disabled{
    background:#ccc;
    cursor:not-allowed;
}

/* ================= ALERT ================= */

.alert{
    padding:13px;
    border-radius:12px;
    margin-bottom:15px;
    font-size:13px;
    font-weight:500;
}

.alert.success{
    background:#e6f7ee;
    color:#27ae60;
}

.alert.error{
    background:#fdecea;
    color:#e74c3c;
}

/* ================= MODAL ================= */

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.35);
    justify-content:center;
    align-items:center;
    z-index:999;
    padding:15px;
}

.modal-box{
    background:#fff;
    padding:25px 20px;
    border-radius:16px;
    width:320px;
    max-width:100%;
    text-align:center;
    box-shadow:0 12px 30px rgba(0,0,0,.15);
}

.modal-box h3{
    margin-bottom:8px;
    color:var(--primary);
}

.modal-box p{
    font-size:13px;
    color:#555;
    margin-bottom:18px;
}

/* ================= TABLET ================= */

@media (max-width:1024px){

    .content{
        margin-left:0 !important;
        margin-right:0;
        padding:20px;
        padding-top:90px;
    }

    .filter-row{
        gap:10px;
    }

    .filter-item{
        flex:1 1 45%;
        justify-content:space-between;
    }

    select{
        width:140px;
    }

}

/* ================= MOBILE ================= */

@media (max-width:768px){

    /* page under header */
    .content{
        margin:0 !important;
        padding:15px !important;
        padding-top:90px !important;
        position:relative;
        z-index:1 !important;
    }

    /* sidenav always top */
    .sidebar{
        position:fixed !important;
        top:75px !important;
        left:-260px;
        width:230px;
        height:calc(100vh - 90px);
        max-height:calc(100vh - 90px);
        overflow-y:auto;
        overflow-x:hidden;
        z-index:9999 !important;
    }

    .sidebar.active{
        left:15px !important;
    }

    .overlay{
        z-index:9998 !important;
    }

    .page-title{
        font-size:18px;
    }

    .filter-row{
        flex-direction:column;
        align-items:stretch;
        gap:10px;
    }

    .filter-item{
        width:100%;
        justify-content:space-between;
    }

    select{
        width:100%;
    }

    table{
        min-width:700px;
    }

    th,
    td{
        font-size:12px;
        padding:8px;
        white-space:nowrap;
    }

    /* FIX SAVE / RESET BUTTONS */
    .content form + div{
        display:flex;
        flex-direction:column;
        gap:10px;
        position:relative;
        z-index:1 !important;
    }

    .edit-btn,
    .danger-btn{
        width:100%;
        padding:12px;
        font-size:14px;
    }

}

</style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="content">

    <div class="page-title">
        Encode Grades - <?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?>
    </div>

    <!-- MESSAGES -->
    <?php if (!empty($success_message)) { ?>
        <div class="alert success" onclick="this.style.display='none'">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php } ?>

    <?php if (!empty($error_message)) { ?>
        <div class="alert error" onclick="this.style.display='none'">
            <?php echo htmlspecialchars($error_message); ?>
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

    <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>Subject Code</th>
                    <th>Subject Title</th>
                    <th>Units</th>
                    <th>Pre-Requisite</th>
                    <th>Year Level</th>
                    <th>Semester</th>
                    <th>Grade</th>
                    <th>Status</th>
                </tr>

            </thead>

            <tbody>

<?php

$hasConfirmed = false;

$subjects = mysqli_query($conn, "
    SELECT
        s.*,
        MAX(h.grade) AS grade,
        MAX(h.is_confirmed) AS is_confirmed,
        GROUP_CONCAT(
            DISTINCT CASE
                WHEN sp.year_required IS NOT NULL
                    THEN CONCAT(sp.year_required, ' Year Standing')
                WHEN sp.note IS NOT NULL
                    THEN sp.note
                WHEN ps.subject_code IS NOT NULL
                    THEN ps.subject_code
            END
            SEPARATOR ', '
        ) AS prereq_codes
    FROM curriculum_subjects s

    LEFT JOIN student_subject_history h
        ON h.subject_code = s.subject_code
        AND h.student_id = '{$student['id']}'

    LEFT JOIN subject_prerequisites sp
        ON sp.subject_id = s.subject_id

    LEFT JOIN subjects ps
        ON ps.id = sp.prereq_id

    WHERE s.curriculum_id = '$curriculum_id'
    AND CAST(s.year_level AS UNSIGNED) = '$selected_year'
    AND CAST(s.semester AS UNSIGNED) = '$selected_sem'

    GROUP BY s.id
    ORDER BY s.subject_code
");

if (mysqli_num_rows($subjects) == 0) {
    echo "<tr><td colspan='8'>No subjects found</td></tr>";
}

while ($row = mysqli_fetch_assoc($subjects)) {

    $subject_code = htmlspecialchars($row['subject_code']);
    $title        = htmlspecialchars($row['subject_title']);
    $units        = htmlspecialchars($row['units'] ?? '-');

    $prereq = !empty(trim($row['prereq_codes']))
        ? htmlspecialchars($row['prereq_codes'])
        : "None";

    $grade        = $row['grade'];
    $is_confirmed = $row['is_confirmed'];

    if ($is_confirmed) {
        $hasConfirmed = true;
    }

    $already_has_grade = ($grade !== null && $grade !== '');
    $can_encode = true;
    $lock_reason = "No grades encoded";

    /* ======================================================
       LOCK LOGIC
    ====================================================== */

    if (!$already_has_grade) {

        $prq = mysqli_query($conn, "
            SELECT
                sp.prereq_id,
                sp.is_coreq,
                sp.year_required,
                ps.subject_code
            FROM subject_prerequisites sp

            LEFT JOIN subjects ps
                ON ps.id = sp.prereq_id

            WHERE sp.subject_id = '{$row['subject_id']}'
        ");

        while ($p = mysqli_fetch_assoc($prq)) {

            /* PRE / COREQ */
            if (!empty($p['subject_code'])) {

                $check = mysqli_query($conn, "
                    SELECT MAX(grade) AS grade
                    FROM student_subject_history
                    WHERE student_id = '{$student['id']}'
                    AND subject_code = '{$p['subject_code']}'
                ");

                $pre = mysqli_fetch_assoc($check);
                $pre_grade = $pre['grade'] ?? null;

                $isPassed = (
                    $pre_grade !== null &&
                    is_numeric($pre_grade) &&
                    (float)$pre_grade <= 3.00
                );

                $isTaking = ($pre_grade === null);

                if ($p['is_coreq']) {

                    if (!$isPassed && !$isTaking) {

                        $can_encode = false;

                        if ($pre_grade === 'INC') {
                            $lock_reason = "Incomplete { {$p['subject_code']} }";
                        } elseif ($pre_grade === 'DROP') {
                            $lock_reason = "Dropped { {$p['subject_code']} }";
                        } elseif (is_numeric($pre_grade) && (float)$pre_grade > 3.00) {
                            $lock_reason = "Failed { {$p['subject_code']} }";
                        } else {
                            $lock_reason = "No grades encoded";
                        }

                        break;
                    }

                } else {

                    if (!$isPassed) {

                        $can_encode = false;

                        if ($pre_grade === 'INC') {
                            $lock_reason = "Incomplete { {$p['subject_code']} }";
                        } elseif ($pre_grade === 'DROP') {
                            $lock_reason = "Dropped { {$p['subject_code']} }";
                        } elseif (is_numeric($pre_grade) && (float)$pre_grade > 3.00) {
                            $lock_reason = "Failed { {$p['subject_code']} }";
                        } else {
                            $lock_reason = "No grades encoded";
                        }

                        break;
                    }
                }
            }

            /* YEAR STANDING */
            if (!empty($p['year_required'])) {

                $previous_year = (int)$p['year_required'] - 1;

                $yr_check = mysqli_query($conn, "
                    SELECT
                        COUNT(*) AS total,
                        SUM(
                            CASE
                                WHEN h.grade IS NOT NULL
                                AND h.grade REGEXP '^[0-9.]+$'
                                AND CAST(h.grade AS DECIMAL(5,2)) <= 3.00
                                THEN 1
                                ELSE 0
                            END
                        ) AS passed

                    FROM curriculum_subjects s

                    LEFT JOIN student_subject_history h
                        ON h.subject_code = s.subject_code
                        AND h.student_id = '{$student['id']}'

                    WHERE s.curriculum_id = '$curriculum_id'
                    AND CAST(s.year_level AS UNSIGNED) = '$previous_year'
                ");

                $yr = mysqli_fetch_assoc($yr_check);

                $total  = (int)$yr['total'];
                $passed = (int)$yr['passed'];

                $percent = ($total > 0)
                    ? (($passed / $total) * 100)
                    : 0;

                if ($percent < 75) {
                    $can_encode = false;
                    $lock_reason = "Year Standing Not Met";
                    break;
                }
            }
        }
    }

    /* ======================================================
       STATUS
    ====================================================== */

    if ($grade === null || $grade === '') {

        if (!$can_encode) {
            $status = $lock_reason;
            $status_class = "failed";
        } else {
            $status = "No grades encoded";
            $status_class = "no-grade";
        }

    } elseif ($grade === 'INC') {

        $status = "Incomplete";
        $status_class = "failed";

    } elseif ($grade === 'DROP') {

        $status = "Dropped Subject";
        $status_class = "failed";

    } elseif (is_numeric($grade) && (float)$grade <= 3.00) {

        $status = "Passed";
        $status_class = "passed";

    } else {

        $status = "Failed";
        $status_class = "failed";
    }

    if ($is_confirmed) {
        $status .= " (Confirmed)";
    }

?>

<tr>

    <td><?php echo $subject_code; ?></td>

    <td><?php echo $title; ?></td>

    <td><?php echo $units; ?></td>

    <td><?php echo $prereq; ?></td>

    <td>Year <?php echo htmlspecialchars($row['year_level']); ?></td>

    <td>
        <?php
        echo $row['semester'] == 1 ? "1st Semester" :
            ($row['semester'] == 2 ? "2nd Semester" :
            ($row['semester'] == 3 ? "Intersemester" :
            htmlspecialchars($row['semester'])));
        ?>
    </td>

    <td>

        <?php if ($is_confirmed): ?>

            <b>
                <?php
                echo is_numeric($grade)
                    ? number_format((float)$grade, 2)
                    : htmlspecialchars($grade);
                ?>
            </b>

        <?php elseif (!$can_encode): ?>

            <input
                type="text"
                disabled
                placeholder="Locked"
                title="<?php echo htmlspecialchars($lock_reason); ?>"
                style="
                    width:70px;
                    padding:5px;
                    border-radius:6px;
                    border:1px solid #ccc;
                    background:#eee;
                    color:#777;
                    cursor:not-allowed;
                    text-align:center;
                "
            >

        <?php else: ?>

            <input
                type="text"
                name="grades[<?php echo $subject_code; ?>]"
                value="<?php
                    if ($grade !== null && $grade !== '') {
                        echo is_numeric($grade)
                            ? number_format((float)$grade, 2)
                            : htmlspecialchars($grade);
                    }
                ?>"
                maxlength="5"
                autocomplete="off"
                style="
                    width:70px;
                    padding:5px;
                    border-radius:6px;
                    border:1px solid #ccc;
                    text-align:center;
                "
                oninput="validateGrade(this)"
                onblur="formatGrade(this)"
            >

        <?php endif; ?>

    </td>

    <td>
        <span class="status-badge <?php echo $status_class; ?>">
            <?php echo htmlspecialchars($status); ?>
        </span>
    </td>

</tr>

<?php } ?>

            </tbody>

        </table>

    </div>

    <div style="margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;">

        <button
            type="submit"
            class="edit-btn"
            <?php echo $hasConfirmed ? "disabled" : ""; ?>
        >
            Save Grades
        </button>

        <button
            type="button"
            class="danger-btn"
            onclick="openResetModal()"
            <?php echo $hasConfirmed ? "disabled" : ""; ?>
        >
            Reset Initial Grades
        </button>

    </div>

    </form>

</div>

<div id="resetModal" class="modal">

    <div class="modal-box">

        <h3>Reset Grades</h3>

        <p>Are you sure you want to reset all initial grades?</p>

        <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap;">

            <button
                type="button"
                class="edit-btn"
                onclick="submitReset()"
            >
                Yes, Reset
            </button>

            <button
                type="button"
                class="danger-btn"
                onclick="closeResetModal()"
            >
                Cancel
            </button>

        </div>

    </div>

</div>

<script>

function openResetModal() {

    const resetBtn = document.querySelector(".danger-btn");

    if (resetBtn && resetBtn.disabled) return;

    const modal = document.getElementById("resetModal");

    if (modal) {
        modal.style.display = "flex";
        document.body.style.overflow = "hidden";
    }
}


function closeResetModal() {

    const modal = document.getElementById("resetModal");

    if (modal) {
        modal.style.display = "none";
        document.body.style.overflow = "";
    }
}


function submitReset() {

    const form = document.querySelector("form[method='POST']");
    const resetBtn = document.querySelector(".danger-btn");

    if (!form || (resetBtn && resetBtn.disabled)) return;

    if (!form.querySelector("input[name='reset_grades']")) {

        const input = document.createElement("input");

        input.type  = "hidden";
        input.name  = "reset_grades";
        input.value = "1";

        form.appendChild(input);
    }

    form.submit();
}


/* ================= GRADE INPUT ================= */

function validateGrade(input) {

    let value = input.value.toUpperCase().trim();

    if ("INC".startsWith(value)) {
        input.value = value;
        return;
    }

    if ("DROP".startsWith(value)) {
        input.value = value;
        return;
    }

    value = value.replace(/[^0-9.]/g, '');

    let parts = value.split('.');

    if (parts.length > 2) {
        value = parts[0] + '.' + parts[1];
    }

    if (value.startsWith('.')) {
        value = value.substring(1);
    }

    if (value.includes('.')) {

        let split = value.split('.');
        let whole = split[0].substring(0, 1);
        let dec   = split[1].substring(0, 2);

        value = whole + '.' + dec;

    } else {

        value = value.substring(0, 1);
    }

    input.value = value;
}


function formatGrade(input) {

    let value = input.value.toUpperCase().trim();

    if (value === '') return;

    if (value === "INC") {
        input.value = "INC";
        return;
    }

    if (value === "DROP") {
        input.value = "DROP";
        return;
    }

    let num = parseFloat(value);

    if (isNaN(num) || num < 1 || num > 5) {
        input.value = '';
        return;
    }

    input.value = num.toFixed(2);
}


/* ================= AUTO NEXT INPUT ================= */

document.addEventListener("DOMContentLoaded", function () {

    const inputs = document.querySelectorAll("input[name^='grades[']:not([disabled])");

    inputs.forEach((input, index) => {

        input.addEventListener("focus", function () {
            input.select();
        });

        input.addEventListener("keydown", function(e) {

            if (e.key === "Enter") {

                e.preventDefault();

                formatGrade(input);

                const next = inputs[index + 1];

                if (next) {

                    next.focus();
                    next.select();

                } else {

                    const form = document.querySelector("form[method='POST']");
                    if (form) form.submit();
                }
            }

            if (e.key === "ArrowDown") {

                e.preventDefault();

                const next = inputs[index + 1];
                if (next) next.focus();
            }

            if (e.key === "ArrowUp") {

                e.preventDefault();

                const prev = inputs[index - 1];
                if (prev) prev.focus();
            }

        });

    });

});


/* ================= BUTTON STATES ================= */

document.addEventListener("DOMContentLoaded", function () {

    const saveBtn  = document.querySelector(".edit-btn");
    const resetBtn = document.querySelector(".danger-btn");

    if (saveBtn && saveBtn.disabled) {
        saveBtn.style.cursor = "not-allowed";
        saveBtn.style.opacity = "0.7";
    }

    if (resetBtn && resetBtn.disabled) {
        resetBtn.style.cursor = "not-allowed";
        resetBtn.style.opacity = "0.7";
    }

});


/* CLOSE MODAL OUTSIDE */
window.addEventListener("click", function(e) {

    const modal = document.getElementById("resetModal");

    if (e.target === modal) {
        closeResetModal();
    }
});


/* ESC CLOSE */
document.addEventListener("keydown", function(e) {

    if (e.key === "Escape") {
        closeResetModal();
    }

});


/* ================= AUTO SAVE (NO RETYPE FIX) ================= */

document.addEventListener("DOMContentLoaded", function () {

    const inputs = document.querySelectorAll("input[name^='grades[']");

    inputs.forEach(input => {

        const saved = localStorage.getItem(input.name);

        if (saved !== null && input.value === "") {
            input.value = saved;
        }

        input.addEventListener("input", function () {
            localStorage.setItem(input.name, input.value);
        });

    });

});

/* CLEAR AFTER SUCCESS */
<?php if (isset($_SESSION['success'])) { ?>
localStorage.clear();
<?php } ?>

</script>
</body>
</html>