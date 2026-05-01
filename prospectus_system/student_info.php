<?php

session_start();
include "includes/db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* ================= UPDATE PERSONAL ================= */

if (isset($_POST['update_personal'])) {

    $full_name      = mysqli_real_escape_string($conn,$_POST['full_name']);
    $email          = mysqli_real_escape_string($conn,$_POST['email']);
    $contact_number = mysqli_real_escape_string($conn,$_POST['contact_number']);
    $address        = mysqli_real_escape_string($conn,$_POST['address']);

    mysqli_query($conn,"
        UPDATE students SET
            full_name      = '$full_name',
            email          = '$email',
            contact_number = '$contact_number',
            address        = '$address'
        WHERE student_id = '$student_id'
    ");

    header("Location: student_info.php");
    exit();
}

/* ================= UPDATE STANDING ================= */

if (isset($_POST['update_standing'])) {

    $course_id      = mysqli_real_escape_string($conn,$_POST['course_id']);
    $year_level     = mysqli_real_escape_string($conn,$_POST['year_level']);
    $section        = mysqli_real_escape_string($conn,$_POST['section']);
    $current_status = mysqli_real_escape_string($conn,$_POST['current_status']);

    mysqli_query($conn,"
        UPDATE students SET
            course_id      = '$course_id',
            year_level     = '$year_level',
            section        = '$section',
            current_status = '$current_status'
        WHERE student_id = '$student_id'
    ");

    header("Location: student_info.php");
    exit();
}

/* ================= STUDENT ================= */

$query = mysqli_query($conn,"
    SELECT s.*, c.course_name
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    WHERE s.student_id = '$student_id'
");

$student = mysqli_fetch_assoc($query);

/* ================= DROPDOWNS ================= */

$courses = mysqli_query($conn,"
    SELECT id, course_name
    FROM courses
    ORDER BY course_name ASC
");

$years = mysqli_query($conn,"
    SELECT year_name
    FROM year_levels
    WHERE course_id = '".$student['course_id']."'
    ORDER BY CAST(year_name AS UNSIGNED) ASC
");

$sections = mysqli_query($conn,"
    SELECT section_name
    FROM sections
    WHERE course_id = '".$student['course_id']."'
    ORDER BY section_name ASC
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

<meta name="format-detection" content="telephone=no">

<title>Student Information</title>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>

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
    padding:25px;
    padding-top:35px;
    position:relative;
    z-index:1;
}

.page-title{
    font-size:22px;
    font-weight:700;
    color:#2c5aa0;
    margin-bottom:18px;
    line-height:1.3;
}

/* ================= INFO ================= */

.info-wrap{
    display:flex;
    flex-direction:column;
    gap:12px;
    position:relative;
    z-index:1;
}

.info-row{
    background:#fff;
    border-radius:16px;
    padding:12px 18px;
    box-shadow:0 3px 10px rgba(0,0,0,.05);
    display:grid;
    grid-template-columns:180px 1fr;
    align-items:center;
    min-height:52px;
    gap:10px;
    transition:background .2s ease;
    position:relative;
    z-index:1;
}

/* FIX HOVER ABOVE SIDEBAR */
.info-row:hover{
    background:#f9fbff;
    transform:none;
    box-shadow:0 3px 10px rgba(0,0,0,.05);
}

.label{
    font-size:14px;
    color:#666;
    font-weight:500;
}

.value{
    font-size:16px;
    font-weight:700;
    color:#2c5aa0;
    word-break:break-word;
}

.value.small{
    font-size:15px;
    font-weight:600;
}

.required{
    color:#e63946;
    font-weight:700;
}

/* ================= BUTTONS ================= */

.btn-wrap{
    margin-top:18px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    position:relative;
    z-index:1;
}

.action-btn{
    border:none;
    color:#fff;
    padding:11px 20px;
    border-radius:10px;
    cursor:pointer;
    font-size:14px;
    font-weight:700;
    background:#2c5aa0;
    transition:background .2s ease;
}

.action-btn:hover{
    background:#1f4580;
    transform:none;
}

.green-btn{
    background:#16a34a;
}

.green-btn:hover{
    background:#15803d;
}

/* ================= MODAL ================= */

.modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:10001;
    padding:15px;
}

.modal-card{
    background:#fff;
    width:430px;
    max-width:100%;
    border-radius:18px;
    padding:24px;
    max-height:92vh;
    overflow-y:auto;
}

.modal-title{
    font-size:20px;
    font-weight:700;
    color:#2c5aa0;
    margin-bottom:15px;
}

.modal input,
.modal select,
.modal textarea{
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border:1px solid #dbe6ff;
    border-radius:10px;
    font-size:14px;
    background:#fff;
}

.modal textarea{
    height:90px;
    resize:none;
}

.modal input:focus,
.modal select:focus,
.modal textarea:focus{
    outline:none;
    border-color:#2c5aa0;
    box-shadow:0 0 0 3px rgba(44,90,160,.08);
}

/* ================= SAME SIZE BUTTONS ================= */

.modal-actions{
    display:flex;
    gap:10px;
    width:100%;
}

.modal-actions button{
    flex:1;
    height:42px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-size:14px;
    font-weight:700;
    display:flex;
    align-items:center;
    justify-content:center;
}

.cancel-btn{
    background:#e5e7eb;
    color:#111;
}

.save-btn{
    background:#2c5aa0;
    color:#fff;
}

.green-save{
    background:#16a34a;
    color:#fff;
}

/* ================= SIDEBAR ALWAYS TOP ================= */

.sidebar{
    z-index:9999 !important;
}

.overlay{
    z-index:9998 !important;
}

.mobile-header{
    z-index:9997 !important;
}

.burger{
    z-index:10000 !important;
}

/* ================= TABLET ================= */

@media (max-width:1024px){

    .content{
        margin-left:220px;
        padding:20px;
        padding-top:30px;
    }

    .page-title{
        font-size:21px;
    }

    .info-row{
        grid-template-columns:160px 1fr;
    }

}

/* ================= MOBILE ================= */

@media (max-width:768px){

    .content{
        margin-left:0 !important;
        padding:15px;
        padding-top:88px;
    }

    .page-title{
        font-size:20px;
        margin-bottom:15px;
    }

    .info-wrap{
        gap:10px;
    }

    .info-row{
        grid-template-columns:1fr;
        gap:5px;
        padding:14px;
        min-height:auto;
        border-radius:14px;
    }

    .label{
        font-size:13px;
    }

    .value{
        font-size:15px;
        line-height:1.4;
    }

    .value.small{
        font-size:14px;
    }

    /* MOBILE BUTTONS */
    .btn-wrap{
        flex-direction:column;
        gap:10px;
        margin-top:16px;
    }

    .action-btn{
        width:100%;
        padding:12px;
    }

    /* MODAL */
    .modal{
        padding:12px;
    }

    .modal-card{
        width:100%;
        padding:18px;
        border-radius:16px;
    }

    .modal-title{
        font-size:18px;
    }

    .modal-actions{
        flex-direction:column;
    }

    .modal-actions button{
        width:100%;
        flex:none;
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

    .info-row{
        padding:12px;
    }

    .value{
        font-size:14px;
    }

}

</style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="content">

    <div class="page-title">
        Student Information
    </div>

    <div class="info-wrap">

        <div class="info-row">
            <div class="label">Student ID</div>
            <div class="value"><?php echo $student['student_id']; ?></div>
        </div>

        <div class="info-row">
            <div class="label">Full Name</div>
            <div class="value"><?php echo $student['full_name']; ?></div>
        </div>

        <div class="info-row">
            <div class="label">Course</div>
            <div class="value"><?php echo $student['course_name']; ?></div>
        </div>

        <div class="info-row">
            <div class="label">Year Level</div>
            <div class="value"><?php echo $student['year_level']; ?> Year</div>
        </div>

        <div class="info-row">
            <div class="label">Section</div>
            <div class="value"><?php echo $student['section']; ?></div>
        </div>

        <div class="info-row">
            <div class="label">Status</div>
            <div class="value"><?php echo $student['current_status']; ?></div>
        </div>

        <div class="info-row">
            <div class="label">Email</div>
            <div class="value small"><?php echo $student['email']; ?></div>
        </div>

        <div class="info-row">
            <div class="label">Contact Number</div>
            <div class="value small">
                <?php
                echo !empty($student['contact_number'])
                ? $student['contact_number']
                : '<span class="required">* Required</span>';
                ?>
            </div>
        </div>

        <div class="info-row">
            <div class="label">Address</div>
            <div class="value small">
                <?php
                echo !empty($student['address'])
                ? $student['address']
                : '<span class="required">* Required</span>';
                ?>
            </div>
        </div>

    </div>

    <div class="btn-wrap">

        <button class="action-btn" onclick="openModal('personalModal')">
            <i class="fa fa-user-pen"></i> Edit Personal Info
        </button>

        <button class="action-btn green-btn" onclick="openModal('standingModal')">
            <i class="fa fa-graduation-cap"></i> Update Standing
        </button>

    </div>

</div>


<!-- PERSONAL MODAL -->
<div class="modal" id="personalModal">
    <div class="modal-card">

        <form method="POST">

            <div class="modal-title">
                Edit Personal Information
            </div>

            <input type="text" name="full_name"
                   value="<?php echo $student['full_name']; ?>" required>

            <input type="email" name="email"
                   value="<?php echo $student['email']; ?>" required>

            <input type="text" name="contact_number"
                   value="<?php echo $student['contact_number']; ?>"
                   placeholder="Contact Number" required>

            <textarea name="address" placeholder="Address" required><?php echo $student['address']; ?></textarea>

            <div class="modal-actions">

                <button type="button"
                        class="cancel-btn"
                        onclick="closeModal('personalModal')">
                    Cancel
                </button>

                <button type="submit"
                        name="update_personal"
                        class="save-btn">
                    Save Changes
                </button>

            </div>

        </form>

    </div>
</div>


<!-- STANDING MODAL -->
<div class="modal" id="standingModal">
    <div class="modal-card">

        <form method="POST">

            <div class="modal-title">
                Update Academic Standing
            </div>

            <select name="course_id" required>
                <?php while($c = mysqli_fetch_assoc($courses)){ ?>
                    <option value="<?php echo $c['id']; ?>"
                        <?php if($student['course_id']==$c['id']) echo "selected"; ?>>
                        <?php echo $c['course_name']; ?>
                    </option>
                <?php } ?>
            </select>

            <select name="year_level" required>
                <?php while($y = mysqli_fetch_assoc($years)){ ?>
                    <option value="<?php echo $y['year_name']; ?>"
                        <?php if($student['year_level']==$y['year_name']) echo "selected"; ?>>
                        <?php echo $y['year_name']; ?> Year
                    </option>
                <?php } ?>
            </select>

            <select name="section" required>
                <?php while($s = mysqli_fetch_assoc($sections)){ ?>
                    <option value="<?php echo $s['section_name']; ?>"
                        <?php if($student['section']==$s['section_name']) echo "selected"; ?>>
                        <?php echo $s['section_name']; ?>
                    </option>
                <?php } ?>
            </select>

            <select name="current_status" required>
                <option value="Regular"
                    <?php if($student['current_status']=="Regular") echo "selected"; ?>>
                    Regular
                </option>

                <option value="Irregular"
                    <?php if($student['current_status']=="Irregular") echo "selected"; ?>>
                    Irregular
                </option>
            </select>

            <div class="modal-actions">

                <button type="button"
                        class="cancel-btn"
                        onclick="closeModal('standingModal')">
                    Cancel
                </button>

                <button type="submit"
                        name="update_standing"
                        class="green-save">
                    Update
                </button>

            </div>

        </form>

    </div>
</div>

<script>

function openModal(id){
    document.getElementById(id).style.display="flex";
}

function closeModal(id){
    document.getElementById(id).style.display="none";
}

window.onclick = function(e){

    if(e.target.classList.contains("modal")){
        e.target.style.display="none";
    }

}

</script>

</body>
</html>