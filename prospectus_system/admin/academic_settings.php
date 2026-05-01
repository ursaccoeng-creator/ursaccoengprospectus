<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

/* =========================
   MESSAGE FUNCTION
========================= */
function setMessage($msg, $type = "success"){
    $_SESSION['msg']  = $msg;
    $_SESSION['type'] = $type;
}

/* =========================
   YEAR ORDINAL FUNCTION
========================= */
function ordinal($number){

    $number = intval($number);
    $suffix = "th";

    if (!in_array(($number % 100), [11,12,13])) {

        switch ($number % 10) {
            case 1: $suffix = "st"; break;
            case 2: $suffix = "nd"; break;
            case 3: $suffix = "rd"; break;
        }

    }

    return $number . $suffix;
}

/* =========================
   ADD COURSE
========================= */
if (isset($_POST['add_course'])) {

    $course = mysqli_real_escape_string($conn, trim($_POST['course']));

    $check = mysqli_query(
        $conn,
        "SELECT * FROM courses 
         WHERE course_name = '$course'"
    );

    if (mysqli_num_rows($check) > 0) {

        setMessage("Course already exists.", "error");

    } else {

        mysqli_query(
            $conn,
            "INSERT INTO courses (course_name)
             VALUES ('$course')"
        );

        setMessage("Course added successfully.");
    }

    header("Location: academic_settings.php#courses");
    exit();
}

/* =========================
   EDIT COURSE
========================= */
if (isset($_POST['edit_course'])) {

    $id     = $_POST['id'];
    $course = mysqli_real_escape_string($conn, trim($_POST['course']));

    $check = mysqli_query(
        $conn,
        "SELECT * FROM courses
         WHERE course_name = '$course'
         AND id != '$id'"
    );

    if (mysqli_num_rows($check) > 0) {

        setMessage("Course already exists.", "error");

    } else {

        mysqli_query(
            $conn,
            "UPDATE courses
             SET course_name = '$course'
             WHERE id = '$id'"
        );

        setMessage("Course updated successfully.");
    }

    header("Location: academic_settings.php#courses");
    exit();
}

/* =========================
   DELETE COURSE
========================= */
if (isset($_GET['delete_course'])) {

    $id = $_GET['delete_course'];

    mysqli_query(
        $conn,
        "DELETE FROM courses
         WHERE id = '$id'"
    );

    setMessage("Course deleted successfully.", "success");

    header("Location: academic_settings.php#courses");
    exit();
}

/* =========================
   ADD SECTION
========================= */
if (isset($_POST['add_section'])) {

    $course  = $_POST['course_id'];
    $section = mysqli_real_escape_string($conn, trim($_POST['section']));

    $check = mysqli_query(
        $conn,
        "SELECT * FROM sections
         WHERE course_id = '$course'
         AND section_name = '$section'"
    );

    if (mysqli_num_rows($check) > 0) {

        setMessage("Section already exists in this course.", "error");

    } else {

        mysqli_query(
            $conn,
            "INSERT INTO sections (course_id, section_name)
             VALUES ('$course', '$section')"
        );

        setMessage("Section added successfully.");
    }

    header("Location: academic_settings.php#sections");
    exit();
}

/* =========================
   EDIT SECTION
========================= */
if (isset($_POST['edit_section'])) {

    $id      = $_POST['id'];
    $section = mysqli_real_escape_string($conn, trim($_POST['section']));

    $get = mysqli_query(
        $conn,
        "SELECT * FROM sections WHERE id = '$id'"
    );

    $row = mysqli_fetch_assoc($get);
    $course = $row['course_id'];

    $check = mysqli_query(
        $conn,
        "SELECT * FROM sections
         WHERE course_id = '$course'
         AND section_name = '$section'
         AND id != '$id'"
    );

    if (mysqli_num_rows($check) > 0) {

        setMessage("Section already exists in this course.", "error");

    } else {

        mysqli_query(
            $conn,
            "UPDATE sections
             SET section_name = '$section'
             WHERE id = '$id'"
        );

        setMessage("Section updated successfully.");
    }

    header("Location: academic_settings.php#sections");
    exit();
}

/* =========================
   DELETE SECTION
========================= */
if (isset($_GET['delete_section'])) {

    $id = $_GET['delete_section'];

    mysqli_query(
        $conn,
        "DELETE FROM sections
         WHERE id = '$id'"
    );

    setMessage("Section deleted successfully.", "success");

    header("Location: academic_settings.php#sections");
    exit();
}

/* =========================
   ADD YEAR
========================= */
if (isset($_POST['add_year'])) {

    $course  = $_POST['course_id'];
    $section = $_POST['section_id'];
    $year    = ordinal($_POST['year']);

    mysqli_query(
        $conn,
        "INSERT INTO year_levels (course_id, section_id, year_name)
         VALUES ('$course', '$section', '$year')"
    );

    setMessage("Year level added successfully.");

    header("Location: academic_settings.php#year");
    exit();
}

/* =========================
   EDIT YEAR
========================= */
if (isset($_POST['edit_year'])) {

    $id   = $_POST['id'];
    $year = ordinal($_POST['year']);

    mysqli_query(
        $conn,
        "UPDATE year_levels
         SET year_name = '$year'
         WHERE id = '$id'"
    );

    setMessage("Year level updated successfully.");

    header("Location: academic_settings.php#year");
    exit();
}

/* =========================
   DELETE YEAR
========================= */
if (isset($_GET['delete_year'])) {

    $id = $_GET['delete_year'];

    mysqli_query(
        $conn,
        "DELETE FROM year_levels
         WHERE id = '$id'"
    );

    setMessage("Year level deleted successfully.");

    header("Location: academic_settings.php#year");
    exit();
}

/* =========================
   UPDATE SEMESTER
========================= */
if (isset($_POST['update_semester'])) {

    $semester    = mysqli_real_escape_string($conn, $_POST['semester']);
    $school_year = mysqli_real_escape_string($conn, $_POST['school_year']);

    $check = mysqli_query(
        $conn,
        "SELECT * FROM academic_settings WHERE id = 1"
    );

    if (mysqli_num_rows($check) == 0) {

        mysqli_query(
            $conn,
            "INSERT INTO academic_settings
            (id,current_semester,school_year)
            VALUES (1,'$semester','$school_year')"
        );

    } else {

        mysqli_query(
            $conn,
            "UPDATE academic_settings
             SET current_semester = '$semester',
                 school_year = '$school_year'
             WHERE id = 1"
        );
    }

    setMessage("Semester updated successfully.");

    header("Location: academic_settings.php#semester");
    exit();
}

/* =========================
   FETCH DATA
========================= */

$courses = mysqli_query(
    $conn,
    "SELECT * FROM courses ORDER BY course_name"
);

$sections = mysqli_query(
    $conn,
    "SELECT s.*, c.course_name
     FROM sections s
     LEFT JOIN courses c ON c.id = s.course_id
     ORDER BY c.course_name, s.section_name"
);

$years = mysqli_query(
    $conn,
    "SELECT y.*, c.course_name, s.section_name
     FROM year_levels y
     LEFT JOIN courses c ON c.id = y.course_id
     LEFT JOIN sections s ON s.id = y.section_id
     ORDER BY c.course_name, s.section_name, y.year_name"
);

$settings = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT * FROM academic_settings WHERE id = 1"
    )
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

<title>Academic Settings</title>

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

.content{
    margin-left:265px;
    margin-right:25px;
    padding:30px;
    padding-top:40px;
}

.page-title{
    font-size:22px;
    font-weight:700;
    margin-bottom:20px;
    color:#2c5aa0;
    line-height:1.3;
}

/* ================= GRID ================= */

.grid{
    display:flex;
    flex-direction:column;
    gap:20px;
}

/* ================= CARD ================= */

.card{
    background:#fff;
    padding:18px 20px;
    border-radius:18px;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
    transition:.2s ease;
}

.card:hover{
    box-shadow:0 6px 18px rgba(0,0,0,.06);
}

.card-title{
    font-weight:700;
    color:#2c5aa0;
    display:flex;
    justify-content:space-between;
    align-items:center;
    cursor:pointer;
    min-height:24px;
    gap:10px;
    line-height:1.3;
}

.caret{
    font-size:18px;
    transition:.25s ease;
    flex-shrink:0;
}

.card.collapsed .list,
.card.collapsed form{
    display:none;
}

.card.collapsed .caret{
    transform:rotate(-90deg);
}

/* ================= FORM ================= */

input,
select{
    width:100%;
    height:42px;
    padding:0 12px;
    border-radius:10px;
    border:1px solid #dbe6ff;
    margin-bottom:10px;
    background:#fff;
    transition:.2s;
    font-size:14px;
}

input:focus,
select:focus{
    border-color:#2c5aa0;
    outline:none;
    box-shadow:0 0 0 2px rgba(44,90,160,.10);
}

.btn{
    width:100%;
    height:42px;
    background:#2c5aa0;
    color:#fff;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-size:14px;
    font-weight:600;
    transition:.2s;
}

.btn:hover{
    background:#244a85;
}

/* ================= LIST ================= */

.list{
    margin-top:12px;
    font-size:13px;
}

.row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:10px 0;
    border-bottom:1px solid #f0f0f0;
    gap:10px;
}

.row:last-child{
    border-bottom:none;
}

/* ================= ACTIONS ================= */

.actions{
    display:flex;
    gap:6px;
    align-items:center;
    flex-wrap:wrap;
}

.actions form{
    display:flex;
    gap:6px;
    align-items:center;
}

.actions input{
    height:34px;
    margin:0;
}

.actions a{
    display:inline-flex;
}

/* ================= BUTTONS ================= */

.btn-small{
    height:34px;
    padding:0 12px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#e9f0ff;
    color:#2c5aa0;
    font-size:13px;
    transition:.2s;
}

.btn-small:hover{
    background:#d6e4ff;
}

.btn-delete{
    background:#ffe5e5;
    color:#c00;
}

.btn-delete:hover{
    background:#ffcccc;
}

/* ================= MODAL ================= */

.modal-wrap{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:99999;
    padding:20px;
    backdrop-filter:blur(2px);
}

.modal-wrap.show{
    display:flex;
    animation:fadeBg .2s ease;
}

@keyframes fadeBg{
    from{opacity:0;}
    to{opacity:1;}
}

.modal-box{
    width:100%;
    max-width:420px;
    background:#fff;
    border-radius:18px;
    padding:24px;
    box-shadow:0 20px 45px rgba(0,0,0,.18);
    animation:popUp .22s ease;
}

@keyframes popUp{
    from{
        opacity:0;
        transform:translateY(15px) scale(.96);
    }
    to{
        opacity:1;
        transform:translateY(0) scale(1);
    }
}

.modal-box.small{
    max-width:380px;
}

.modal-title{
    font-size:22px;
    font-weight:700;
    color:#2c5aa0;
    margin-bottom:14px;
}

.modal-title.success{
    color:#27ae60;
}

.modal-title.error{
    color:#d63031;
}

.modal-text{
    font-size:15px;
    color:#444;
    line-height:1.5;
    margin-bottom:24px;
}

.modal-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
}

.modal-actions.center{
    justify-content:center;
}

.btn-cancel,
.btn-ok{
    border:none;
    padding:11px 22px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
    transition:.2s;
}

.btn-cancel{
    background:#ececec;
    color:#333;
}

.btn-cancel:hover{
    background:#dddddd;
}

.btn-ok{
    background:#2c5aa0;
    color:#fff;
}

.btn-ok:hover{
    background:#244a85;
}

/* ================= FIX ================= */

#semester form:first-of-type{
    margin-bottom:15px;
}

#semester input,
#semester select{
    margin-bottom:12px;
}

/* ================= TABLET ================= */

@media (max-width:1024px){

    .content{
        margin-left:0;
        margin-right:20px;
        padding:25px;
        padding-top:82px;
    }

    .grid{
        gap:18px;
    }

    .row{
        flex-direction:column;
        align-items:flex-start;
        gap:8px;
    }

    .actions{
        width:100%;
        gap:8px;
    }

    .actions form{
        width:100%;
    }

    .actions input{
        flex:1;
    }

}

/* ================= MOBILE ================= */

@media (max-width:768px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:15px;
        padding-top:85px;
    }

    .page-title{
        font-size:20px;
        margin-bottom:16px;
    }

    .card{
        padding:16px;
        border-radius:14px;
    }

    .caret{
        font-size:20px;
    }

    .row{
        flex-direction:column;
        align-items:stretch;
        gap:8px;
    }

    .actions{
        width:100%;
        gap:6px;
    }

    .actions form{
        display:flex;
        width:100%;
        gap:6px;
    }

    .actions input{
        flex:1;
    }

    .btn-small{
        width:auto;
    }

    .btn-delete{
        width:auto;
    }

    .modal-box{
        max-width:100%;
        padding:20px;
    }

    .modal-title{
        font-size:20px;
    }

    .modal-actions{
        flex-direction:column;
    }

    .btn-cancel,
    .btn-ok{
        width:100%;
    }

}

/* ================= SMALL MOBILE ================= */

@media (max-width:480px){

    .content{
        padding:12px;
        padding-top:78px;
    }

    .page-title{
        font-size:18px;
    }

    .card{
        padding:14px;
    }

    .grid{
        gap:15px;
    }

}

</style>

</head>
<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="page-title">
        Manage Academic Settings
    </div>

    <div class="grid">

        <!-- ================= CURRICULUM ================= -->
        <div class="card" id="curriculum">

            <div class="card-title">
                Curriculum
                <span class="caret">▶</span>
            </div>

            <div class="list">

                <div class="row">

                    <span>
                        Current Curriculum Setup
                    </span>

                    <div class="actions">

                        <a href="curriculum_update.php">
                            <button type="button" class="btn-small">
                                Update
                            </button>
                        </a>

                    </div>

                </div>

            </div>

        </div>


        <!-- ================= COURSES ================= -->
        <div class="card" id="courses">

            <div class="card-title">
                Courses
                <span class="caret">▶</span>
            </div>

            <form method="POST" action="#courses">

                <input
                    type="text"
                    name="course"
                    placeholder="Add Course"
                    required
                >

                <button
                    type="submit"
                    name="add_course"
                    class="btn"
                >
                    Add Course
                </button>

            </form>

            <div class="list">

                <?php while ($row = mysqli_fetch_assoc($courses)) { ?>

                <div class="row">

                    <span>
                        <?php echo $row['course_name']; ?>
                    </span>

                    <div class="actions">

                        <form method="POST" action="#courses">

                            <input
                                type="hidden"
                                name="id"
                                value="<?php echo $row['id']; ?>"
                            >

                            <input
                                type="text"
                                name="course"
                                value="<?php echo $row['course_name']; ?>"
                                required
                            >

                            <button
                                type="submit"
                                name="edit_course"
                                class="btn-small"
                            >
                                Edit
                            </button>

                        </form>

                        <button
                            type="button"
                            class="btn-small btn-delete"
                            onclick="openConfirmModal('?delete_course=<?php echo $row['id']; ?>#courses','Delete this course?')"
                        >
                            Delete
                        </button>

                    </div>

                </div>

                <?php } ?>

            </div>

        </div>



        <!-- ================= SECTIONS ================= -->
        <div class="card" id="sections">

            <div class="card-title">
                Sections
                <span class="caret">▶</span>
            </div>

            <form method="POST" action="#sections">

                <select name="course_id" required>

                    <option value="">
                        Select Course
                    </option>

                    <?php
                    $c = mysqli_query($conn,"SELECT * FROM courses ORDER BY course_name");
                    while ($row = mysqli_fetch_assoc($c)) {
                    ?>

                    <option value="<?php echo $row['id']; ?>">
                        <?php echo $row['course_name']; ?>
                    </option>

                    <?php } ?>

                </select>

                <input
                    type="text"
                    name="section"
                    placeholder="Section Name"
                    required
                >

                <button
                    type="submit"
                    name="add_section"
                    class="btn"
                >
                    Add Section
                </button>

            </form>

            <div class="list">

                <?php while ($row = mysqli_fetch_assoc($sections)) { ?>

                <div class="row">

                    <span>
                        <?php echo $row['course_name']; ?> -
                        <?php echo $row['section_name']; ?>
                    </span>

                    <div class="actions">

                        <form method="POST" action="#sections">

                            <input
                                type="hidden"
                                name="id"
                                value="<?php echo $row['id']; ?>"
                            >

                            <input
                                type="text"
                                name="section"
                                value="<?php echo $row['section_name']; ?>"
                                required
                            >

                            <button
                                type="submit"
                                name="edit_section"
                                class="btn-small"
                            >
                                Edit
                            </button>

                        </form>

                        <button
                            type="button"
                            class="btn-small btn-delete"
                            onclick="openConfirmModal('?delete_section=<?php echo $row['id']; ?>#sections','Delete this section?')"
                        >
                            Delete
                        </button>

                    </div>

                </div>

                <?php } ?>

            </div>

        </div>



        <!-- ================= YEAR LEVEL ================= -->
        <div class="card" id="year">

            <div class="card-title">
                Year Levels
                <span class="caret">▶</span>
            </div>

            <form method="POST" action="#year">

                <select name="course_id" required>

                    <option value="">
                        Select Course
                    </option>

                    <?php
                    $c = mysqli_query($conn,"SELECT * FROM courses ORDER BY course_name");
                    while ($row = mysqli_fetch_assoc($c)) {
                    ?>

                    <option value="<?php echo $row['id']; ?>">
                        <?php echo $row['course_name']; ?>
                    </option>

                    <?php } ?>

                </select>

                <select name="section_id" required>

                    <option value="">
                        Select Section
                    </option>

                    <?php
                    $s = mysqli_query($conn,"SELECT * FROM sections ORDER BY section_name");
                    while ($row = mysqli_fetch_assoc($s)) {
                    ?>

                    <option value="<?php echo $row['id']; ?>">
                        <?php echo $row['section_name']; ?>
                    </option>

                    <?php } ?>

                </select>

                <input
                    type="number"
                    name="year"
                    placeholder="1 - 5"
                    required
                >

                <button
                    type="submit"
                    name="add_year"
                    class="btn"
                >
                    Add Year
                </button>

            </form>

            <div class="list">

                <?php while ($row = mysqli_fetch_assoc($years)) { ?>

                <div class="row">

                    <span>
                        <?php echo $row['course_name']; ?> -
                        <?php echo $row['section_name']; ?> -
                        <?php echo $row['year_name']; ?> Year
                    </span>

                    <div class="actions">

                        <form method="POST" action="#year">

                            <input
                                type="hidden"
                                name="id"
                                value="<?php echo $row['id']; ?>"
                            >

                            <input
                                type="number"
                                name="year"
                                value="<?php echo (int)$row['year_name']; ?>"
                                required
                            >

                            <button
                                type="submit"
                                name="edit_year"
                                class="btn-small"
                            >
                                Edit
                            </button>

                        </form>

                        <button
                            type="button"
                            class="btn-small btn-delete"
                            onclick="openConfirmModal('?delete_year=<?php echo $row['id']; ?>#year','Delete this year level?')"
                        >
                            Delete
                        </button>

                    </div>

                </div>

                <?php } ?>

            </div>

        </div>



        <!-- ================= SEMESTER ================= -->
        <div class="card" id="semester">

            <div class="card-title">
                Current Semester
                <span class="caret">▶</span>
            </div>

            <form method="POST" action="#semester">

                <select name="semester" required>

                    <option value="1st" <?php if($settings['current_semester']=="1st") echo "selected"; ?>>
                        1st Semester
                    </option>

                    <option value="2nd" <?php if($settings['current_semester']=="2nd") echo "selected"; ?>>
                        2nd Semester
                    </option>

                    <option value="Inter" <?php if($settings['current_semester']=="Inter") echo "selected"; ?>>
                        Inter Semester
                    </option>

                </select>

                <input
                    type="text"
                    name="school_year"
                    value="<?php echo htmlspecialchars($settings['school_year']); ?>"
                    placeholder="2025-2026"
                    required
                >

                <button
                    type="submit"
                    name="update_semester"
                    class="btn"
                >
                    Update Semester
                </button>

            </form>

        </div>

    </div>

</div>



<!-- ================= CONFIRM MODAL ================= -->
<div id="confirmModal" class="modal-wrap">

    <div class="modal-box">

        <div class="modal-title">
            Confirmation
        </div>

        <div class="modal-text" id="confirmMessage">
            Are you sure?
        </div>

        <div class="modal-actions">

            <button
                type="button"
                class="btn-cancel"
                onclick="closeConfirmModal()"
            >
                Cancel
            </button>

            <a href="#" id="confirmLink">
                <button
                    type="button"
                    class="btn-ok"
                >
                    Confirm
                </button>
            </a>

        </div>

    </div>

</div>



<!-- ================= MESSAGE MODAL ================= -->
<?php if(isset($_SESSION['msg'])){ ?>

<div id="messageModal" class="modal-wrap show">

    <div class="modal-box small">

        <div class="modal-title <?php echo $_SESSION['type']; ?>">

            <?php
            if($_SESSION['type']=="error"){
                echo "Warning";
            }else{
                echo "Success";
            }
            ?>

        </div>

        <div class="modal-text">
            <?php echo $_SESSION['msg']; ?>
        </div>

        <div class="modal-actions center">

            <button
                type="button"
                class="btn-ok"
                onclick="closeMessageModal()"
            >
                OK
            </button>

        </div>

    </div>

</div>

<?php
unset($_SESSION['msg']);
unset($_SESSION['type']);
} ?>



<script>

/* ================= COLLAPSE ================= */
function initCollapse(){

    const isMobile = window.innerWidth <= 768;

    document.querySelectorAll(".card").forEach(card => {

        if(isMobile){
            card.classList.add("collapsed");
        }else{
            card.classList.remove("collapsed");
        }

    });

}

initCollapse();

window.addEventListener("resize", initCollapse);

document.querySelectorAll(".card-title").forEach(title => {

    title.addEventListener("click", function(){

        this.parentElement.classList.toggle("collapsed");

    });

});


/* ================= CONFIRM MODAL ================= */
function openConfirmModal(link,message){

    document.getElementById("confirmModal").classList.add("show");
    document.getElementById("confirmMessage").innerText = message;
    document.getElementById("confirmLink").href = link;

}

function closeConfirmModal(){

    document.getElementById("confirmModal").classList.remove("show");

}


/* ================= MESSAGE MODAL ================= */
function closeMessageModal(){

    let modal = document.getElementById("messageModal");

    if(modal){
        modal.classList.remove("show");
    }

}

/* auto close after 2.5 sec */
setTimeout(function(){

    closeMessageModal();

},2500);


/* close if click outside */
window.addEventListener("click", function(e){

    let confirmModal = document.getElementById("confirmModal");
    let messageModal = document.getElementById("messageModal");

    if(e.target === confirmModal){
        closeConfirmModal();
    }

    if(e.target === messageModal){
        closeMessageModal();
    }

});

</script>

</body>
</html>