<?php
session_start();
include "includes/db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location:index.php");
    exit();
}

$student_id = mysqli_real_escape_string($conn, $_SESSION['student_id']);

/* ================= STUDENT ================= */

$query = mysqli_query($conn,"
    SELECT 
        s.*,
        c.course_name
    FROM students s
    LEFT JOIN courses c
        ON c.id = s.course_id
    WHERE s.student_id = '$student_id'
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
2. active curriculum by course
================================================ */

$curriculum_id = 0;

if (!empty($student['curriculum_id'])) {

    $curriculum_id = (int)$student['curriculum_id'];

} else {

    $cur = mysqli_query($conn,"
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

/* ================= STUDENT PHOTO ================= */

$profile_img = "img/default.png";

if (!empty($student['profile_image'])) {

    if (file_exists("uploads/" . $student['profile_image'])) {
        $profile_img = "uploads/" . $student['profile_image'];
    } else {
        $profile_img = "img/default.png";
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

$getGrades = mysqli_query($conn,"
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

        /* current sem must have grades */
        $chk = mysqli_query($conn,"
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

        /* determine NEXT semester */
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
        $nextSubs = mysqli_query($conn,"
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
            $pre = mysqli_query($conn,"
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

                /* NORMAL PRE-REQ */
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

                /* store in CURRENT semester table */
                $suggestedSubjects[$y][$s][] = $sub;
            }
        }
    }
}

/* ================= ALL SUBJECTS ================= */

$subjects = mysqli_query($conn,"
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

<title>Print Form</title>

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
    color:#111;
}

/* ================= CONTENT ================= */

.content{
    margin-left:265px;
    padding:30px;
}

/* ================= TOP BAR ================= */

.top-bar{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
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
    text-align:center;
}

.btn:hover{
    opacity:.92;
}

/* ================= PAPER ================= */

.paper-wrap{
    width:100%;
    overflow:hidden;
}

.paper{
    background:#fff;
    padding:30px;
    max-width:1100px;
    margin:0 auto;
    box-shadow:0 4px 14px rgba(0,0,0,.06);
    border-radius:10px;
}

/* ================= HEADER ================= */

.header{
    position:relative;
    text-align:center;
    margin-bottom:20px;
    padding-top:10px;
}

.student-photo-box{
    position:absolute;
    top:0;
    right:0;
    width:110px;
    height:110px;
    overflow:hidden;
    background:#fff;
}

.student-photo{
    width:100%;
    height:100%;
    object-fit:cover;
}

.header-text{
    padding-right:135px;
}

.header h2,
.header h3,
.header p{
    margin:0;
}

.header h2{
    font-size:34px;
}

.header h3{
    margin-top:8px;
    font-size:26px;
}

/* ================= INFO ================= */

.info{
    width:100%;
    margin-bottom:22px;
}

.info-table{
    width:100%;
    border-collapse:collapse;
}

.info-table td{
    border:none !important;
    padding:7px 0;
    font-size:14px;
    vertical-align:top;
}

.label{
    width:110px;
    font-weight:700;
}

.fill-line{
    display:block;
    border-bottom:1px solid #000;
    min-height:22px;
    padding-bottom:2px;
}

/* ================= SEM ================= */

.sem{
    margin-top:24px;
    margin-bottom:8px;
    text-align:center;
    font-size:22px;
    font-weight:700;
}

/* ================= TABLE ================= */

table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:20px;
    table-layout:fixed;
}

th,
td{
    border:1px solid #000;
    padding:6px 8px;
    font-size:13px !important;
    line-height:1.35 !important;
    font-family:Arial, Helvetica, sans-serif !important;
    word-wrap:break-word;
}

th{
    background:#f3f3f3;
    text-align:center;
    font-weight:700;
}

.center{
    text-align:center;
}

.right{
    text-align:right;
}

.total{
    font-weight:700;
}

/* ================= SIGN ================= */

.sign{
    margin-top:45px;
    display:flex;
    justify-content:space-between;
    gap:30px;
}

.sigbox{
    width:45%;
    text-align:center;
    font-size:14px;
}

.sigline{
    height:38px;
    border-bottom:1px solid #000;
    margin-bottom:6px;
}

/* ================= MOBILE ================= */

@media (max-width:768px){

    html,
    body{
        overflow-x:hidden;
        overflow-y:auto;
        background:#f4f7fc;
    }

    /* PAGE BELOW SIDENAV */
    .content{
        margin-left:0 !important;
        margin-right:0 !important;
        width:100%;
        padding:75px 10px 20px !important;
        position:relative;
        z-index:1 !important;
    }

    /* SIDENAV MUST STAY ON TOP */
    .sidebar{
        position:fixed !important;
        top:75px !important;
        left:-260px;
        height:calc(100vh - 90px);
        max-height:calc(100vh - 90px);
        z-index:9999 !important;
    }

    .sidebar.active{
        left:15px !important;
    }

    .overlay{
        z-index:9998 !important;
    }

    .top-bar{
        flex-direction:column;
        gap:8px;
        margin-bottom:12px;
    }

    .btn{
        width:100%;
        padding:12px;
        font-size:14px;
    }

    .paper-wrap{
        width:100%;
        overflow:visible;
    }

    .paper{
        width:100%;
        max-width:100%;
        padding:15px;
        margin:0 auto;
        box-shadow:0 3px 10px rgba(0,0,0,.05);
        border-radius:10px;
    }

    .header{
        text-align:center;
        padding-top:0;
    }

    .student-photo-box{
        position:relative;
        width:90px;
        height:90px;
        top:auto;
        right:auto;
        margin:0 auto 12px;
    }

    .header-text{
        padding-right:0;
    }

    .header h2{
        font-size:20px;
        line-height:1.25;
    }

    .header h3{
        font-size:16px;
        margin-top:5px;
    }

    .header p{
        font-size:12px;
    }

    .info-table,
    .info-table tbody,
    .info-table tr,
    .info-table td{
        display:block;
        width:100%;
    }

    .info-table td{
        padding:4px 0;
    }

    .label{
        width:100%;
    }

    table{
        display:block;
        width:100%;
        overflow-x:auto;
        white-space:nowrap;
    }

    th,
    td{
        font-size:12px !important;
        padding:6px;
    }

    .sem{
        font-size:16px;
        margin-top:18px;
    }

    .sign{
        flex-direction:column;
        gap:20px;
        margin-top:30px;
    }

    .sigbox{
        width:100%;
    }

}

/* ================= PRINT ================= */

@media print{

    .top-bar,
    .sidebar,
    .mobile-header,
    .burger,
    .overlay{
        display:none !important;
    }

    body{
        background:#fff;
    }

    .content{
        margin:0;
        padding:0;
    }

    .paper{
        width:100%;
        max-width:100%;
        box-shadow:none;
        border-radius:0;
        margin:0;
    }

}

</style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="content">

    <!-- TOP BAR -->
    <div class="top-bar">

        <a href="dashboard.php" class="btn">
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

    <!-- PRINT AREA -->
    <div id="printArea">

        <div class="paper-wrap">
        <div class="paper">

            <!-- HEADER -->
            <div class="header">

                <div class="header-wrap">

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
                    </div>

                </div>

            </div>

            <!-- STUDENT INFO -->
            <div class="info">

                <table class="info-table">

                    <tr>

                        <td class="label">Name:</td>

                        <td>
                            <span class="fill-line">
                                <?php echo $student['full_name']; ?>
                            </span>
                        </td>

                        <td style="width:40px;"></td>

                        <td class="label">Student No:</td>

                        <td>
                            <span class="fill-line">
                                <?php echo $student['student_id']; ?>
                            </span>
                        </td>

                    </tr>

                    <tr>

                        <td class="label">Course:</td>

                        <td>
                            <span class="fill-line">
                                <?php echo $student['course_name']; ?>
                            </span>
                        </td>

                        <td></td>

                        <td class="label">Section:</td>

                        <td>
                            <span class="fill-line">
                                <?php echo $student['section']; ?>
                            </span>
                        </td>

                    </tr>

                </table>

            </div>

<?php

$currentY = -1;
$currentS = -1;
$total    = 0;

while ($row = mysqli_fetch_assoc($subjects)) {

    $rowY = (int)$row['year_level'];
    $rowS = (int)$row['semester'];

    /* OPEN NEW TABLE ONLY WHEN SEM CHANGES */
    if ($currentY !== $rowY || $currentS !== $rowS) {

        /* CLOSE PREVIOUS TABLE */
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
        <div class='sem'>{$yearLabel} - {$semLabel}</div>

        <table>
            <tr>
                <th style='width:140px;'>Code</th>
                <th>Description</th>
                <th style='width:100px;'>Units</th>
                <th style='width:130px;'>Grades</th>
                <th style='width:220px;'>Suggested</th>
            </tr>";
    }

    /* GRADE */
    $grade = $row['grade'];

    if (
        $grade !== '' &&
        $grade !== null &&
        is_numeric($grade)
    ) {
        $grade = number_format((float)$grade, 2);
    }

    /* SUGGESTED */
    $suggestCell = '';

    if (
        isset($suggestedSubjects[$currentY][$currentS]) &&
        count($suggestedSubjects[$currentY][$currentS]) > 0
    ) {

        $sg = array_shift(
            $suggestedSubjects[$currentY][$currentS]
        );

        $suggestCell = '☐ ' . $sg['subject_code'];

        /* SHOW COREQ */
        if (
            isset($sg['coreq_list']) &&
            !empty($sg['coreq_list'])
        ) {

            $suggestCell .=
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
        <td class='suggest-col'>{$suggestCell}</td>
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

            <!-- SIGNATURE -->
            <div class="sign">

                <div class="sigbox">
                    <div class="sigline"></div>
                    Student Signature
                </div>

                <div class="sigbox">
                    <div class="sigline"></div>
                    Approved By
                </div>

            </div>

        </div>
        </div>

    </div>

</div>

<!-- LIBRARIES -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>

async function downloadPDF(){

    const { jsPDF } = window.jspdf;

    const area  = document.getElementById("printArea");
    const paper = area.querySelector(".paper");
    const wrap  = area.querySelector(".paper-wrap");

    const oldPaperStyle = paper.getAttribute("style") || "";
    const oldWrapStyle  = wrap.getAttribute("style") || "";

    wrap.style.width = "100%";
    wrap.style.height = "auto";
    wrap.style.overflow = "visible";

    paper.style.width = "1100px";
    paper.style.maxWidth = "1100px";
    paper.style.transform = "none";
    paper.style.padding = "30px";
    paper.style.margin = "0 auto";
    paper.style.boxShadow = "none";
    paper.style.borderRadius = "0";

    await new Promise(resolve => setTimeout(resolve,300));

    const canvas = await html2canvas(area,{
        scale:3,
        useCORS:true,
        backgroundColor:"#ffffff",
        scrollX:0,
        scrollY:-window.scrollY,
        windowWidth:1400
    });

    paper.setAttribute("style", oldPaperStyle);
    wrap.setAttribute("style", oldWrapStyle);

    const pdf = new jsPDF("p","mm","a4");

    const pageWidth  = 210;
    const pageHeight = 297;
    const margin = 10;

    const usableWidth  = pageWidth - (margin * 2);
    const usableHeight = pageHeight - (margin * 2);

    const imgWidth = usableWidth;

    const pxPerMm = canvas.width / imgWidth;
    const pagePxHeight = Math.floor(
        usableHeight * pxPerMm
    );

    const pageCanvas =
        document.createElement("canvas");

    const pageCtx =
        pageCanvas.getContext("2d");

    pageCanvas.width = canvas.width;

    let startY = 0;
    let pageNum = 0;

    while(startY < canvas.height){

        let endY = startY + pagePxHeight;

        if(endY < canvas.height){

            for(
                let scan = endY;
                scan > endY - 220;
                scan--
            ){

                let whiteLine = true;

                for(
                    let x = 0;
                    x < canvas.width;
                    x += 25
                ){

                    const px = canvas
                        .getContext("2d")
                        .getImageData(
                            x,
                            scan,
                            1,
                            1
                        ).data;

                    if(
                        px[0] < 245 ||
                        px[1] < 245 ||
                        px[2] < 245
                    ){
                        whiteLine = false;
                        break;
                    }
                }

                if(whiteLine){
                    endY = scan;
                    break;
                }
            }
        }

        const sliceHeight = endY - startY;

        pageCanvas.height = sliceHeight;

        pageCtx.setTransform(
            1,0,0,1,0,0
        );

        pageCtx.clearRect(
            0,
            0,
            pageCanvas.width,
            pageCanvas.height
        );

        pageCtx.fillStyle = "#ffffff";

        pageCtx.fillRect(
            0,
            0,
            pageCanvas.width,
            pageCanvas.height
        );

        pageCtx.drawImage(
            canvas,
            0,startY,
            canvas.width,sliceHeight,
            0,0,
            canvas.width,sliceHeight
        );

        const imgData =
            pageCanvas.toDataURL("image/png");

        const pageImgHeight =
            sliceHeight / pxPerMm;

        if(pageNum > 0){
            pdf.addPage();
        }

        pdf.addImage(
            imgData,
            "PNG",
            margin,
            margin,
            imgWidth,
            pageImgHeight
        );

        startY = endY;
        pageNum++;
    }

    pdf.save(
        "Student_Prospectus_<?php echo $student['student_id']; ?>.pdf"
    );
}

</script>

</body>
</html>