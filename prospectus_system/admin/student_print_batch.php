<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['ids']) || trim($_GET['ids']) == '') {
    die("No students selected.");
}

/* ================= IDS ================= */

$raw_ids = explode(",", $_GET['ids']);
$ids = [];

foreach ($raw_ids as $id) {
    $ids[] = (int)$id;
}

$id_list = implode(",", $ids);

$students = mysqli_query($conn, "
    SELECT 
        s.*,
        c.course_name
    FROM students s
    LEFT JOIN courses c
        ON c.id = s.course_id
    WHERE s.id IN ($id_list)
    ORDER BY s.full_name ASC
");

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

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
>

<meta
    name="format-detection"
    content="telephone=no"
>

<title>Batch Print Form</title>

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

.content{
    margin-left:265px;
    padding:30px;
    padding-right:25px;
    padding-top:40px;
}

/* TOP BAR */

.top-bar{
    display:flex;
    gap:10px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.btn{
    padding:10px 18px;
    background:#2c5aa0;
    color:#fff;
    border:none;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
    text-decoration:none;
    cursor:pointer;
    text-align:center;
    transition:.2s;
}

.btn:hover{
    background:#1f4580;
}

/* PAPER */

.paper{
    background:#fff;
    padding:30px;
    margin:0 auto 25px;
    max-width:1100px;
    border-radius:12px;
    box-shadow:0 4px 14px rgba(0,0,0,.06);
    page-break-after:always;
    overflow:hidden;
}

/* HEADER */

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
    overflow:hidden;
    border:1px solid #ddd;
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

.header h2{
    font-size:34px;
    line-height:1.15;
}

.header h3{
    font-size:24px;
    margin-top:6px;
}

.header p{
    margin-top:3px;
    font-size:14px;
}

/* INFO TABLE */

.info-table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:18px;
}

.info-table td{
    border:none;
    padding:6px 0;
    font-size:14px;
    vertical-align:top;
}

.fill-line{
    display:block;
    border-bottom:1px solid #000;
    min-height:22px;
}

/* SEMESTER */

.sem{
    text-align:center;
    font-size:20px;
    font-weight:700;
    margin:18px 0 8px;
}

/* TABLE */

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
    font-weight:700;
}

.center{
    text-align:center;
}

.total{
    font-weight:700;
}

/* TABLET */

@media (max-width:1024px){

    .content{
        margin-left:0;
        padding:20px;
        padding-top:88px;
    }

    .paper{
        max-width:100%;
    }

}

/* MOBILE FIX */

@media (max-width:768px){

    html,
    body{
        overflow-x:hidden;
        background:#f4f7fc;
    }

    .content{
        margin-left:0 !important;
        padding:85px 12px 20px !important;
        width:100%;
    }

    .top-bar{
        flex-direction:column;
        gap:10px;
        margin-bottom:15px;
    }

    .btn{
        width:100%;
        padding:12px;
        font-size:14px;
    }

    .paper{
        width:100% !important;
        max-width:100% !important;
        margin:0 0 18px 0 !important;
        padding:15px !important;
        border-radius:10px;
        box-shadow:0 3px 10px rgba(0,0,0,.06);
    }

    .header{
        text-align:center;
        margin-bottom:15px;
    }

    .student-photo-box{
        position:relative !important;
        top:auto !important;
        right:auto !important;
        width:85px !important;
        height:85px !important;
        margin:0 auto 10px;
    }

    .header-text{
        padding-right:0 !important;
    }

    .header h2{
        font-size:22px;
        line-height:1.15;
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
        font-size:13px;
    }

    .fill-line{
        min-height:20px;
    }

    .sem{
        font-size:17px;
        margin:16px 0 8px;
    }

    table{
        display:block;
        width:100%;
        overflow-x:auto;
        white-space:nowrap;
        margin-bottom:15px;
        -webkit-overflow-scrolling:touch;
    }

    th,
    td{
        font-size:12px;
        padding:6px;
    }

}

/* SMALL MOBILE */

@media (max-width:480px){

    .content{
        padding:82px 10px 18px !important;
    }

    .paper{
        padding:12px !important;
    }

    .header h2{
        font-size:18px;
    }

    .header h3{
        font-size:14px;
    }

    .sem{
        font-size:15px;
    }

}

/* PRINT */

@media print{

    body{
        background:#fff;
    }

    .top-bar,
    .sidebar,
    .mobile-header,
    .burger,
    .overlay{
        display:none !important;
    }

    .content{
        margin:0 !important;
        padding:0 !important;
    }

    .paper{
        max-width:100%;
        width:100%;
        margin:0 0 20px 0;
        border-radius:0;
        box-shadow:none;
        page-break-after:always;
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

<?php while ($student = mysqli_fetch_assoc($students)) { ?>

<?php

$student_db_id = (int)$student['id'];
$course_id     = (int)$student['course_id'];

/* PHOTO */

$profile_img = "../img/default.png";

if (!empty($student['profile_image'])) {

    if (file_exists("../uploads/" . $student['profile_image'])) {
        $profile_img = "../uploads/" . $student['profile_image'];
    }
}

/* GRADE MAP */

$gradeMap = [];

$getGrades = mysqli_query($conn, "
    SELECT subject_code, grade
    FROM student_subject_history
    WHERE student_id = '$student_db_id'
");

while ($g = mysqli_fetch_assoc($getGrades)) {
    $gradeMap[$g['subject_code']] = $g['grade'];
}

/* NEXT TERM */

$targetYear = 1;
$targetSem  = 1;

for ($y=1; $y<=6; $y++) {

    for ($s=1; $s<=2; $s++) {

        $chk = mysqli_query($conn, "
            SELECT s.subject_code
            FROM subjects s
            LEFT JOIN student_subject_history h
                ON h.subject_code = s.subject_code
                AND h.student_id = '$student_db_id'
            WHERE s.course_id = '$course_id'
            AND CAST(s.year_level AS UNSIGNED) = '$y'
            AND CAST(s.semester AS UNSIGNED) = '$s'
            AND h.grade IS NOT NULL
            AND h.grade <> ''
            LIMIT 1
        ");

        if (mysqli_num_rows($chk) > 0) {

            if ($s == 1) {
                $targetYear = $y;
                $targetSem  = 2;
            } else {
                $targetYear = $y + 1;
                $targetSem  = 1;
            }
        }
    }
}

/* SUGGESTED */

$suggestedSubjects = [];

$nextSubs = mysqli_query($conn, "
    SELECT *
    FROM subjects
    WHERE course_id = '$course_id'
    AND CAST(year_level AS UNSIGNED) = '$targetYear'
    AND CAST(semester AS UNSIGNED) = '$targetSem'
    ORDER BY subject_code ASC
");

while ($sub = mysqli_fetch_assoc($nextSubs)) {

    $allow = true;

    $pre = mysqli_query($conn, "
        SELECT ps.subject_code
        FROM subject_prerequisites sp
        INNER JOIN subjects ps
            ON ps.id = sp.prereq_id
        WHERE sp.subject_id = '{$sub['id']}'
    ");

    while ($p = mysqli_fetch_assoc($pre)) {

        $req = $p['subject_code'];

        if (
            !isset($gradeMap[$req]) ||
            !isPassed($gradeMap[$req])
        ) {
            $allow = false;
            break;
        }
    }

    if ($allow) {
        $suggestedSubjects[] = $sub;
    }
}

/* SUBJECTS */

$subjects = mysqli_query($conn, "
    SELECT s.*, h.grade
    FROM subjects s
    LEFT JOIN student_subject_history h
        ON h.subject_code = s.subject_code
        AND h.student_id = '$student_db_id'
    WHERE s.course_id = '$course_id'
    ORDER BY
        CAST(s.year_level AS UNSIGNED),
        CAST(s.semester AS UNSIGNED),
        s.subject_code ASC
");

?>

<div class="paper">

    <div class="header">

        <div class="student-photo-box">
            <img src="<?php echo $profile_img; ?>" class="student-photo">
        </div>

        <div class="header-text">
            <h2>UNIVERSITY OF RIZAL SYSTEM</h2>
            <p>Province of Rizal</p>
            <h3><?php echo strtoupper($student['course_name']); ?></h3>
            <p>Official Student Prospectus</p>
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

$currentY = '';
$currentS = '';
$total    = 0;

while ($row = mysqli_fetch_assoc($subjects)) {

    if (
        $currentY != $row['year_level'] ||
        $currentS != $row['semester']
    ) {

        if ($currentY !== '') {

            echo "
            <tr class='total'>
                <td colspan='2' class='center'>TOTAL</td>
                <td class='center'>{$total} units</td>
                <td></td>
                <td></td>
            </tr>
            </table>";
        }

        $currentY = $row['year_level'];
        $currentS = $row['semester'];
        $total    = 0;

        $semLabel = ($currentS == 1)
            ? '1st Semester'
            : '2nd Semester';

        echo "
        <div class='sem'>
            Year {$currentY} - {$semLabel}
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

    $grade = $row['grade'];

    if (
        $grade !== '' &&
        $grade !== null &&
        is_numeric($grade)
    ) {
        $grade = number_format((float)$grade, 2);
    }

    $suggest = '';

    if (!empty($suggestedSubjects)) {
        $sg = array_shift($suggestedSubjects);
        $suggest = "☐ " . $sg['subject_code'];
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

if ($currentY !== '') {

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

<?php } ?>

</div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>

async function downloadPDF()
{
    const { jsPDF } = window.jspdf;

    const papers =
        document.querySelectorAll("#printArea .paper");

    const pdf =
        new jsPDF("p","mm","a4");

    const pageWidth  = 210;
    const pageHeight = 297;
    const margin     = 10;

    const usableWidth  = pageWidth - (margin * 2);
    const usableHeight = pageHeight - (margin * 2);

    let isFirstPdfPage = true;

    for(let i = 0; i < papers.length; i++)
    {
        const paper = papers[i];

        /* ===============================
           CREATE DESKTOP CLONE
        =============================== */
        const clone =
            paper.cloneNode(true);

        clone.style.position = "fixed";
        clone.style.left = "-99999px";
        clone.style.top = "0";
        clone.style.width = "1100px";
        clone.style.maxWidth = "1100px";
        clone.style.padding = "30px";
        clone.style.margin = "0";
        clone.style.background = "#ffffff";
        clone.style.boxShadow = "none";
        clone.style.borderRadius = "0";
        clone.style.zIndex = "-1";

        document.body.appendChild(clone);

        await new Promise(resolve => setTimeout(resolve,200));

        const canvas =
            await html2canvas(clone,{
                scale:3,
                useCORS:true,
                backgroundColor:"#ffffff",
                scrollX:0,
                scrollY:0,
                windowWidth:1400
            });

        clone.remove();

        const imgWidth = usableWidth;

        const pxPerMm =
            canvas.width / imgWidth;

        const pagePxHeight =
            Math.floor(
                usableHeight * pxPerMm
            );

        const pageCanvas =
            document.createElement("canvas");

        const pageCtx =
            pageCanvas.getContext("2d");

        pageCanvas.width =
            canvas.width;

        let startY = 0;
        let isFirstStudentPage = true;

        while(startY < canvas.height)
        {
            let endY =
                startY + pagePxHeight;

            /* ===============================
               FIND WHITE SPACE CUT
            =============================== */
            if(endY < canvas.height)
            {
                for(
                    let scan = endY;
                    scan > endY - 240;
                    scan--
                ){
                    let whiteLine = true;

                    for(
                        let x = 0;
                        x < canvas.width;
                        x += 25
                    ){
                        const px =
                            canvas
                            .getContext("2d")
                            .getImageData(
                                x, scan, 1, 1
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

            const sliceHeight =
                endY - startY;

            pageCanvas.height =
                sliceHeight;

            pageCtx.setTransform(
                1,0,0,1,0,0
            );

            pageCtx.clearRect(
                0,0,
                pageCanvas.width,
                pageCanvas.height
            );

            pageCtx.fillStyle =
                "#ffffff";

            pageCtx.fillRect(
                0,0,
                pageCanvas.width,
                pageCanvas.height
            );

            pageCtx.drawImage(
                canvas,
                0,startY,
                canvas.width,
                sliceHeight,
                0,0,
                canvas.width,
                sliceHeight
            );

            const imgData =
                pageCanvas.toDataURL(
                    "image/png"
                );

            const pageImgHeight =
                sliceHeight / pxPerMm;

            /* ===============================
               ADD NEW PAGE ONLY WHEN NEEDED
            =============================== */
            if(!isFirstPdfPage){
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

            isFirstPdfPage = false;
            isFirstStudentPage = false;

            startY = endY;
        }

        /* ===============================
           NEXT STUDENT STARTS NEW PAGE
        =============================== */
        if(i < papers.length - 1){
            pdf.addPage();
            isFirstPdfPage = true;
        }
    }

    pdf.save(
        "Batch_Student_Prospectus.pdf"
    );
}

</script>

</body>
</html>