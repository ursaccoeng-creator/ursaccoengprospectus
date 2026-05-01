<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$id = (int) $_GET['id'];

/* ================= STUDENT ================= */

$query = mysqli_query($conn, "
    SELECT
        s.*,
        c.course_name
    FROM students s
    LEFT JOIN courses c
        ON c.id = s.course_id
    WHERE s.id = '$id'
    LIMIT 1
");

$student = mysqli_fetch_assoc($query);

if (!$student) {
    die("Student not found.");
}

$student_db_id = (int)$student['id'];
$course_id     = (int)$student['course_id'];

/* ================= CURRICULUM =================
Priority:
1. student's saved curriculum_id
2. active curriculum of course
================================================ */

$curriculum_id = 0;

if (!empty($student['curriculum_id'])) {

    $curriculum_id = (int)$student['curriculum_id'];

} else {

    $cur = mysqli_query($conn, "
        SELECT id
        FROM curricula
        WHERE course_id = '$course_id'
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

/* ================= PHOTO ================= */

$profile_img = "../img/default.png";

if (!empty($student['profile_image'])) {

    if (file_exists("../uploads/" . $student['profile_image'])) {
        $profile_img = "../uploads/" . $student['profile_image'];
    }
}

/* ================= PASS CHECK ================= */

function isPassed($grade)
{
    if ($grade === null || $grade === '') {
        return false;
    }

    $grade = strtoupper(trim($grade));

    if ($grade == 'INC' || $grade == 'DROP') {
        return false;
    }

    if (is_numeric($grade)) {
        return ((float)$grade <= 3.00);
    }

    return false;
}

/* ================= GRADE MAP ================= */

$gradeMap = [];

$getGrades = mysqli_query($conn, "
    SELECT subject_code, grade
    FROM student_subject_history
    WHERE student_id = '$student_db_id'
");

while ($g = mysqli_fetch_assoc($getGrades)) {
    $gradeMap[trim($g['subject_code'])] = $g['grade'];
}

/* ================= BUILD ALL SUGGESTED SUBJECTS ================= */
/* CURRENT TABLE shows NEXT SEM eligible subjects */

$suggestedSubjects = [];

for ($y = 1; $y <= 4; $y++) {

    for ($s = 1; $s <= 2; $s++) {

        /* current semester must have grade first */
        $chk = mysqli_query($conn, "
            SELECT 1
            FROM curriculum_subjects ss
            INNER JOIN student_subject_history h
                ON h.subject_code = ss.subject_code
                AND h.student_id = '$student_db_id'
            WHERE ss.curriculum_id = '$curriculum_id'
            AND CAST(ss.year_level AS UNSIGNED) = '$y'
            AND CAST(ss.semester AS UNSIGNED) = '$s'
            AND h.grade IS NOT NULL
            AND h.grade <> ''
            LIMIT 1
        ");

        if (mysqli_num_rows($chk) == 0) {
            continue;
        }

        /* determine next semester */
        if ($s == 1) {
            $nextYear = $y;
            $nextSem  = 2;
        } else {
            $nextYear = $y + 1;
            $nextSem  = 1;
        }

        if ($nextYear > 4) {
            continue;
        }

        /* get next semester subjects */
        $nextSubs = mysqli_query($conn, "
            SELECT *
            FROM curriculum_subjects
            WHERE curriculum_id = '$curriculum_id'
            AND CAST(year_level AS UNSIGNED) = '$nextYear'
            AND CAST(semester AS UNSIGNED) = '$nextSem'
            ORDER BY subject_code ASC
        ");

        while ($sub = mysqli_fetch_assoc($nextSubs)) {

            $allow = true;
            $coreqList = [];

            /* prerequisite + coreq check */
            $pre = mysqli_query($conn, "
                SELECT
                    ps.subject_code,
                    sp.is_coreq
                FROM subject_prerequisites sp
                INNER JOIN subjects ps
                    ON ps.id = sp.prereq_id
                WHERE sp.subject_id = '{$sub['subject_id']}'
            ");

            while ($p = mysqli_fetch_assoc($pre)) {

                $req = trim($p['subject_code']);
                $is_coreq = (int)$p['is_coreq'];

                /* NORMAL PRE-REQ ONLY */
                if ($is_coreq == 0) {

                    if (
                        !isset($gradeMap[$req]) ||
                        !isPassed($gradeMap[$req])
                    ) {
                        $allow = false;
                        break;
                    }

                } else {

                    /* CO-REQ DISPLAY ONLY */
                    $coreqList[] = $req;
                }
            }

            if ($allow) {

                $sub['coreq_list'] = $coreqList;

                /* store in current semester table */
                $suggestedSubjects[$y][$s][] = $sub;
            }
        }
    }
}

/* ================= SUBJECTS ================= */

$subjects = mysqli_query($conn, "
    SELECT
        s.*,
        h.grade
    FROM curriculum_subjects s
    LEFT JOIN student_subject_history h
        ON h.subject_code = s.subject_code
        AND h.student_id = '$student_db_id'
    WHERE s.curriculum_id = '$curriculum_id'
    ORDER BY
        CAST(s.year_level AS UNSIGNED),
        CAST(s.semester AS UNSIGNED),
        s.subject_code ASC
");

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Admin Print Form</title>

<style>

body{
    margin:0;
    font-family:Arial, Helvetica, sans-serif;
    background:#f4f7fc;
}

*{
    box-sizing:border-box;
}

.content{
    margin-left:265px;
    padding:30px;
}

.top-bar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.btn{
    display:inline-block;
    padding:10px 18px;
    background:#2c5aa0;
    color:#fff;
    text-decoration:none;
    border:none;
    border-radius:8px;
    font-size:14px;
    cursor:pointer;
}

.paper{
    background:#fff;
    padding:30px;
    max-width:1100px;
    margin:0 auto;
    border-radius:10px;
    box-shadow:0 4px 14px rgba(0,0,0,.06);
}

.header{
    position:relative;
    text-align:center;
    margin-bottom:20px;
}

.student-photo-box{
    position:absolute;
    top:0;
    right:0;
    width:110px;
    height:110px;
}

.student-photo{
    width:100%;
    height:100%;
    object-fit:cover;
}

.header-text{
    padding-right:130px;
}

.header h2,
.header h3,
.header p{
    margin:0;
}

.info-table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:20px;
}

.info-table td{
    border:none;
    padding:6px 0;
    font-size:14px;
}

.fill-line{
    display:block;
    border-bottom:1px solid #000;
    min-height:22px;
}

.sem{
    text-align:center;
    font-size:22px;
    font-weight:700;
    margin:20px 0 8px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:18px;
}

th,
td{
    border:1px solid #000;
    padding:6px;
    font-size:13px;
}

th{
    background:#f3f3f3;
}

.center{
    text-align:center;
}

.total{
    font-weight:700;
}

@media(max-width:768px){

    .content{
        margin-left:0;
        padding:15px;
        padding-top:90px;
    }

    .btn{
        width:100%;
        text-align:center;
    }

}

</style>

</head>
<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="top-bar">

        <a href="student_print_form.php" class="btn">
            Back
        </a>

        <button
            type="button"
            class="btn"
            onclick="downloadPDF()"
        >
            Download PDF
        </button>

    </div>

    <div id="printArea">

        <div class="paper">

            <div class="header">

                <div class="student-photo-box">
                    <img
                        src="<?php echo $profile_img; ?>"
                        class="student-photo"
                    >
                </div>

                <div class="header-text">

                    <h2>UNIVERSITY OF RIZAL SYSTEM</h2>

                    <p>Province of Rizal</p>

                    <h3>
                        <?php echo strtoupper($student['course_name']); ?>
                    </h3>

                    <p>Official Student Prospectus</p>

                    <!-- CURRICULUM -->
                    <p style="margin-top:6px;font-weight:600;">

                        <?php

                        $cur_name = "No Curriculum";

                        $cur_q = mysqli_query($conn, "
                            SELECT curriculum_name
                            FROM curricula
                            WHERE id = '$curriculum_id'
                            LIMIT 1
                        ");

                        if ($cur_r = mysqli_fetch_assoc($cur_q)) {
                            $cur_name = $cur_r['curriculum_name'];
                        }

                        echo htmlspecialchars($cur_name);

                        ?>

                    </p>

                </div>

            </div>

            <table class="info-table">

                <tr>

                    <td width="100">Name:</td>

                    <td>
                        <span class="fill-line">
                            <?php echo $student['full_name']; ?>
                        </span>
                    </td>

                    <td width="30"></td>

                    <td width="100">Student No:</td>

                    <td>
                        <span class="fill-line">
                            <?php echo $student['student_id']; ?>
                        </span>
                    </td>

                </tr>

                <tr>

                    <td>Course:</td>

                    <td>
                        <span class="fill-line">
                            <?php echo $student['course_name']; ?>
                        </span>
                    </td>

                    <td></td>

                    <td>Section:</td>

                    <td>
                        <span class="fill-line">
                            <?php echo $student['section']; ?>
                        </span>
                    </td>

                </tr>

            </table>

<?php

$currentY = -1;
$currentS = -1;
$total    = 0;

while ($row = mysqli_fetch_assoc($subjects)) {

    $rowY = (int)$row['year_level'];
    $rowS = (int)$row['semester'];

    /* NEW SEMESTER TABLE */
    if ($currentY !== $rowY || $currentS !== $rowS) {

        /* CLOSE PREVIOUS */
        if ($currentY != -1) {

            echo "
            <tr class='total'>
                <td colspan='2' class='center'>TOTAL</td>
                <td class='center'>{$total} units</td>
                <td></td>
                <td></td>
            </tr>
            </table>";
        }

        $currentY = $rowY;
        $currentS = $rowS;
        $total    = 0;

        /* YEAR LABEL */
        if ($currentY == 1) {
            $yearLabel = "1st Year";
        } elseif ($currentY == 2) {
            $yearLabel = "2nd Year";
        } elseif ($currentY == 3) {
            $yearLabel = "3rd Year";
        } elseif ($currentY == 4) {
            $yearLabel = "4th Year";
        } else {
            $yearLabel = $currentY . "th Year";
        }

        /* SEM LABEL */
        if ($currentS == 1) {
            $semLabel = "1st Semester";
        } elseif ($currentS == 2) {
            $semLabel = "2nd Semester";
        } else {
            $semLabel = "Intersemester";
        }

        echo "
        <div class='sem'>
            {$yearLabel} - {$semLabel}
        </div>

        <table>
            <tr>
                <th style='width:140px;'>Code</th>
                <th>Description</th>
                <th style='width:90px;'>Units</th>
                <th style='width:120px;'>Grades</th>
                <th style='width:220px;'>Suggested</th>
            </tr>";
    }

    /* GRADE FORMAT */
    $grade = $row['grade'];

    if (
        $grade !== '' &&
        $grade !== null &&
        is_numeric($grade)
    ) {
        $grade = number_format((float)$grade, 2);
    }

    /* SUGGESTED SUBJECTS */
    $suggest = '';

    if (
        isset($suggestedSubjects[$currentY][$currentS]) &&
        count($suggestedSubjects[$currentY][$currentS]) > 0
    ) {

        $sg = array_shift(
            $suggestedSubjects[$currentY][$currentS]
        );

        $suggest = "☐ " . $sg['subject_code'];

        /* SHOW COREQ */
        if (
            isset($sg['coreq_list']) &&
            !empty($sg['coreq_list'])
        ) {

            $suggest .=
                "<br><small>(Co-Req: " .
                implode(', ', $sg['coreq_list']) .
                ")</small>";
        }
    }

    echo "
    <tr>
        <td class='center'>{$row['subject_code']}</td>
        <td>{$row['subject_title']}</td>
        <td class='center'>{$row['units']}</td>
        <td class='center'>{$grade}</td>
        <td>{$suggest}</td>
    </tr>";

    $total += (int)$row['units'];
}

/* CLOSE LAST TABLE */
if ($currentY != -1) {

    echo "
    <tr class='total'>
        <td colspan='2' class='center'>TOTAL</td>
        <td class='center'>{$total} units</td>
        <td></td>
        <td></td>
    </tr>
    </table>";
}

?>

        </div>

    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>

async function downloadPDF()
{
    const { jsPDF } = window.jspdf;

    const area =
        document.getElementById("printArea");

    const canvas =
        await html2canvas(area, {
            scale: 3,
            useCORS: true,
            backgroundColor: "#ffffff",
            scrollY: -window.scrollY
        });

    const imgData =
        canvas.toDataURL("image/jpeg", 1.0);

    const pdf =
        new jsPDF("p", "mm", "a4");

    const pageWidth  = 210;
    const pageHeight = 297;
    const margin     = 10;

    const usableWidth =
        pageWidth - (margin * 2);

    const usableHeight =
        pageHeight - (margin * 2);

    const imgWidth =
        usableWidth;

    const imgHeight =
        (canvas.height * imgWidth) / canvas.width;

    let heightLeft =
        imgHeight;

    let position =
        margin;

    pdf.addImage(
        imgData,
        "JPEG",
        margin,
        position,
        imgWidth,
        imgHeight
    );

    heightLeft -= usableHeight;

    while (heightLeft > 0) {

        position =
            heightLeft - imgHeight + margin;

        pdf.addPage();

        pdf.addImage(
            imgData,
            "JPEG",
            margin,
            position,
            imgWidth,
            imgHeight
        );

        heightLeft -= usableHeight;
    }

    pdf.save(
        "Student_Prospectus_<?php echo $student['student_id']; ?>.pdf"
    );
}

</script>

</body>
</html>