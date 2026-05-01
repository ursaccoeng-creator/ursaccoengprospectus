<?php

session_start();
include "../includes/db.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "../PHPMailer/PHPMailer-master/src/PHPMailer.php";
require "../PHPMailer/PHPMailer-master/src/SMTP.php";
require "../PHPMailer/PHPMailer-master/src/Exception.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

/* ================= CONFIRM SELECTED ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_selected'])) {

    $students = $_POST['students'] ?? [];
    $sem = (int)($_POST['sem'] ?? 1);

    if (!in_array($sem, [1, 2, 3])) {
        $sem = 1;
    }

    if (!empty($students) && is_array($students)) {

        $confirmedCount = 0;
        $emailSentCount = 0;

        foreach ($students as $student_id) {

            $student_id = (int)$student_id;

            $run = mysqli_query($conn, "
                UPDATE student_subject_history h
                INNER JOIN students s
                    ON s.id = h.student_id
                INNER JOIN subjects sub
                    ON sub.subject_code = h.subject_code
                    AND sub.course_id = s.course_id
                SET h.is_confirmed = 1
                WHERE h.student_id = '$student_id'
                AND CAST(sub.semester AS UNSIGNED) = '$sem'
                AND h.grade IS NOT NULL
                AND h.grade <> ''
            ");

            if ($run && mysqli_affected_rows($conn) > 0) {

                $confirmedCount++;

                /* ================= GET STUDENT EMAIL ================= */

                $studentQuery = mysqli_query($conn, "
                    SELECT full_name, email
                    FROM students
                    WHERE id = '$student_id'
                    LIMIT 1
                ");

                $stu = mysqli_fetch_assoc($studentQuery);

                if ($stu && !empty($stu['email'])) {

                    $studentName  = $stu['full_name'];
                    $studentEmail = trim($stu['email']);

                    /* SEM LABEL */

                    if ($sem == 1) {
                        $semLabel = "1st Semester";
                    } elseif ($sem == 2) {
                        $semLabel = "2nd Semester";
                    } else {
                        $semLabel = "Intersemester";
                    }

                    /* ================= SEND EMAIL ================= */

                    try {

                        $mail = new PHPMailer(true);

                        $mail->isSMTP();
                        $mail->Host       = "smtp.gmail.com";
                        $mail->SMTPAuth   = true;
                        $mail->Username   = "ursaccoeng@gmail.com";
                        $mail->Password   = "ipzyafayynidonwk";
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        $mail->CharSet    = "UTF-8";

                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer'       => false,
                                'verify_peer_name'  => false,
                                'allow_self_signed' => true
                            ]
                        ];

                        $mail->setFrom(
                            "ursaccoeng@gmail.com",
                            "Prospectus System"
                        );

                        $mail->addAddress($studentEmail, $studentName);

                        $mail->isHTML(true);
                        $mail->Subject = "Grades Confirmed";

                        $mail->Body = "
                            Hello <b>$studentName</b>,<br><br>

                            Your grades for <b>$semLabel</b> have been officially confirmed.<br><br>

                            You may now login to the Prospectus System to review your records.<br><br>

                            Thank you.
                        ";

                        $mail->AltBody =
                            "Hello $studentName,\n\n" .
                            "Your grades for $semLabel have been officially confirmed.\n" .
                            "You may now login to the Prospectus System.\n\n" .
                            "Thank you.";

                        if ($mail->send()) {
                            $emailSentCount++;
                        }

                    } catch (Exception $e) {
                        /* continue even if one mail fails */
                    }
                }
            }
        }

        $_SESSION['success_msg'] =
            $confirmedCount .
            " student(s) grades confirmed. " .
            $emailSentCount .
            " email(s) sent.";

    } else {

        $_SESSION['error_msg'] = "No students selected.";
    }

    header("Location: class_lists.php?sem=$sem");
    exit();
}


/* ================= FILTERS ================= */

$course_id = $_GET['course_id'] ?? "";
$year      = $_GET['year'] ?? "";
$section   = $_GET['section'] ?? "";
$search    = $_GET['search'] ?? "";
$sem       = $_GET['sem'] ?? 1;

$sem = (int)$sem;

if (!in_array($sem, [1, 2, 3])) {
    $sem = 1;
}


/* ================= DROPDOWNS ================= */

$courses = mysqli_query($conn, "
    SELECT id, course_name
    FROM courses
    ORDER BY course_name ASC
");

$sections = mysqli_query($conn, "
    SELECT section_name
    FROM sections
    ORDER BY section_name ASC
");

$years = mysqli_query($conn, "
    SELECT DISTINCT CAST(year_name AS UNSIGNED) AS year
    FROM year_levels
    ORDER BY year ASC
");


/* ================= QUERY ================= */

$query = "
    SELECT
        s.id,
        s.student_id,
        s.full_name,
        s.year_level,
        s.section,
        s.course_id,

        c.course_name,

        COUNT(
            DISTINCT CASE
                WHEN h.grade IS NOT NULL
                AND h.grade <> ''
                AND (
                    h.grade = 'INC'
                    OR h.grade = 'DROP'
                    OR h.grade REGEXP '^[0-9.]+$'
                )
                THEN h.id
            END
        ) AS grade_count

    FROM students s

    LEFT JOIN courses c
        ON c.id = s.course_id

    LEFT JOIN student_subject_history h
        ON h.student_id = s.id
        AND h.subject_code IN (
            SELECT sub.subject_code
            FROM subjects sub
            WHERE sub.course_id = s.course_id
            AND CAST(sub.semester AS UNSIGNED) = '$sem'
        )
";


/* ================= CONDITIONS ================= */

$query .= " WHERE 1 ";

/* COURSE */
if ($course_id !== "") {
    $course_id = (int)$course_id;
    $query .= " AND s.course_id = $course_id";
}

/* YEAR */
if ($year !== "") {
    $year = (int)$year;
    $query .= " AND CAST(s.year_level AS UNSIGNED) = $year";
}

/* SECTION */
if ($section !== "") {
    $section = mysqli_real_escape_string($conn, $section);
    $query .= " AND s.section = '$section'";
}

/* SEARCH */
if ($search !== "") {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (
        s.full_name LIKE '%$search%'
        OR s.student_id LIKE '%$search%'
    )";
}


/* ================= SEM FILTER ================= */

$query .= "
    AND EXISTS (
        SELECT 1
        FROM subjects semsub
        WHERE semsub.course_id = s.course_id
        AND CAST(semsub.semester AS UNSIGNED) = '$sem'
    )
";


/* ================= GROUP + ORDER ================= */

$query .= "
    GROUP BY s.id
    ORDER BY s.full_name ASC
";


/* ================= EXECUTE ================= */

$result = mysqli_query($conn, $query);

if (!$result) {
    die('Query Error: ' . mysqli_error($conn));
}


/* ================= HELPER ================= */

function getGradeStatus($count)
{
    return ($count > 0) ? "Encoded" : "No Grades";
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

<title>Class Lists</title>

<style>

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
    background:#f4f7fc;
}

/* ================= CONTENT ================= */

.content{
    margin-left:265px;
    margin-right:25px;
    padding:30px;
    padding-top:40px;
}

.page-title{
    font-size:22px;
    font-weight:700;
    margin-bottom:15px;
    color:#2c5aa0;
    line-height:1.3;
}

/* ================= SEM TABS ================= */

.sem-tabs{
    display:flex;
    gap:8px;
    margin-bottom:15px;
    border-bottom:2px solid #e6edff;
    padding-bottom:5px;
    flex-wrap:wrap;
}

.sem-tab{
    padding:8px 16px;
    border-radius:8px 8px 0 0;
    text-decoration:none;
    color:#2c5aa0;
    font-size:13px;
    background:#eef3ff;
    transition:.2s;
    white-space:nowrap;
    font-weight:600;
}

.sem-tab:hover{
    background:#dde6ff;
}

.sem-tab.active{
    background:#fff;
    border:1px solid #dbe6ff;
    border-bottom:none;
}

/* ================= FILTERS ================= */

.filters{
    display:flex;
    gap:10px;
    margin-bottom:15px;
    flex-wrap:wrap;
    align-items:center;
}

.select{
    padding:11px;
    border-radius:10px;
    border:1px solid #dbe6ff;
    background:#fff;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
    outline:none;
    font-size:13px;
    width:200px;
    transition:.2s;
}

.select:focus{
    border-color:#2c5aa0;
}

/* ================= ACTION BAR ================= */

.action-bar{
    display:flex;
    justify-content:flex-end;
    margin-bottom:10px;
}

/* ================= BUTTON ================= */

.btn{
    padding:10px 16px;
    border:none;
    background:#2c5aa0;
    color:#fff;
    border-radius:10px;
    cursor:pointer;
    font-size:13px;
    font-weight:600;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
    transition:.2s;
}

.btn:hover{
    background:#1f4580;
}

/* ================= CARD ================= */

.card{
    background:#fff;
    padding:20px;
    border-radius:18px;
    overflow:visible;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
}

/* ================= TABLE ================= */

.table-scroll{
    overflow-x:auto;
    overflow-y:visible;
    width:100%;
    -webkit-overflow-scrolling:touch;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:700px;
}

thead{
    background:#eef3ff;
}

th{
    color:#2c5aa0;
    padding:13px;
    font-size:13px;
    text-align:center;
    border-bottom:1px solid #dbe6ff;
    white-space:nowrap;
}

td{
    padding:12px;
    border-bottom:1px solid #f0f0f0;
    font-size:13px;
    text-align:center;
    white-space:nowrap;
}

tr:last-child td{
    border-bottom:none;
}

/* ================= CHECKBOX ================= */

.row-check{
    cursor:pointer;
    transform:scale(1.08);
}

/* ================= PILLS ================= */

.pill{
    display:inline-block;
    padding:6px 14px;
    border-radius:20px;
    font-size:12px;
    font-weight:700;
    cursor:pointer;
    user-select:none;
}

.encoded{
    background:#e6f7ee;
    color:#27ae60;
}

.no-grade{
    background:#f2f2f2;
    color:#888;
}

/* ================= DROPDOWN ================= */

.dropdown{
    position:relative;
    display:inline-block;
}

.dropdown-content{
    display:none;
    position:absolute;
    top:110%;
    left:0;
    background:#fff;
    border-radius:12px;
    min-width:160px;
    box-shadow:0 10px 25px rgba(0,0,0,.15);
    z-index:99999;
    overflow:hidden;
}

.dropdown.active .dropdown-content{
    display:block;
}

.dropdown-content div{
    padding:10px 12px;
    font-size:13px;
    cursor:pointer;
    text-align:left;
    transition:.2s;
}

.dropdown-content div:hover{
    background:#f4f7fc;
}

.disabled{
    color:#bbb;
    pointer-events:none;
    background:#fafafa;
    cursor:not-allowed;
}

/* ================= MODAL ================= */

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    justify-content:center;
    align-items:center;
    z-index:9999;
    padding:20px;
}

.modal-box{
    background:#fff;
    width:100%;
    max-width:980px;
    max-height:92vh;
    overflow-y:auto;
    overflow-x:hidden;
    padding:22px;
    border-radius:18px;
    box-shadow:0 15px 40px rgba(0,0,0,.18);
}

.modal-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    margin-bottom:12px;
}

.modal-head b{
    font-size:22px;
    color:#2c5aa0;
}

.close-btn{
    border:none;
    background:none;
    font-size:28px;
    cursor:pointer;
    line-height:1;
}

#modalContent{
    overflow-x:hidden;
}

/* ================= MESSAGE BOX ================= */

#msgBox{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.35);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:99999;
    padding:20px;
}

.msg-card{
    background:#fff;
    width:100%;
    max-width:340px;
    border-radius:16px;
    padding:22px;
    box-shadow:0 20px 45px rgba(0,0,0,.15);
    text-align:center;
}

.msg-card.success{
    border-top:5px solid #27ae60;
}

.msg-card.error{
    border-top:5px solid #e74c3c;
}

.msg-text{
    font-size:15px;
    color:#222;
    margin-bottom:18px;
    line-height:1.4;
}

.msg-card button{
    border:none;
    background:#2c5aa0;
    color:#fff;
    padding:10px 18px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
}

/* ================= TABLET ================= */

@media (max-width:1024px){

    .content{
        margin-left:0 !important;
        margin-right:0;
        padding:20px;
        padding-top:90px;
    }

    .select{
        flex:1 1 48%;
        width:100%;
        min-width:180px;
    }

    .modal{
        padding:15px;
    }

    .modal-box{
        max-width:95vw;
        padding:18px;
    }

    #modalContent{
        overflow-x:auto;
    }

    #modalContent table{
        min-width:760px;
    }

}

/* ================= MOBILE ================= */

@media (max-width:768px){

    .content{
        margin:0;
        padding:15px;
        padding-top:90px;
    }

    .page-title{
        font-size:20px;
    }

    .filters{
        flex-direction:column;
        align-items:stretch;
    }

    .select{
        width:100%;
        min-width:100%;
        font-size:13px;
    }

    .action-bar{
        justify-content:stretch;
    }

    .btn{
        width:100%;
        padding:12px;
    }

    .card{
        padding:15px;
        border-radius:15px;
    }

    .sem-tabs{
        gap:6px;
    }

    .sem-tab{
        flex:1 1 calc(50% - 6px);
        text-align:center;
        padding:10px;
    }

    table{
        min-width:620px;
    }

    th,
    td{
        font-size:13px;
        padding:9px;
    }

    .modal{
        padding:10px;
    }

    .modal-box{
        width:100%;
        max-width:100%;
        max-height:92vh;
        padding:14px;
        border-radius:14px;
    }

    .modal-head b{
        font-size:20px;
    }

    #modalContent{
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
    }

    #modalContent table{
        min-width:680px;
    }

}

/* ================= SMALL MOBILE ================= */

@media (max-width:480px){

    .content{
        padding:12px;
        padding-top:84px;
    }

    .page-title{
        font-size:18px;
    }

    .sem-tab{
        flex:1 1 100%;
        font-size:13px;
    }

    th,
    td{
        font-size:12px;
        padding:8px;
    }

    #modalContent table{
        min-width:620px;
    }

}

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<?php
$sem = $_GET['sem'] ?? 1;

/* CHECK INTERSEM */
$hasIntersem = false;

if (!empty($course_id)) {

    /* SPECIFIC COURSE */
    $check = mysqli_query($conn, "
        SELECT 1 
        FROM subjects 
        WHERE course_id = '$course_id'
        AND CAST(semester AS UNSIGNED) = 3
        LIMIT 1
    ");

} else {

    /* ALL COURSE */
    $check = mysqli_query($conn, "
        SELECT 1
        FROM subjects
        WHERE CAST(semester AS UNSIGNED) = 3
        LIMIT 1
    ");
}

if ($check && mysqli_num_rows($check) > 0) {
    $hasIntersem = true;
}
?>

<div class="content">

    <div class="page-title">
        Grades Tracker
    </div>

    <?php if (isset($_SESSION['success_msg'])) { ?>
        <script>
        window.addEventListener("load", function () {
            showMessage("<?php echo $_SESSION['success_msg']; ?>", true);
        });
        </script>
    <?php unset($_SESSION['success_msg']); } ?>

    <?php if (isset($_SESSION['error_msg'])) { ?>
        <script>
        window.addEventListener("load", function () {
            showMessage("<?php echo $_SESSION['error_msg']; ?>", false);
        });
        </script>
    <?php unset($_SESSION['error_msg']); } ?>


    <!-- FILTERS -->
    <form method="GET" class="filters">

        <input type="hidden" name="sem" value="<?php echo $sem; ?>">

        <input 
            type="text" 
            name="search" 
            placeholder="Search student..." 
            class="select"
            value="<?php echo htmlspecialchars($search ?? ''); ?>"
        >

        <select name="course_id" class="select" onchange="this.form.submit()">
            <option value="">All Course</option>

            <?php while ($c = mysqli_fetch_assoc($courses)) { ?>
                <option 
                    value="<?php echo $c['id']; ?>"
                    <?php if ($course_id == $c['id']) echo "selected"; ?>
                >
                    <?php echo $c['course_name']; ?>
                </option>
            <?php } ?>

        </select>

        <select name="year" class="select" onchange="this.form.submit()">
            <option value="">All Year</option>

            <?php while ($y = mysqli_fetch_assoc($years)) { ?>
                <option 
                    value="<?php echo $y['year']; ?>"
                    <?php if ($year == $y['year']) echo "selected"; ?>
                >
                    <?php echo $y['year']; ?>
                </option>
            <?php } ?>

        </select>

        <select name="section" class="select" onchange="this.form.submit()">
            <option value="">All Section</option>

            <?php while ($s = mysqli_fetch_assoc($sections)) { ?>
                <option 
                    value="<?php echo $s['section_name']; ?>"
                    <?php if ($section == $s['section_name']) echo "selected"; ?>
                >
                    <?php echo $s['section_name']; ?>
                </option>
            <?php } ?>

        </select>

    </form>


    <!-- CONFIRM BUTTON -->
    <div class="action-bar">
        <button type="button" class="btn" onclick="confirmSelected()">
            Confirm Selected
        </button>
    </div>


    <!-- TABLE CARD -->
    <div class="card">

        <!-- SEM TABS -->
        <div class="sem-tabs table-tabs">

            <a href="?sem=1&course_id=<?php echo $course_id; ?>&year=<?php echo $year; ?>&section=<?php echo $section; ?>&search=<?php echo urlencode($search); ?>"
               class="sem-tab <?php if ($sem == 1) echo 'active'; ?>">
                1st Semester
            </a>

            <a href="?sem=2&course_id=<?php echo $course_id; ?>&year=<?php echo $year; ?>&section=<?php echo $section; ?>&search=<?php echo urlencode($search); ?>"
               class="sem-tab <?php if ($sem == 2) echo 'active'; ?>">
                2nd Semester
            </a>

            <?php if ($hasIntersem) { ?>
            <a href="?sem=3&course_id=<?php echo $course_id; ?>&year=<?php echo $year; ?>&section=<?php echo $section; ?>&search=<?php echo urlencode($search); ?>"
               class="sem-tab <?php if ($sem == 3) echo 'active'; ?>">
                Intersemester
            </a>
            <?php } ?>

        </div>


        <div class="table-scroll">

        <table>

            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Section</th>
                    <th>Grades</th>
                </tr>
            </thead>

            <tbody>

            <?php while ($row = mysqli_fetch_assoc($result)) { 

                $hasGrades   = isset($row['grade_count']) && $row['grade_count'] > 0;
                $currentYear = (int)$row['year_level'];
            ?>

                <tr>

                    <td>
                        <input 
                            type="checkbox" 
                            class="row-check"
                            value="<?php echo $row['id']; ?>"
                        >
                    </td>

                    <td><?php echo $row['student_id']; ?></td>
                    <td><?php echo $row['full_name']; ?></td>
                    <td><?php echo $row['course_name'] ?? 'No Course'; ?></td>
                    <td><?php echo $row['year_level']; ?></td>
                    <td><?php echo $row['section']; ?></td>

                    <td>

                        <div class="dropdown">

                            <?php if ($hasGrades) { ?>

                                <span class="pill encoded" onclick="toggleDropdown(this,event)" style="cursor:pointer;">
                                    Encoded ▼
                                </span>

                            <?php } else { ?>

                                <span class="pill no-grade" onclick="toggleDropdown(this,event)" style="cursor:pointer;">
                                    No Grades ▼
                                </span>

                            <?php } ?>

                            <div class="dropdown-content">

                                <?php
                                for ($i = 1; $i <= 4; $i++) {

                                    $suffix = "th";
                                    if ($i == 1) $suffix = "st";
                                    elseif ($i == 2) $suffix = "nd";
                                    elseif ($i == 3) $suffix = "rd";

                                    if ($i <= $currentYear) {

                                        $yearHasGrade = false;

                                        $checkYear = mysqli_query($conn, "
                                            SELECT id
                                            FROM student_subject_history
                                            WHERE student_id = '{$row['id']}'
                                            AND CAST(year_level AS UNSIGNED) = '$i'
                                            AND grade IS NOT NULL
                                            AND grade <> ''
                                            AND (
                                                grade = 'INC'
                                                OR grade = 'DROP'
                                                OR grade REGEXP '^[0-9.]+$'
                                            )
                                            LIMIT 1
                                        ");

                                        if ($checkYear && mysqli_num_rows($checkYear) > 0) {
                                            $yearHasGrade = true;
                                        }

                                        if ($yearHasGrade) {

                                            echo "
                                            <div
                                                onclick=\"openGradeModal({$row['id']}, $i)\"
                                                style='color:#16a34a;font-weight:700;cursor:pointer;'>
                                                {$i}{$suffix} Year
                                            </div>";

                                        } else {

                                            echo "
                                            <div
                                                onclick=\"openGradeModal({$row['id']}, $i)\"
                                                style='color:#222;font-weight:500;cursor:pointer;'>
                                                {$i}{$suffix} Year
                                            </div>";
                                        }

                                    } else {

                                        echo "
                                        <div class='disabled'>
                                            {$i}{$suffix} Year
                                        </div>";
                                    }
                                }
                                ?>

                            </div>

                        </div>

                    </td>

                </tr>

            <?php } ?>

            </tbody>

        </table>

        </div>

    </div>

</div>


<!-- MODAL -->
<div id="gradeModal" class="modal">

    <div class="modal-box">

        <div style="display:flex;justify-content:space-between;align-items:center;">

            <b id="modalTitle">Student Grades</b>

            <button 
                type="button"
                onclick="closeGradeModal()"
                style="border:none;background:none;font-size:18px;cursor:pointer;">
                ×
            </button>

        </div>

        <div id="modalContent" style="margin-top:10px;">
            Loading...
        </div>

    </div>

</div>


<!-- ================= SCRIPT ================= -->
<script>

/* ================= GET CURRENT SEM ================= */
function getCurrentSem() {
    const params = new URLSearchParams(window.location.search);
    return parseInt(params.get("sem") || 1);
}


/* ================= CUSTOM MESSAGE ================= */
function showMessage(message, success = true) {

    let oldBox = document.getElementById("msgBox");
    if (oldBox) oldBox.remove();

    const box = document.createElement("div");
    box.id = "msgBox";

    box.innerHTML = `
        <div class="msg-card ${success ? 'success' : 'error'}">
            <div class="msg-text">${message}</div>
            <button onclick="closeMessage()">OK</button>
        </div>
    `;

    document.body.appendChild(box);
}

function closeMessage() {
    const box = document.getElementById("msgBox");
    if (box) box.remove();
}


/* ================= DROPDOWN ================= */
function toggleDropdown(el, event) {

    if (event) event.stopPropagation();

    const parent = el.closest(".dropdown");
    const menu   = parent.querySelector(".dropdown-content");

    document.querySelectorAll(".dropdown").forEach(d => {
        if (d !== parent) d.classList.remove("active");
    });

    document.querySelectorAll(".dropdown-content").forEach(m => {
        if (m !== menu) m.style.display = "none";
    });

    if (parent.classList.contains("active")) {
        parent.classList.remove("active");
        menu.style.display = "none";
        return;
    }

    parent.classList.add("active");

    const rect = el.getBoundingClientRect();

    menu.style.display  = "block";
    menu.style.position = "fixed";
    menu.style.left     = rect.left + "px";
    menu.style.top      = (rect.bottom + 6) + "px";
    menu.style.zIndex   = "999999";

    const menuRect = menu.getBoundingClientRect();

    if (menuRect.right > window.innerWidth) {
        menu.style.left = (window.innerWidth - menuRect.width - 10) + "px";
    }

    if (menuRect.bottom > window.innerHeight) {
        menu.style.top = "auto";
        menu.style.bottom = (window.innerHeight - rect.top + 6) + "px";
    } else {
        menu.style.bottom = "auto";
    }
}


/* ================= CLOSE DROPDOWN ================= */
document.addEventListener("click", function(e) {

    if (!e.target.closest(".dropdown")) {

        document.querySelectorAll(".dropdown").forEach(d => {
            d.classList.remove("active");
        });

        document.querySelectorAll(".dropdown-content").forEach(menu => {
            menu.style.display = "none";
        });
    }

});


/* ================= MODAL ================= */
function openGradeModal(studentId, year) {

    const sem = getCurrentSem();

    document.querySelectorAll(".dropdown").forEach(d => {
        d.classList.remove("active");
    });

    document.querySelectorAll(".dropdown-content").forEach(menu => {
        menu.style.display = "none";
    });

    const modal = document.getElementById("gradeModal");
    const title = document.getElementById("modalTitle");
    const body  = document.getElementById("modalContent");

    modal.style.display = "flex";
    body.innerHTML = "Loading...";

    title.innerHTML =
        "<span style='color:#2c5aa0;font-weight:700;'>Year " + year + " - " +
        (sem === 1 ? "1st Semester" :
        sem === 2 ? "2nd Semester" :
        "Intersemester") +
        "</span>";

    fetch(
        "fetch_grades.php?student_id=" +
        studentId +
        "&year=" +
        year +
        "&sem=" +
        sem
    )
    .then(res => res.text())
    .then(data => {
        body.innerHTML = data;
    })
    .catch(() => {
        body.innerHTML = "<div style='padding:15px;color:#e74c3c;'>Failed to load grades.</div>";
    });

}

function closeGradeModal() {
    document.getElementById("gradeModal").style.display = "none";
}


/* ================= CLOSE MODAL ================= */
document.addEventListener("click", function(e) {

    const modal = document.getElementById("gradeModal");

    if (e.target === modal) {
        closeGradeModal();
    }

});

document.addEventListener("keydown", function(e) {

    if (e.key === "Escape") {
        closeGradeModal();
    }

});


/* ================= SELECT ALL ================= */
document.addEventListener("DOMContentLoaded", function () {

    const selectAll = document.getElementById("selectAll");

    if (selectAll) {

        selectAll.addEventListener("change", function () {

            const checked = this.checked;

            document.querySelectorAll(".row-check").forEach(cb => {

                const row = cb.closest("tr");

                if (row.style.display !== "none") {
                    cb.checked = checked;
                    toggleRow(cb);
                }

            });

        });

    }

    document.querySelectorAll(".row-check").forEach(cb => {

        cb.addEventListener("change", function () {
            toggleRow(this);
            syncSelectAll();
        });

    });

});


function toggleRow(cb) {

    const row = cb.closest("tr");
    row.style.background = cb.checked ? "#eef6ff" : "";

}


function syncSelectAll() {

    const all = [...document.querySelectorAll(".row-check")].filter(cb => {
        return cb.closest("tr").style.display !== "none";
    });

    const checked = all.filter(cb => cb.checked);

    const selectAll = document.getElementById("selectAll");

    if (!selectAll) return;

    selectAll.checked = all.length > 0 && all.length === checked.length;

}


/* ================= CONFIRM ================= */
function confirmSelected() {

    const sem = getCurrentSem();

    const selected = [];

    document.querySelectorAll(".row-check:checked").forEach(cb => {

        const row = cb.closest("tr");

        if (row.style.display !== "none") {
            selected.push(cb.value);
        }

    });

    if (selected.length === 0) {
        showMessage("No students selected.", false);
        return;
    }

    const form = document.createElement("form");
    form.method = "POST";
    form.action = "";

    const actionInput = document.createElement("input");
    actionInput.type = "hidden";
    actionInput.name = "confirm_selected";
    actionInput.value = "1";
    form.appendChild(actionInput);

    const semInput = document.createElement("input");
    semInput.type = "hidden";
    semInput.name = "sem";
    semInput.value = sem;
    form.appendChild(semInput);

    selected.forEach(id => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "students[]";
        input.value = id;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

</script>

</body>
</html>