<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

$id = intval($_GET['id']);


/* ================= GET SUBJECT ================= */

$subject = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM subjects WHERE id=".$id)
);


/* ================= GET PREREQUISITES ================= */

$pr = mysqli_query($conn,"
    SELECT 
        sp.prereq_id,
        sp.is_coreq,
        sp.year_required,
        sp.note,
        s.subject_code
    FROM subject_prerequisites sp
    LEFT JOIN subjects s ON sp.prereq_id = s.id
    WHERE sp.subject_id=".$id."
");

$tags = [];
$tagIDs = [];
$manual_note = '';
$year_required = 0;

while ($p = mysqli_fetch_assoc($pr)) {

    if ($p['year_required'] !== null && $p['year_required'] != 0) {
        $year_required = intval($p['year_required']);
    }

    if (!empty($p['note'])) {
        $manual_note = $p['note'];
    }

    if (!empty($p['prereq_id']) && !empty($p['subject_code'])) {

        if ($p['is_coreq'] == 1) {
            $tags[] = "Co-req " . $p['subject_code'];
            $tagIDs[] = "C" . $p['prereq_id'];
        } else {
            $tags[] = $p['subject_code'];
            $tagIDs[] = $p['prereq_id'];
        }
    }
}


/* ================= UPDATE ================= */

if (isset($_POST['update_subject'])) {

    $code       = mysqli_real_escape_string($conn, trim($_POST['code']));
    $title      = mysqli_real_escape_string($conn, trim($_POST['title']));
    $course_id  = intval($_POST['course_id']);
    $year_level = mysqli_real_escape_string($conn, $_POST['year_level']);
    $semester   = intval($_POST['semester']);
    $units      = intval($_POST['units']);

    $manual = mysqli_real_escape_string($conn, $_POST['manual_prereq'] ?? '');

    $year_required_input = isset($_POST['year_required']) 
        ? intval($_POST['year_required']) 
        : 0;

    // ✅ FIX HERE (ARRAY INSTEAD OF STRING)
    $prereq_ids = $_POST['prereq'] ?? [];


    /* ================= UPDATE SUBJECT ================= */

    mysqli_query($conn,"
        UPDATE subjects SET
            subject_code='$code',
            subject_title='$title',
            course_id='$course_id',
            year_level='$year_level',
            semester='$semester',
            units='$units'
        WHERE id=".$id."
    ");


    /* ================= RESET PREREQUISITES ================= */

    mysqli_query($conn,"
        DELETE FROM subject_prerequisites
        WHERE subject_id=".$id."
    ");


    /* ================= YEAR STANDING ================= */

    if ($year_required_input > 0) {

        mysqli_query($conn,"
            INSERT INTO subject_prerequisites
            (subject_id, year_required)
            VALUES
            (".$id.", ".$year_required_input.")
        ");

    } else {

        /* ================= SUBJECT PREREQUISITES ================= */

        if (!empty($prereq_ids)) {

            $added = [];

            foreach ($prereq_ids as $raw) {

                if ($raw === '' || $raw === null) continue;

                $is_coreq = 0;

                if (strpos($raw, "C") === 0) {
                    $is_coreq = 1;
                    $pr_id = intval(substr($raw, 1));
                } else {
                    $pr_id = intval($raw);
                }

                if ($pr_id <= 0 || $pr_id == $id) continue;
                if (in_array($pr_id, $added)) continue;

                $added[] = $pr_id;

                mysqli_query($conn,"
                    INSERT INTO subject_prerequisites
                    (subject_id, prereq_id, is_coreq)
                    VALUES
                    (".$id.", ".$pr_id.", ".$is_coreq.")
                ");
            }
        }
    }


    /* ================= MANUAL NOTE ================= */

    if (!empty(trim($manual))) {

        mysqli_query($conn,"
            INSERT INTO subject_prerequisites
            (subject_id, note)
            VALUES
            (".$id.", '$manual')
        ");
    }


    header("Location: subjects.php?success=Subject updated");
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

?>

<!DOCTYPE html>
<html>

<head>

<title>Edit Subject</title>

<style>

/* ================= BASE ================= */
*{
box-sizing:border-box;
}

body{
margin:0;
font-family:Arial, Helvetica, sans-serif;
background:#f4f7fc;
}


/* ================= CONTENT ================= */
.content{
margin-left:265px;
margin-right:25px;
padding:30px;
padding-top:40px;
min-height:100vh;
}


/* ================= TOP BAR ================= */
.top-bar{
display:flex;
align-items:center;
gap:15px;
margin-bottom:20px;
}

.btn-back{
background:#eef3ff;
color:#2c5aa0;
padding:8px 14px;
border-radius:10px;
text-decoration:none;
font-size:13px;
border:1px solid #dbe6ff;
}

.btn-back:hover{
background:#e0eaff;
}


/* ================= TITLE ================= */
.page-title{
font-size:24px;
font-weight:600;
color:#2c5aa0;
letter-spacing:0.3px;
}


/* ================= CARD ================= */
.card{
background:white;
padding:28px 30px;
border-radius:20px;
box-shadow:0 6px 18px rgba(0,0,0,0.06);
max-width:760px;
width:100%;
margin:20px auto;
overflow:visible;
}


/* ================= FORM ================= */
.form-group{
margin-bottom:18px;
position:relative;
}

.form-group label{
font-size:13px;
display:block;
margin-bottom:6px;
color:#333;
font-weight:500;
}

.form-group input,
.form-group select{
width:100%;
height:44px;
padding:0 12px;
border-radius:10px;
border:1px solid #dbe6ff;
font-size:13px;
background:white;
transition:all 0.2s ease;
}

.form-group input:focus,
.form-group select:focus{
outline:none;
border-color:#2c5aa0;
box-shadow:0 0 0 2px rgba(44,90,160,0.08);
}


/* ================= TAG INPUT ================= */
.tag-input{
display:flex;
flex-wrap:wrap;
gap:6px;
border:1px solid #dbe6ff;
border-radius:10px;
padding:6px;
min-height:44px;
background:white;
align-items:center;
position:relative;
z-index:2;
}

.tag-input input{
border:none;
outline:none;
flex:1;
min-width:120px;
height:28px;
font-size:12px;
background:transparent;
}

.tag{
background:#eef3ff;
color:#2c5aa0;
padding:4px 8px;
border-radius:6px;
font-size:12px;
display:flex;
align-items:center;
gap:5px;
}

.tag span{
cursor:pointer;
font-weight:bold;
}


/* ================= DROPDOWN ================= */
.suggestions-box{
position:absolute;
top:100%;
left:0;
width:100%;
background:white;
border:1px solid #dbe6ff;
border-radius:10px;
max-height:180px;
overflow-y:auto;
display:none;
z-index:99999;
box-shadow:0 10px 25px rgba(0,0,0,0.08);
margin-top:5px;
}

.suggestion-item{
padding:10px 12px;
font-size:13px;
cursor:pointer;
border-bottom:1px solid #f0f0f0;
transition:all 0.15s ease;
}

.suggestion-item:last-child{
border-bottom:none;
}

.suggestion-item:hover{
background:#355fa3;
color:white;
}


/* ================= BUTTONS ================= */
.form-actions{
margin-top:20px;
display:flex;
flex-direction:column;
align-items:center;
gap:12px;
}

.btn{
width:100%;
max-width:320px;
height:44px;
border-radius:10px;
border:none;
font-size:14px;
cursor:pointer;
display:flex;
align-items:center;
justify-content:center;
text-decoration:none;
color:white;
}

.btn-save{
background:#355fa3;
}

.btn-save:hover{
background:#2c4f8a;
}

.btn-cancel{
background:#95a5a6;
}

.btn-cancel:hover{
background:#7f8c8d;
}


/* ================= TABLET ================= */
@media (max-width:1024px){

.content{
margin-left:0;
margin-right:0;
padding:20px;
padding-top:90px;
}

.card{
margin:0 auto;
max-width:720px;
}

}


/* ================= MOBILE ================= */
@media (max-width:768px){

.content{
padding:15px;
padding-top:90px;
}

.page-title{
font-size:20px;
}

.card{
padding:18px;
border-radius:16px;
margin:10px auto;
}

.form-group input,
.form-group select{
height:42px;
}

.form-actions{
width:100%;
}

.btn{
width:100%;
}

}


/* ================= SMALL MOBILE ================= */
@media (max-width:480px){

.content{
padding:15px;
padding-top:85px;
}

.card{
padding:16px;
}

}


/* ================= EXTRA ================= */
.form-group{ position:relative; }
.card{ overflow:visible; }
.tag-input{ position:relative; z-index:2; }

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="top-bar">

        <a href="subjects.php" class="btn-back">
            ← Back
        </a>

        <div class="page-title">
            Edit Subject
        </div>

    </div>


    <div class="card">

        <form method="POST">

            <div class="form-group">
                <label>Subject Code</label>
                <input 
                    type="text"
                    name="code"
                    value="<?php echo $subject['subject_code']; ?>"
                    required
                >
            </div>


            <div class="form-group">
                <label>Subject Title</label>
                <input 
                    type="text"
                    name="title"
                    value="<?php echo $subject['subject_title']; ?>"
                    required
                >
            </div>


            <div class="form-group">
                <label>Units</label>
                <input 
                    type="number"
                    name="units"
                    value="<?php echo $subject['units']; ?>"
                    required
                >
            </div>


            <div class="form-group">
                <label>Course</label>
                <select name="course_id" required>
                    <?php while($c=mysqli_fetch_assoc($courses)){ ?>
                        <option
                            value="<?php echo $c['id']; ?>"
                            <?php if($subject['course_id']==$c['id']) echo "selected"; ?>
                        >
                            <?php echo $c['course_name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>


            <div class="form-group">
                <label>Year Level</label>
                <select name="year_level" required>
                    <?php while($y=mysqli_fetch_assoc($years)){ ?>
                        <option
                            value="<?php echo $y['year_name']; ?>"
                            <?php if($subject['year_level']==$y['year_name']) echo "selected"; ?>
                        >
                            Year <?php echo $y['year_name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>


            <div class="form-group">
                <label>Semester</label>
                <select name="semester" required>
                    <option value="1" <?php if($subject['semester']=="1") echo "selected"; ?>>1st Semester</option>
                    <option value="2" <?php if($subject['semester']=="2") echo "selected"; ?>>2nd Semester</option>
                    <option value="3" <?php if($subject['semester']=="3") echo "selected"; ?>>Intersemester</option>
                </select>
            </div>


            <div class="form-group">
                <label>Required Year Standing</label>
                <select name="year_required" id="yearRequired">
                    <option value="0" <?php if($year_required==0) echo "selected"; ?>>None</option>
                    <option value="2" <?php if($year_required==2) echo "selected"; ?>>2nd Year</option>
                    <option value="3" <?php if($year_required==3) echo "selected"; ?>>3rd Year</option>
                    <option value="4" <?php if($year_required==4) echo "selected"; ?>>4th Year</option>
                </select>
            </div>


            <div class="form-group">
                <label>Pre-Requisite</label>

                <div class="tag-input" id="tagBox">
                    <input type="text" id="tagInput" autocomplete="off">
                </div>

                <div id="suggestions" class="suggestions-box"></div>

                <div id="prereqContainer"></div>

                <button type="button" id="coreqBtn" class="btn" style="background:#f39c12; margin-top:6px;">
                    Co-req Mode: OFF
                </button>

                <script>
                    let tags = <?php echo json_encode($tags); ?>;
                    let tagIDs = <?php echo json_encode($tagIDs); ?>;

                    const subjectData = [
                        {id:0, code:"NONE"},
                        <?php
                        $subs = mysqli_query($conn,"SELECT id, subject_code, course_id FROM subjects ORDER BY subject_code");
                        while($s=mysqli_fetch_assoc($subs)){
                            echo "{id:".$s['id'].", code:'".addslashes($s['subject_code'])."', course:".$s['course_id']."},";
                        }
                        ?>
                    ];

                    let isCoreqMode = false;

                    const input  = document.getElementById("tagInput");
                    const box    = document.getElementById("tagBox");
                    const suggestBox = document.getElementById("suggestions");
                    const container = document.getElementById("prereqContainer");
                    const yearSelect = document.getElementById("yearRequired");

                    const courseSelect = document.querySelector("select[name='course_id']");

                    document.getElementById("coreqBtn").onclick = function(){
                        isCoreqMode = !isCoreqMode;
                        this.textContent = isCoreqMode ? "Co-req Mode: ON" : "Co-req Mode: OFF";
                        this.style.background = isCoreqMode ? "#e67e22" : "#f39c12";
                    };

                    function renderTags(){
                        document.querySelectorAll(".tag").forEach(el => el.remove());
                        container.innerHTML = "";

                        tagIDs.forEach((id,i)=>{
                            let div = document.createElement("div");
                            div.className = "tag";
                            div.innerHTML = tags[i] + " <span onclick='removeTag("+i+")'>×</span>";
                            box.insertBefore(div,input);

                            let hidden = document.createElement("input");
                            hidden.type = "hidden";
                            hidden.name = "prereq[]";
                            hidden.value = id;
                            container.appendChild(hidden);
                        });
                    }

                    function checkEnable(){
                        if(yearSelect.value > 0){
                            input.disabled = true;
                            input.placeholder = "Year standing selected";
                        } else {
                            input.disabled = false;
                            input.placeholder = "Search subject...";
                        }
                    }

                    yearSelect.addEventListener("change", function(){
                        if(this.value > 0){
                            tags = [];
                            tagIDs = [];
                            renderTags();
                        }
                        checkEnable();
                    });

                    function selectTag(item){
                        if(yearSelect.value > 0) return;
                        if(tags.includes(item.code)) return;

                        let value = isCoreqMode ? "C"+item.id : item.id;
                        let label = isCoreqMode ? "Co-req "+item.code : item.code;

                        tags.push(label);
                        tagIDs.push(value);

                        renderTags();
                        input.value="";
                        suggestBox.style.display="none";
                    }

                    function removeTag(i){
                        tags.splice(i,1);
                        tagIDs.splice(i,1);
                        renderTags();
                    }

                    function createSuggestion(item){
                        let div = document.createElement("div");
                        div.className = "suggestion-item";
                        div.innerText = item.code;
                        div.onclick = () => selectTag(item);
                        suggestBox.appendChild(div);
                    }

                    function filterSubjects(value=""){
                        let courseVal = String(courseSelect.value);
                        let val = value.toLowerCase();

                        return subjectData.filter(s => {
                            if(s.code === "NONE") return true;
                            if(String(s.course) !== courseVal) return false;
                            return s.code.toLowerCase().includes(val);
                        });
                    }

                    input.addEventListener("focus", function(){
                        suggestBox.innerHTML="";
                        filterSubjects().forEach(createSuggestion);
                        suggestBox.style.display="block";
                    });

                    input.addEventListener("input", function(){
                        suggestBox.innerHTML="";
                        filterSubjects(input.value).forEach(createSuggestion);
                        suggestBox.style.display="block";
                    });

                    renderTags();
                    checkEnable();
                </script>
            </div>


            <div class="form-group">
                <label>Manual Pre-Requisite</label>
                <input 
                    type="text" 
                    name="manual_prereq" 
                    value="<?php echo htmlspecialchars($manual_note); ?>"
                    placeholder="Type here..."
                >
            </div>


            <div class="form-actions">
                <button class="btn btn-save" name="update_subject">Update Subject</button>
                <a href="subjects.php" class="btn btn-cancel">Cancel</a>
            </div>

        </form>

    </div>

</div>

<script>

const input  = document.getElementById("tagInput");
const box    = document.getElementById("tagBox");
const suggestBox = document.getElementById("suggestions");
const yearSelect = document.getElementById("yearRequired");
const container = document.getElementById("prereqContainer");


function getSelects(){
    return {
        course: document.querySelector("select[name='course_id']"),
        year: document.querySelector("select[name='year_level']"),
        sem: document.querySelector("select[name='semester']")
    };
}


/* RENDER TAGS */
function renderTags(){

    document.querySelectorAll(".tag").forEach(el => el.remove());
    container.innerHTML = "";

    tags.forEach((tag,i)=>{

        let div = document.createElement("div");
        div.className = "tag";
        div.innerHTML = tag + " <span onclick='removeTag("+i+")'>×</span>";
        box.insertBefore(div,input);

        let hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = "prereq[]";
        hidden.value = tagIDs[i];
        container.appendChild(hidden);
    });
}


/* ENABLE / DISABLE INPUT */
function checkEnablePrereq(){

    const {course, year, sem} = getSelects();

    if(yearSelect.value > 0){
        input.disabled = true;
        input.placeholder = "Year standing selected";
        return;
    }

    if(course.value && year.value && sem.value){
        input.disabled = false;
        input.placeholder = "Search subject...";
    } else {
        input.disabled = true;
        input.placeholder = "Select course/year/semester first";
    }
}


/* YEAR STANDING CHANGE */
yearSelect.addEventListener("change", function(){

    if(this.value > 0){
        tags = [];
        tagIDs = [];
        renderTags();
    }

    checkEnablePrereq();
});


/* LISTENER FOR SELECT CHANGES */
document.addEventListener("change", function(e){
    if(
        e.target.name === "course_id" ||
        e.target.name === "year_level" ||
        e.target.name === "semester"
    ){
        checkEnablePrereq();
    }
});


/* SHOW + SEARCH (MERGED LOGIC) */
function showSuggestions(value = ""){

    const {course} = getSelects();

    suggestBox.innerHTML = "";

    // prevent showing all if course not selected
    if(!course.value){
        suggestBox.style.display = "none";
        return;
    }

    const selectedCourse = String(course.value).trim();

    let clean = value.toLowerCase().replace(/\s+/g,'');

    let filtered = subjectData.filter(item => {

        if(item.code === "NONE") return true;

        // strict + safe comparison
        if(!item.course) return false;
        if(String(item.course).trim() !== selectedCourse) return false;

        return item.code.toLowerCase().replace(/\s+/g,'').includes(clean);
    });

    filtered.forEach(createSuggestion);

    suggestBox.style.display = filtered.length ? "block" : "none";
}


/* FOCUS */
input.addEventListener("focus", function(){
    if(input.disabled) return;
    showSuggestions("");
});


/* SEARCH */
input.addEventListener("input", function(){
    if(input.disabled) return;
    showSuggestions(input.value);
});


/* CREATE ITEM */
function createSuggestion(item){
    let div = document.createElement("div");
    div.className = "suggestion-item";
    div.innerText = item.code;
    div.onclick = () => selectTag(item);
    suggestBox.appendChild(div);
}


/* SELECT TAG */
function selectTag(item){

    if(yearSelect.value > 0) return;

    if(item.code === "NONE"){
        tags = ["NONE"];
        tagIDs = [0];
        renderTags();
        input.value="";
        input.disabled = true;
        suggestBox.style.display="none";
        return;
    }

    tags = tags.filter(t => t !== "NONE");
    tagIDs = tagIDs.filter(id => id !== 0);

    if(tags.includes(item.code)) return;

    let value = item.id;

    if(typeof isCoreqMode !== "undefined" && isCoreqMode){
        value = "C" + item.id;
        tags.push("Co-req " + item.code);
    } else {
        tags.push(item.code);
    }

    tagIDs.push(value);

    renderTags();
    input.value="";
    suggestBox.style.display="none";
}


/* REMOVE TAG */
function removeTag(i){
    tags.splice(i,1);
    tagIDs.splice(i,1);
    renderTags();
}


/* VALIDATION */
document.querySelector("form").addEventListener("submit", function(e){

    if(tagIDs.length === 0 && yearSelect.value == 0){
        alert("Please select a prerequisite, choose NONE, or set a year standing.");
        e.preventDefault();
    }

});


/* CLICK OUTSIDE */
document.addEventListener("click", function(e){

    const insideInput = box.contains(e.target);
    const insideDropdown = suggestBox.contains(e.target);

    if(!insideInput && !insideDropdown){
        setTimeout(()=> suggestBox.style.display="none",100);
    }

});


/* INITIALIZE */
window.onload = function(){
    checkEnablePrereq();
    renderTags();
};

</script>

</body>
</html>