<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

/* ================= HELPER ================= */

function getSemesterName($sem) {
    switch ($sem) {
        case 1: return "1st Semester";
        case 2: return "2nd Semester";
        case 3: return "Intersemester";
        case 4: return "Summer";
        default: return "Term " . $sem;
    }
}

/* ================= FILTER ================= */

$course_id = $_GET['course_id'] ?? '';
$year_level = $_GET['year_level'] ?? '';
$semester   = $_GET['semester'] ?? '';

$where = "WHERE 1=1";

if (!empty($course_id)) {
    $course_id = intval($course_id);
    $where .= " AND s.course_id = '$course_id'";
}

if (!empty($year_level)) {
    $year_level = mysqli_real_escape_string($conn, $year_level);
    $where .= " AND s.year_level = '$year_level'";
}

if ($semester !== '') {
    $semester = intval($semester);
    $where .= " AND s.semester = '$semester'";
}

$subjects = mysqli_query(
    $conn,
    "SELECT s.*, c.course_name
     FROM subjects s
     LEFT JOIN courses c ON c.id = s.course_id
     $where
     ORDER BY 
        CAST(s.year_level AS UNSIGNED),
        s.semester,
        s.subject_code"
);

/* ================= ADD SUBJECT ================= */

if (isset($_POST['add_subject'])) {

    $code       = trim($_POST['code']);
    $title      = trim($_POST['title']);
    $course_id  = intval($_POST['course_id']);
    $year_level = trim($_POST['year_level']);
    $semester   = intval($_POST['semester']); // now supports 1,2,3,4...
    $units      = intval($_POST['units']);

    $year_required = !empty($_POST['year_required']) 
        ? intval($_POST['year_required']) 
        : null;

    if (
        empty($code) ||
        empty($title) ||
        empty($course_id) ||
        empty($year_level) ||
        $semester === '' ||
        $units <= 0
    ) {
        header("Location: subjects.php?error=Please fill in all required fields");
        exit();
    }

    $check = mysqli_query($conn,"
        SELECT id FROM subjects
        WHERE subject_code='$code' AND course_id='$course_id'
    ");

    if(mysqli_num_rows($check) > 0){
        header("Location: subjects.php?error=Subject already exists");
        exit();
    }

    mysqli_query(
        $conn,
        "INSERT INTO subjects
        (subject_code, subject_title, course_id, year_level, semester, units)
        VALUES
        ('$code', '$title', '$course_id', '$year_level', '$semester', '$units')"
    );

    $subject_id = mysqli_insert_id($conn);

    $hasPrereq =
        (!empty($_POST['prereq']) && array_filter($_POST['prereq'])) ||
        !empty($_POST['manual_prereq']) ||
        !empty($year_required);

    if ($hasPrereq) {

        if (!empty($year_required)) {
            mysqli_query(
                $conn,
                "INSERT INTO subject_prerequisites
                (subject_id, year_required)
                VALUES
                ('$subject_id', '$year_required')"
            );
        }

        if (!empty($_POST['prereq'])) {

            $added = [];

            foreach ($_POST['prereq'] as $pr_id_raw) {

                $is_coreq = 0;

                if (strpos($pr_id_raw, 'C') === 0) {
                    $is_coreq = 1;
                    $pr_id = intval(substr($pr_id_raw, 1));
                } else {
                    $pr_id = intval($pr_id_raw);
                }

                if ($pr_id == 0) continue;
                if (in_array($pr_id, $added)) continue;

                $added[] = $pr_id;

                mysqli_query(
                    $conn,
                    "INSERT INTO subject_prerequisites
                    (subject_id, prereq_id, is_coreq)
                    VALUES
                    ('$subject_id', '$pr_id', '$is_coreq')"
                );
            }
        }

        if (!empty($_POST['manual_prereq'])) {

            $manual = mysqli_real_escape_string($conn, trim($_POST['manual_prereq']));

            if (!empty($manual)) {
                mysqli_query(
                    $conn,
                    "INSERT INTO subject_prerequisites
                    (subject_id, note)
                    VALUES
                    ('$subject_id', '$manual')"
                );
            }
        }
    }

    header("Location: subjects.php?success=Subject added successfully");
    exit();
}

/* ================= DELETE ================= */

if (isset($_GET['delete'])) {

    $delete = intval($_GET['delete']);

    mysqli_query($conn,"DELETE FROM subjects WHERE id='$delete'");

    mysqli_query(
        $conn,
        "DELETE FROM subject_prerequisites
         WHERE subject_id='$delete' OR prereq_id='$delete'"
    );

    header("Location: subjects.php?success=Subject deleted");
    exit();
}

/* ================= DROPDOWNS ================= */

$courses = mysqli_query(
    $conn,
    "SELECT * FROM courses ORDER BY course_name"
);

$years = mysqli_query(
    $conn,
    "SELECT DISTINCT year_name 
     FROM year_levels
     ORDER BY CAST(year_name AS UNSIGNED)"
);

$allSubjects = mysqli_query(
    $conn,
    "SELECT id, subject_code, subject_title 
     FROM subjects 
     ORDER BY subject_code"
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

<meta
    name="format-detection"
    content="telephone=no"
>

<title>Subjects</title>

<style>

/* ================= BASE ================= */
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


/* ================= ALERT ================= */
.alert{
padding:12px 14px;
border-radius:10px;
margin-bottom:15px;
font-size:13px;
opacity:1;
transition:opacity .4s ease;
}

.alert-success{
background:#e8f8f0;
color:#27ae60;
border:1px solid #b7ebd1;
}

.alert-error{
background:#fdecea;
color:#e74c3c;
border:1px solid #f5c6cb;
}


/* ================= HEADER ================= */
.top-bar{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:20px;
gap:10px;
flex-wrap:wrap;
}

.page-title{
font-size:22px;
font-weight:600;
color:#2c5aa0;
}

.top-buttons{
display:flex;
gap:10px;
flex-wrap:wrap;
}


/* ================= FILTER ================= */
.filters{
display:flex;
gap:10px;
margin-top:8px;
margin-bottom:18px;
flex-wrap:wrap;
}

.filters select{
width:200px;
height:36px;
padding:0 10px;
border-radius:8px;
border:1px solid #dbe6ff;
background:white;
font-size:13px;
}


/* ================= BUTTONS ================= */
.btn{
height:36px;
padding:0 14px;
border-radius:8px;
border:none;
color:white;
cursor:pointer;
font-size:13px;
display:inline-flex;
align-items:center;
justify-content:center;
text-decoration:none;
}

.btn-add{
background:#355fa3;
}

.btn-save{
background:#355fa3;
width:100%;
}

.btn-cancel{
background:#95a5a6;
width:100%;
}


/* ================= ACTION BUTTONS ================= */
.action-buttons{
display:flex;
gap:6px;
justify-content:center;
align-items:center;
flex-wrap:nowrap;
}

.btn-edit,
.btn-delete{
height:28px;
padding:0 10px;
border-radius:6px;
font-size:12px;
display:flex;
align-items:center;
justify-content:center;
color:white;
white-space:nowrap;
text-decoration:none;
}

.btn-edit{
background:#355fa3;
}

.btn-delete{
background:#e74c3c;
}

.btn-edit:hover{
background:#2c4f8a;
}

.btn-delete:hover{
background:#c0392b;
}


/* ================= SEMESTER TITLE ================= */
.semester-title{
font-size:16px;
font-weight:600;
color:#2c5aa0;
margin-bottom:10px;
margin-top:25px;
}

.empty-table{
text-align:center;
padding:20px;
color:#888;
font-size:13px;
}


/* ================= TABLE ================= */
.table-card{
background:white;
padding:18px;
border-radius:18px;
box-shadow:0 3px 12px rgba(0,0,0,0.05);
overflow-x:auto;
margin-bottom:20px;
}

.table-card:empty{
display:none;
}

table{
width:100%;
border-collapse:collapse;
min-width:600px;
}

thead{
background:#eef3ff;
}

th{
color:#2c5aa0;
padding:12px;
font-size:13px;
text-align:center;
border-bottom:1px solid #dbe6ff;
font-weight:600;
}

td{
padding:11px;
font-size:13px;
text-align:center;
border-bottom:1px solid #f0f0f0;
}


/* ================= MODAL ================= */
.modal{
display:none;
position:fixed;
inset:0;
background:rgba(0,0,0,0.4);

justify-content:center;
align-items:flex-start; /* allow top spacing */

z-index:9999;

/* small top & bottom spacing */
padding:30px 20px 30px;

/* prevent cut if long */
overflow-y:auto;
}

.modal-card{
background:white;
padding:20px;
border-radius:16px;
width:420px;
max-width:100%;
box-sizing:border-box;
box-shadow:0 10px 30px rgba(0,0,0,0.08);

/* center horizontally only */
margin:0 auto;

/* IMPORTANT FIX */
overflow:visible;
}

.form-group{
margin-bottom:14px;
position:relative; /* REQUIRED FOR DROPDOWN */
}

.form-group label{
font-size:12px;
display:block;
margin-bottom:5px;
color:#333;
}

.form-group input,
.form-group select{
width:100%;
height:36px;
padding:0 10px;
border-radius:8px;
border:1px solid #dbe6ff;
font-size:13px;
box-sizing:border-box;
}

/* required highlight */
.tag-input.required{
border:1px solid #e74c3c !important;
}

.modal-actions{
margin-top:12px;
display:flex;
flex-direction:column;
gap:8px;
}


/* ================= TAG INPUT ================= */
.hint{
font-size:11px;
color:#888;
margin-left:5px;
}

.tag-input{
display:flex;
flex-wrap:wrap;
gap:5px;
border:1px solid #dbe6ff;
border-radius:8px;
padding:5px;
min-height:36px;
background:white;
align-items:center;
position:relative;
z-index:2;
}

.tag-input input{
border:none;
outline:none;
flex:1;
min-width:100px;
height:24px;
font-size:12px;
background:transparent;
}

.tag{
background:#eef3ff;
color:#2c5aa0;
padding:2px 6px;
border-radius:5px;
display:flex;
align-items:center;
gap:4px;
font-size:11px;
}

.tag span{
cursor:pointer;
font-weight:bold;
}


/* ================= DROPDOWN FIX ================= */
.suggestions-box{
position:absolute;
top:100%;
left:0;
width:100%;
background:white;
border:1px solid #dbe6ff;
border-radius:8px;
max-height:180px;
overflow-y:auto;
display:none;
z-index:99999;
box-shadow:0 8px 20px rgba(0,0,0,0.08);
}

.suggestion-item{
padding:10px 12px;
font-size:13px;
cursor:pointer;
border-bottom:1px solid #f0f0f0;
transition:background 0.15s ease;
}

.suggestion-item:last-child{
border-bottom:none;
}

.suggestion-item:hover{
background:#355fa3;
color:white;
}


/* ================= TABLET ================= */
@media (max-width:1024px){

.content{
margin-left:0;
margin-right:0;
padding:20px;
padding-top:80px;
}

.top-bar{
flex-direction:column;
align-items:stretch;
gap:12px;
}

.filters select{
width:48%;
}

}


/* ================= MOBILE ================= */
@media (max-width:768px){

.content{
margin-left:0;
margin-right:0;
padding:15px;
padding-top:80px;
}

.page-title{
font-size:20px;
}

.filters{
flex-direction:column;
}

.filters select{
width:100%;
}

.btn-add{
width:100%;
}

.table-card{
padding:14px;
border-radius:14px;
}

.modal{
padding:15px;
}

.modal-card{
width:100%;
border-radius:14px;
}

.action-buttons{
justify-content:center;
}

}

</style>
</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="top-bar">

        <div class="page-title">
            Subjects
        </div>

        <div class="top-buttons">
            <button onclick="openModal()" class="btn btn-add">
                Add Subject
            </button>
        </div>

    </div>


    <!-- SUCCESS / ERROR MESSAGE -->
    <?php if(isset($_GET['success'])){ ?>
        <div class="alert alert-success" id="successAlert">
            <?php echo $_GET['success']; ?>
        </div>
    <?php } ?>

    <?php if(isset($_GET['error'])){ ?>
        <div class="alert alert-error" id="errorAlert">
            <?php echo $_GET['error']; ?>
        </div>
    <?php } ?>


    <form method="GET" class="filters">

        <select name="course_id" onchange="this.form.submit()">
            <option value="">All Courses</option>
            <?php while($c=mysqli_fetch_assoc($courses)){ ?>
                <option value="<?php echo $c['id']; ?>"
                    <?php if($course_id==$c['id']) echo "selected"; ?>>
                    <?php echo $c['course_name']; ?>
                </option>
            <?php } ?>
        </select>

        <select name="year_level" onchange="this.form.submit()">
            <option value="">All Year</option>
            <?php 
            mysqli_data_seek($years,0);
            while($y=mysqli_fetch_assoc($years)){ 
            ?>
                <option value="<?php echo $y['year_name']; ?>"
                    <?php if($year_level==$y['year_name']) echo "selected"; ?>>
                    Year <?php echo $y['year_name']; ?>
                </option>
            <?php } ?>
        </select>

        <!-- ✅ UPDATED SEMESTER FILTER -->
        <select name="semester" onchange="this.form.submit()">
            <option value="">All Semester</option>
            <option value="1" <?php if($semester=="1") echo "selected"; ?>>1st Semester</option>
            <option value="2" <?php if($semester=="2") echo "selected"; ?>>2nd Semester</option>
            <option value="3" <?php if($semester=="3") echo "selected"; ?>>Intersemester</option>
        </select>

    </form>


<?php
/* ================= FINAL PREREQ LOAD (CLEAN + STRICT) ================= */

$allPrereq = [];

$yearMap = [
    1 => "1st Year Standing",
    2 => "2nd Year Standing",
    3 => "3rd Year Standing",
    4 => "4th Year Standing"
];

$prq = mysqli_query($conn,"
    SELECT 
        sp.subject_id,
        sp.prereq_id,
        sp.is_coreq,
        sp.note,
        sp.year_required,
        s.subject_code
    FROM subject_prerequisites sp
    LEFT JOIN subjects s ON sp.prereq_id = s.id
");

while($p = mysqli_fetch_assoc($prq)){

    $sid = $p['subject_id'];

    if(!isset($allPrereq[$sid])){
        $allPrereq[$sid] = [];
    }

    /* ===== YEAR STANDING (ONLY VALID VALUES) ===== */
    if(!empty($p['year_required']) && isset($yearMap[$p['year_required']])){
        $allPrereq[$sid][] = $yearMap[$p['year_required']];
    }

    /* ===== MANUAL NOTE ===== */
    if(!empty($p['note'])){
        $note = trim($p['note']);
        if($note !== ""){
            $allPrereq[$sid][] = $note;
        }
    }

    /* ===== SUBJECT / CO-REQ ===== */
    if(!empty($p['prereq_id']) && $p['prereq_id'] > 0 && !empty($p['subject_code'])){

        if($p['is_coreq'] == 1){
            $allPrereq[$sid][] = "Co-req ".$p['subject_code'];
        } else {
            $allPrereq[$sid][] = $p['subject_code'];
        }
    }
}
?>


<!-- 1ST SEM -->
<?php if($semester != "2" && $semester != "3"){ ?>

<div class="semester-title">1st Semester Subjects</div>

<div class="table-card">

    <table>
        <thead>
            <tr>
                <th>Subject Code</th>
                <th>Subject Title</th>
                <th>Units</th>
                <th>Course</th>
                <th>Year</th>
                <th>Pre-Requisite</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>

        <?php 
        $hasFirst = false;
        mysqli_data_seek($subjects,0);

        while($row=mysqli_fetch_assoc($subjects)){ 
            if($row['semester'] != 1) continue;

            $hasFirst = true;

            $tags = $allPrereq[$row['id']] ?? [];

            $year = [];
            $coreq = [];
            $normal = [];
            $manual = [];

            foreach($tags as $t){
                if(strpos($t, "Year Standing") !== false){
                    $year[] = $t;
                }
                else if(strpos($t, "Co-req") !== false){
                    $coreq[] = $t;
                }
                else if(preg_match('/^[A-Z0-9 ]+$/', $t)){
                    $normal[] = $t;
                }
                else{
                    $manual[] = $t;
                }
            }

            $final = array_unique(array_merge($year, $normal, $coreq, $manual));
            $prereq = !empty($final) ? implode(", ", $final) : "NONE";
        ?>

            <tr>
                <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                <td><?php echo htmlspecialchars($row['subject_title']); ?></td>
                <td><?php echo $row['units']; ?></td>
                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                <td>Year <?php echo $row['year_level']; ?></td>
                <td><?php echo $prereq; ?></td>

                <td class="action-buttons">
                    <a href="subject_edit.php?id=<?php echo $row['id']; ?>" class="btn-edit">Edit</a>
                    <a href="?delete=<?php echo $row['id']; ?>" class="btn-delete">Delete</a>
                </td>
            </tr>

        <?php } ?>

        <?php if(!$hasFirst){ ?>
            <tr>
                <td colspan="7" class="empty-table">No subjects found</td>
            </tr>
        <?php } ?>

        </tbody>
    </table>

</div>

<?php } ?>


<!-- 2ND SEM -->
<?php if($semester != "1" && $semester != "3"){ ?>

<div class="semester-title">2nd Semester Subjects</div>

<div class="table-card">

    <table>
        <thead>
            <tr>
                <th>Subject Code</th>
                <th>Subject Title</th>
                <th>Units</th>
                <th>Course</th>
                <th>Year</th>
                <th>Pre-Requisite</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>

        <?php 
        $hasSecond = false;
        mysqli_data_seek($subjects,0);

        while($row=mysqli_fetch_assoc($subjects)){ 
            if($row['semester'] != 2) continue;

            $hasSecond = true;

            $tags = $allPrereq[$row['id']] ?? [];

            $year = [];
            $coreq = [];
            $normal = [];
            $manual = [];

            foreach($tags as $t){
                if(strpos($t, "Year Standing") !== false){
                    $year[] = $t;
                }
                else if(strpos($t, "Co-req") !== false){
                    $coreq[] = $t;
                }
                else if(preg_match('/^[A-Z0-9 ]+$/', $t)){
                    $normal[] = $t;
                }
                else{
                    $manual[] = $t;
                }
            }

            $final = array_unique(array_merge($year, $normal, $coreq, $manual));
            $prereq = !empty($final) ? implode(", ", $final) : "NONE";
        ?>

            <tr>
                <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                <td><?php echo htmlspecialchars($row['subject_title']); ?></td>
                <td><?php echo $row['units']; ?></td>
                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                <td>Year <?php echo $row['year_level']; ?></td>
                <td><?php echo $prereq; ?></td>

                <td class="action-buttons">
                    <a href="subject_edit.php?id=<?php echo $row['id']; ?>" class="btn-edit">Edit</a>
                    <a href="?delete=<?php echo $row['id']; ?>" class="btn-delete">Delete</a>
                </td>
            </tr>

        <?php } ?>

        <?php if(!$hasSecond){ ?>
            <tr>
                <td colspan="7" class="empty-table">No subjects found</td>
            </tr>
        <?php } ?>

        </tbody>
    </table>

</div>

<?php } ?>


<!-- ✅ INTERSEM -->
<?php if($semester != "1" && $semester != "2"){ ?>

<div class="semester-title">Intersemester Subjects</div>

<div class="table-card">

    <table>
        <thead>
            <tr>
                <th>Subject Code</th>
                <th>Subject Title</th>
                <th>Units</th>
                <th>Course</th>
                <th>Year</th>
                <th>Pre-Requisite</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>

        <?php 
        $hasInter = false;
        mysqli_data_seek($subjects,0);

        while($row=mysqli_fetch_assoc($subjects)){ 
            if($row['semester'] != 3) continue;

            $hasInter = true;

            $tags = $allPrereq[$row['id']] ?? [];

            $year = [];
            $coreq = [];
            $normal = [];
            $manual = [];

            foreach($tags as $t){
                if(strpos($t, "Year Standing") !== false){
                    $year[] = $t;
                }
                else if(strpos($t, "Co-req") !== false){
                    $coreq[] = $t;
                }
                else if(preg_match('/^[A-Z0-9 ]+$/', $t)){
                    $normal[] = $t;
                }
                else{
                    $manual[] = $t;
                }
            }

            $final = array_unique(array_merge($year, $normal, $coreq, $manual));
            $prereq = !empty($final) ? implode(", ", $final) : "NONE";
        ?>

            <tr>
                <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                <td><?php echo htmlspecialchars($row['subject_title']); ?></td>
                <td><?php echo $row['units']; ?></td>
                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                <td>Year <?php echo $row['year_level']; ?></td>
                <td><?php echo $prereq; ?></td>

                <td class="action-buttons">
                    <a href="subject_edit.php?id=<?php echo $row['id']; ?>" class="btn-edit">Edit</a>
                    <a href="?delete=<?php echo $row['id']; ?>" class="btn-delete">Delete</a>
                </td>
            </tr>

        <?php } ?>

        <?php if(!$hasInter){ ?>
            <tr>
                <td colspan="7" class="empty-table">No subjects found</td>
            </tr>
        <?php } ?>

        </tbody>
    </table>

</div>

<?php } ?>

</div>

<!-- ADD SUBJECT MODAL -->
<div id="modal" class="modal">

    <div class="modal-card">

        <form method="POST" id="subjectForm">

            <div class="form-group">
                <label>Subject Code</label>
                <input type="text" name="code" required>
            </div>

            <div class="form-group">
                <label>Subject Title</label>
                <input type="text" name="title" required>
            </div>

            <div class="form-group">
                <label>Units</label>
                <input type="number" name="units" required>
            </div>

            <div class="form-group">
                <label>Course</label>
                <select name="course_id" required>
                    <option value="">Select Course</option>
                    <?php
                    $c = mysqli_query($conn,"SELECT * FROM courses");
                    while($r=mysqli_fetch_assoc($c)){
                    ?>
                        <option value="<?php echo $r['id']; ?>">
                            <?php echo $r['course_name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label>Year Level</label>
                <select name="year_level" required>
                    <option value="">Select Year Level</option>
                    <?php
                    $y=mysqli_query($conn,"
                        SELECT DISTINCT year_name 
                        FROM year_levels
                        ORDER BY CAST(year_name AS UNSIGNED)
                    ");
                    while($r=mysqli_fetch_assoc($y)){
                    ?>
                        <option value="<?php echo $r['year_name']; ?>">
                            Year <?php echo $r['year_name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- ✅ UPDATED SEMESTER -->
            <div class="form-group">
                <label>Semester</label>
                <select name="semester" required>
                    <option value="">Select Semester</option>
                    <option value="1">1st Semester</option>
                    <option value="2">2nd Semester</option>
                    <option value="3">Intersemester</option>
                </select>
            </div>

            <!-- YEAR STANDING -->
            <div class="form-group">
                <label>Required Year Standing</label>
                <select name="year_required">
                    <option value="">None</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>

            <!-- PREREQUISITE -->
            <div class="form-group">
                <label>
                    Pre-Requisite
                    <span class="hint">(type to search and select)</span>
                </label>

                <div class="tag-input" id="tagBox">
                    <input 
                        type="text" 
                        id="tagInput"
                        placeholder="Select course/year/semester first"
                        autocomplete="off"
                        disabled
                    >
                </div>

                <div id="suggestions" class="suggestions-box"></div>

                <div id="prereqContainer"></div>

                <button type="button" id="coreqBtn" class="btn" style="background:#f39c12; margin-top:6px;">
                    Co-req Mode: OFF
                </button>
            </div>

            <!-- MANUAL -->
            <div class="form-group">
                <label>Manual Pre-Requisite</label>
                <input type="text" name="manual_prereq" placeholder="Type here...">
            </div>

            <!-- SUBJECT DATA -->
            <script>
                const subjectData = [

                    {id:0, code:"NONE", type:"none"},

                    <?php
                    $subs = mysqli_query($conn,"
                        SELECT id, subject_code, course_id 
                        FROM subjects 
                        ORDER BY subject_code
                    ");
                    while($s=mysqli_fetch_assoc($subs)){
                        echo "{id:".$s['id'].", code:'".addslashes($s['subject_code'])."', course:".$s['course_id'].", type:'subject'},";
                    }
                    ?>
                ];

                let isCoreqMode = false;

                document.getElementById("coreqBtn").onclick = function(){
                    isCoreqMode = !isCoreqMode;
                    this.textContent = isCoreqMode ? "Co-req Mode: ON" : "Co-req Mode: OFF";
                    this.style.background = isCoreqMode ? "#e67e22" : "#f39c12";
                };

                function addPrereqInput(id){

                    if(id == 0){
                        document.getElementById("prereqContainer").innerHTML = "";
                        return;
                    }

                    let val = isCoreqMode ? "C"+id : id;

                    let input = document.createElement("input");
                    input.type = "hidden";
                    input.name = "prereq[]";
                    input.value = val;

                    document.getElementById("prereqContainer").appendChild(input);
                }
            </script>

            <div class="modal-actions">

                <button type="submit" name="add_subject" class="btn btn-save">
                    Save Subject
                </button>

                <button type="button" onclick="closeModal()" class="btn btn-cancel">
                    Cancel
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* ================= MODAL ================= */
function openModal(){
    document.getElementById("modal").style.display = "flex";
    resetForm();
    checkEnablePrereq();
}

function closeModal(){
    document.getElementById("modal").style.display = "none";
}

function resetForm(){
    document.getElementById("subjectForm").reset();

    tags = [];
    tagIDs = [];

    input.disabled = true;
    input.placeholder = "Select course/year/semester first";
    input.value = "";

    // CLEAR all hidden inputs
    document.getElementById("prereqContainer").innerHTML = "";

    suggestBox.style.display = "none";
    renderTags();
}


/* ================= TAG SYSTEM ================= */
let tags = [];
let tagIDs = [];

const input  = document.getElementById("tagInput");
const box    = document.getElementById("tagBox");
const suggestBox = document.getElementById("suggestions");


/* ================= GET SELECTS ================= */
function getSelects(){
    return {
        course: document.querySelector("#modal select[name='course_id']"),
        year: document.querySelector("#modal select[name='year_level']"),
        sem: document.querySelector("#modal select[name='semester']")
    };
}


/* ================= ENABLE / DISABLE ================= */
function checkEnablePrereq(){

    const {course, year, sem} = getSelects();

    if(!course || !year || !sem) return;

    /* 🔥 INTERSEM RULE */
    if(sem.value == "3"){
        input.disabled = true;
        input.placeholder = "Intersemester uses Year Standing only";

        tags = [];
        tagIDs = [];

        document.getElementById("prereqContainer").innerHTML = "";
        renderTags();

        return;
    }

    // 🚫 LOCK if NONE selected
    if(tags.includes("NONE")){
        input.disabled = true;
        input.placeholder = "NONE selected";
        return;
    }

    if(course.value && year.value && sem.value){
        input.disabled = false;

        if(tags.length === 0){
            input.placeholder = "Search subject...";
        }

    }else{
        input.disabled = true;
        input.placeholder = "Select course/year/semester first";

        tags = [];
        tagIDs = [];

        document.getElementById("prereqContainer").innerHTML = "";

        renderTags();
    }
}


/* ================= LISTENER ================= */
document.addEventListener("change", function(e){

    if(
        e.target.name === "course_id" ||
        e.target.name === "year_level" ||
        e.target.name === "semester"
    ){
        checkEnablePrereq();
    }

});


/* ================= YEAR STANDING RULE ================= */
function allowStanding(item, year){

    if(!year) return false;

    year = year.toLowerCase();

    if(item.code === "2nd Year Standing" && year.includes("2")) return true;
    if(item.code === "3rd Year Standing" && year.includes("3")) return true;
    if(item.code === "4th Year Standing" && year.includes("4")) return true;

    return false;
}


/* ================= SHOW ALL ================= */
input.addEventListener("focus", function(){

    if(input.disabled) return;

    if(tags.includes("NONE")) return;

    const {course, year, sem} = getSelects();

    /* 🔥 BLOCK FOR INTERSEM */
    if(sem.value == "3") return;

    suggestBox.innerHTML = "";

    subjectData.forEach(item => {

        if(item.code === "NONE"){
            createSuggestion(item);
            return;
        }

        if(item.type === "standing"){
            if(allowStanding(item, year.value)){
                createSuggestion(item);
            }
            return;
        }

        if(item.type === "subject"){
            if(item.course != course.value) return;
            createSuggestion(item);
        }

    });

    suggestBox.style.display = "block";
});


/* ================= SEARCH ================= */
input.addEventListener("input", function(){

    if(input.disabled) return;

    if(tags.includes("NONE")){
        suggestBox.style.display = "none";
        return;
    }

    const {course, year, sem} = getSelects();

    /* 🔥 BLOCK FOR INTERSEM */
    if(sem.value == "3") return;

    let value = input.value.toLowerCase();

    suggestBox.innerHTML = "";

    let filtered = subjectData.filter(s => {

        if(s.code === "NONE") return true;

        if(s.type === "standing"){
            return allowStanding(s, year.value) &&
                   s.code.toLowerCase().includes(value);
        }

        if(s.type === "subject"){
            if(s.course != course.value) return false;
            return s.code.toLowerCase().includes(value);
        }

        return false;
    });

    if(filtered.length === 0){
        suggestBox.style.display = "none";
        return;
    }

    filtered.forEach(createSuggestion);

    suggestBox.style.display = "block";
});


/* ================= CREATE ITEM ================= */
function createSuggestion(item){

    let div = document.createElement("div");
    div.className = "suggestion-item";
    div.innerText = item.code;

    div.onclick = () => selectTag(item);

    suggestBox.appendChild(div);
}

/* ================= SELECT ================= */
function selectTag(item){

    const container = document.getElementById("prereqContainer");
    const {sem} = getSelects();

    // 🔥 BLOCK SUBJECTS FOR INTERSEM
    if(sem.value == "3" && item.type === "subject"){
        return;
    }

    // ===== NONE =====
    if(item.code === "NONE"){
        tags = ["NONE"];
        tagIDs = [0];

        container.innerHTML = "";

        renderTags();

        input.value = "";
        input.disabled = true;
        suggestBox.style.display = "none";
        return;
    }

    // REMOVE NONE if adding real prereq
    tags = tags.filter(t => t !== "NONE");
    tagIDs = tagIDs.filter(id => id !== 0);

    // ===== YEAR STANDING =====
    if(item.type === "standing"){
        tags = [item.code];
        tagIDs = [item.id];

        container.innerHTML = "";

        renderTags();

        input.value = "";
        suggestBox.style.display = "none";
        return;
    }

    // 🔥 EXTRA SAFETY: prevent subject add if intersem
    if(sem.value == "3") return;

    // REMOVE YEAR STANDING if adding subjects
    tags = tags.filter(t => !t.includes("Year Standing"));
    tagIDs = tagIDs.filter(id => id > 0);

    if(tags.includes(item.code)) return;

    tags.push(item.code);
    tagIDs.push(item.id);

    addPrereqInput(item.id);

    renderTags();

    input.value = "";
    input.placeholder = "Search subject...";
    suggestBox.style.display = "none";
}


/* ================= RENDER ================= */
function renderTags(){

    document.querySelectorAll(".tag").forEach(el => el.remove());

    tags.forEach((tag,i)=>{

        let div = document.createElement("div");
        div.className = "tag";

        div.innerHTML = tag + 
        " <span onclick='removeTag("+i+")'>×</span>";

        box.insertBefore(div,input);
    });

    if(tags.includes("NONE")){
        input.style.display = "none";
    }else{
        input.style.display = "block";
    }
}


/* ================= REMOVE ================= */
function removeTag(i){

    tags.splice(i,1);
    tagIDs.splice(i,1);

    document.getElementById("prereqContainer").innerHTML = "";

    tagIDs.forEach(id => addPrereqInput(id));

    if(tags.length === 0){
        input.disabled = false;
        input.placeholder = "Search subject...";
        input.style.display = "block";
    }

    renderTags();
}


/* ================= VALIDATION ================= */
document.getElementById("subjectForm").addEventListener("submit", function(e){

    const {course, year, sem} = getSelects();

    if(!course.value || !year.value || !sem.value){
        alert("Please complete Course, Year, and Semester first.");
        e.preventDefault();
        return;
    }

    // 🔥 INTERSEM VALIDATION
    if(sem.value == "3"){
        // clear any accidental subject prereqs
        document.getElementById("prereqContainer").innerHTML = "";
    }

});


/* ================= CLICK OUTSIDE ================= */
document.addEventListener("click", function(e){

    const insideInput = box.contains(e.target);
    const insideDropdown = suggestBox.contains(e.target);

    if(!insideInput && !insideDropdown){
        suggestBox.style.display = "none";
    }

});


/* ================= PREVENT CLOSE ================= */
suggestBox.addEventListener("mousedown", function(e){
    e.preventDefault();
});


/* ================= AUTO ALERT ================= */
setTimeout(function(){

    const s = document.getElementById("successAlert");
    const e = document.getElementById("errorAlert");

    if(s){
        s.style.opacity="0";
        setTimeout(()=>s.remove(),300);
    }

    if(e){
        e.style.opacity="0";
        setTimeout(()=>e.remove(),300);
    }

},2000);

</script>