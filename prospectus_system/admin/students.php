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

$success = "";
$error   = "";

/* ================= SUCCESS DELETE MESSAGE ================= */

if (isset($_GET['deleted'])) {
    $success = "Student deleted successfully.";
}

/* ================= DROPDOWNS ================= */

$courses = mysqli_query($conn, "
    SELECT id, course_name
    FROM courses
    ORDER BY course_name ASC
");

$years = mysqli_query($conn, "
    SELECT year_name
    FROM year_levels
    GROUP BY year_name
    ORDER BY year_name ASC
");

$sections = mysqli_query($conn, "
    SELECT section_name
    FROM sections
    ORDER BY section_name ASC
");

/* ================= PASSWORD GENERATOR ================= */

function generatePassword()
{
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    return substr(str_shuffle($chars), 0, 8);
}

/* ================= ADD STUDENT ================= */

if (isset($_POST['add_student'])) {

    $student_id = mysqli_real_escape_string($conn, trim($_POST['student_id']));
    $name       = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email      = mysqli_real_escape_string($conn, trim($_POST['email']));
    $course_id  = (int)$_POST['course_id'];
    $year       = mysqli_real_escape_string($conn, $_POST['year']);
    $section    = mysqli_real_escape_string($conn, $_POST['section']);
    $status     = mysqli_real_escape_string($conn, $_POST['status']);

    /* CHECK REQUIRED */

    if (
        $student_id == "" ||
        $name == "" ||
        $email == "" ||
        $course_id == "" ||
        $year == "" ||
        $section == ""
    ) {

        $error = "Please fill in all required fields.";

    } else {

        /* CHECK EMAIL */

        $checkEmail = mysqli_query($conn, "
            SELECT id
            FROM students
            WHERE email = '$email'
        ");

        /* CHECK STUDENT ID */

        $checkID = mysqli_query($conn, "
            SELECT id
            FROM students
            WHERE student_id = '$student_id'
        ");

        if (mysqli_num_rows($checkID) > 0) {

            $error = "Student ID already exists.";

        } elseif (mysqli_num_rows($checkEmail) > 0) {

            $error = "Email already exists.";

        } else {

            /* ================= GET ACTIVE CURRICULUM ================= */

            $curriculum_id = 0;

            $getCurriculum = mysqli_query($conn, "
                SELECT id
                FROM curricula
                WHERE course_id = '$course_id'
                AND is_active = 1
                LIMIT 1
            ");

            if ($cur = mysqli_fetch_assoc($getCurriculum)) {
                $curriculum_id = (int)$cur['id'];
            }

            if ($curriculum_id <= 0) {

                $error = "No active curriculum found for selected course.";

            } else {

                $plain_password = generatePassword();
                $hash_password  = password_hash($plain_password, PASSWORD_DEFAULT);

                $insert = mysqli_query($conn, "
                    INSERT INTO students
                    (
                        student_id,
                        full_name,
                        email,
                        password,
                        course_id,
                        curriculum_id,
                        year_level,
                        section,
                        current_status
                    )
                    VALUES
                    (
                        '$student_id',
                        '$name',
                        '$email',
                        '$hash_password',
                        '$course_id',
                        '$curriculum_id',
                        '$year',
                        '$section',
                        '$status'
                    )
                ");

                if ($insert) {

                    try {

                        $mail = new PHPMailer(true);

                        $mail->isSMTP();
                        $mail->Host       = "smtp.gmail.com";
                        $mail->SMTPAuth   = true;
                        $mail->Username   = "ursaccoeng@gmail.com";
                        $mail->Password   = "ipzyafayynidonwk";
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = 465;

                        $mail->setFrom(
                            "ursaccoeng@gmail.com",
                            "Prospectus System"
                        );

                        $mail->addAddress($email, $name);

                        $mail->isHTML(true);
                        $mail->Subject = "Student Account Created";

                        $mail->Body = "
                            Hello <b>$name</b>, <br><br>

                            Your student account has been created.<br><br>

                            <b>Student ID:</b> $student_id <br>
                            <b>Password:</b> $plain_password <br><br>

                            You may now login to the system.<br><br>

                            Thank you.
                        ";

                        if ($mail->send()) {

                            $success =
                            "Student added successfully. Login credentials sent to email.";

                        } else {

                            $success =
                            "Student added successfully, but email was not sent.";
                        }

                    } catch (Exception $e) {

                        $success =
                        "Student added successfully, but email failed.";
                    }

                } else {

                    $error = "Failed to add student.";
                }
            }
        }
    }
}

/* ================= DELETE ================= */

if (isset($_GET['delete'])) {

    $delete = (int)$_GET['delete'];

    mysqli_query($conn, "
        DELETE FROM students
        WHERE id = '$delete'
    ");

    header("Location: students.php?deleted=1");
    exit();
}

/* ================= FETCH ================= */

$students = mysqli_query($conn, "
    SELECT 
        s.*,
        c.course_name,
        cu.curriculum_name
    FROM students s
    LEFT JOIN courses c
        ON c.id = s.course_id
    LEFT JOIN curricula cu
        ON cu.id = s.curriculum_id
    ORDER BY s.full_name ASC
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

<title>Students</title>

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

/* CONTENT */

.content{
    margin-left:265px;
    margin-right:25px;
    padding:30px;
    padding-top:40px;
}

.top-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.page-title{
    font-size:22px;
    font-weight:700;
    color:#2c5aa0;
    line-height:1.3;
}

.btn-add{
    background:#2c5aa0;
    color:#fff;
    padding:11px 18px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-size:14px;
    font-weight:600;
    transition:.2s;
}

.btn-add:hover{
    background:#1f4580;
}

/* ALERT */

.alert{
    padding:14px 18px;
    border-radius:12px;
    margin-bottom:18px;
    font-size:14px;
    font-weight:600;
    animation:fadeIn .3s ease;
}

.success{
    background:#eafaf1;
    color:#1e874b;
    border:1px solid #c8efd8;
}

.error{
    background:#fff0f0;
    color:#d83c3c;
    border:1px solid #ffd1d1;
}

@keyframes fadeIn{

    from{
        opacity:0;
        transform:translateY(-8px);
    }

    to{
        opacity:1;
        transform:translateY(0);
    }

}

/* TABLE */

.table-card{
    background:#fff;
    padding:22px;
    border-radius:18px;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
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
    font-weight:700;
    text-align:center;
    white-space:nowrap;
}

td{
    padding:13px;
    border-bottom:1px solid #f0f0f0;
    text-align:center;
    font-size:13px;
    white-space:nowrap;
}

tr:last-child td{
    border-bottom:none;
}

.action-cell{
    white-space:nowrap;
}

/* BUTTONS */

.btn{
    display:inline-block;
    min-width:64px;
    padding:8px 14px;
    border-radius:9px;
    color:#fff;
    text-decoration:none;
    font-size:12px;
    font-weight:600;
    text-align:center;
    margin:2px;
    transition:.2s;
}

.btn:hover{
    transform:translateY(-1px);
}

.btn-edit{
    background:#2c5aa0;
}

.btn-edit:hover{
    background:#1f4580;
}

.btn-delete{
    background:#e74c3c;
}

.btn-delete:hover{
    background:#c0392b;
}

/* MODAL */

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    justify-content:center;
    align-items:center;
    z-index:9999;
    padding:15px;
}

.modal-card{
    background:#fff;
    width:420px;
    max-width:100%;
    padding:25px;
    border-radius:18px;
    box-shadow:0 15px 45px rgba(0,0,0,.12);
}

.form-group{
    margin-bottom:14px;
}

.form-group label{
    display:block;
    font-size:13px;
    margin-bottom:6px;
    font-weight:600;
    color:#444;
}

.form-group input,
.form-group select{
    width:100%;
    height:42px;
    padding:0 12px;
    border:1px solid #dbe6ff;
    border-radius:10px;
    font-size:14px;
    box-sizing:border-box;
    background:#fff;
}

.form-group input:focus,
.form-group select:focus{
    outline:none;
    border-color:#2c5aa0;
    box-shadow:0 0 0 3px rgba(44,90,160,.08);
}

/* MODAL ACTIONS */

.modal-actions{
    display:flex;
    gap:12px;
    margin-top:18px;
}

.modal-actions button{
    flex:1;
    height:42px;
    border:none;
    border-radius:10px;
    color:#fff;
    cursor:pointer;
    font-size:14px;
    font-weight:600;
    transition:.2s;
}

.btn-save{
    background:#2c5aa0;
}

.btn-save:hover{
    background:#1f4580;
}

.btn-cancel{
    background:#95a5a6;
}

.btn-cancel:hover{
    background:#7f8c8d;
}

/* DELETE MODAL */

.delete-box{
    width:340px;
    max-width:100%;
    background:#fff;
    border-radius:18px;
    padding:22px;
    text-align:center;
    box-shadow:0 18px 45px rgba(0,0,0,.18);
    animation:fadeIn .2s ease;
}

.delete-title{
    font-size:16px;
    font-weight:700;
    color:#111;
    margin-bottom:8px;
}

.delete-text{
    font-size:13px;
    color:#555;
    line-height:1.5;
    margin-bottom:18px;
}

.delete-actions{
    display:flex;
    gap:10px;
}

.delete-actions button,
.delete-actions a{
    flex:1;
    height:40px;
    border-radius:10px;
    font-size:14px;
    font-weight:600;
    text-decoration:none;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    transition:.2s;
    box-sizing:border-box;
}

.delete-cancel{
    background:#f3f3f3;
    border:1px solid #d9d9d9;
    color:#111;
}

.delete-cancel:hover{
    background:#ececec;
}

.delete-confirm{
    background:#2c5aa0;
    color:#fff;
    border:none;
}

.delete-confirm:hover{
    background:#1f4580;
}

/* TABLET */

@media (max-width:1024px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:22px;
        padding-top:88px;
    }

}

/* MOBILE */

@media (max-width:768px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:15px;
        padding-top:88px;
    }

    .top-bar{
        flex-direction:column;
        gap:12px;
        align-items:stretch;
    }

    .page-title{
        font-size:20px;
    }

    .btn-add{
        width:100%;
    }

    .table-card{
        padding:14px;
        border-radius:15px;
    }

    table{
        min-width:900px;
    }

    .modal-card,
    .delete-box{
        width:100%;
        padding:18px;
        border-radius:15px;
    }

    .modal-actions,
    .delete-actions{
        flex-direction:column;
    }

}

/* SMALL MOBILE */

@media (max-width:480px){

    .content{
        padding:12px;
        padding-top:82px;
    }

    .page-title{
        font-size:18px;
    }

    .table-card{
        padding:12px;
    }

    th,
    td{
        font-size:12px;
        padding:10px;
    }

}

</style>

</head>
<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="top-bar">

        <div class="page-title">
            Students
        </div>

        <button onclick="openModal()" class="btn-add">
            Add Student
        </button>

    </div>

    <?php if ($success != "") { ?>
        <div id="alertBox" class="alert success">
            <?php echo $success; ?>
        </div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div id="alertBox" class="alert error">
            <?php echo $error; ?>
        </div>
    <?php } ?>

    <div class="table-card">

        <table>

            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                <?php while ($row = mysqli_fetch_assoc($students)) { ?>

                    <tr>

                        <td><?php echo $row['student_id']; ?></td>
                        <td><?php echo $row['full_name']; ?></td>
                        <td><?php echo $row['email']; ?></td>
                        <td><?php echo $row['course_name']; ?></td>
                        <td><?php echo $row['year_level']; ?></td>
                        <td><?php echo $row['section']; ?></td>
                        <td><?php echo $row['current_status']; ?></td>

                        <td class="action-cell">

                            <a
                                href="student_edit.php?id=<?php echo $row['id']; ?>"
                                class="btn btn-edit"
                            >
                                Edit
                            </a>

                            <a
                                href="#"
                                class="btn btn-delete"
                                onclick="openDeleteModal(<?php echo $row['id']; ?>)"
                            >
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php } ?>

            </tbody>

        </table>

    </div>

</div>

<!-- ADD STUDENT MODAL -->

<div id="modal" class="modal">

    <div class="modal-card">

        <form method="POST">

            <div class="form-group">
                <label>Student ID</label>
                <input type="text" name="student_id" required>
            </div>

            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Course</label>

                <select name="course_id" required>
                    <option value="">Select Course</option>

                    <?php while ($c = mysqli_fetch_assoc($courses)) { ?>
                        <option value="<?php echo $c['id']; ?>">
                            <?php echo $c['course_name']; ?>
                        </option>
                    <?php } ?>

                </select>

            </div>

            <div class="form-group">
                <label>Year</label>

                <select name="year" required>
                    <option value="">Select Year</option>

                    <?php while ($y = mysqli_fetch_assoc($years)) { ?>
                        <option value="<?php echo $y['year_name']; ?>">
                            <?php echo $y['year_name']; ?>
                        </option>
                    <?php } ?>

                </select>

            </div>

            <div class="form-group">
                <label>Section</label>

                <select name="section" required>
                    <option value="">Select Section</option>

                    <?php while ($s = mysqli_fetch_assoc($sections)) { ?>
                        <option value="<?php echo $s['section_name']; ?>">
                            <?php echo $s['section_name']; ?>
                        </option>
                    <?php } ?>

                </select>

            </div>

            <div class="form-group">
                <label>Status</label>

                <select name="status">
                    <option value="Regular">Regular</option>
                    <option value="Irregular">Irregular</option>
                </select>

            </div>

            <div class="modal-actions">

                <button
                    type="submit"
                    name="add_student"
                    class="btn-save"
                >
                    Save Student
                </button>

                <button
                    type="button"
                    onclick="closeModal()"
                    class="btn-cancel"
                >
                    Cancel
                </button>

            </div>

        </form>

    </div>

</div>

<!-- DELETE MODAL -->

<div id="deleteModal" class="modal">

    <div class="delete-box">

        <div class="delete-title">
            Delete Student
        </div>

        <div class="delete-text">
            Are you sure you want to delete this student?
        </div>

        <div class="delete-actions">

            <button
                type="button"
                class="delete-cancel"
                onclick="closeDeleteModal()"
            >
                Cancel
            </button>

            <a
                id="deleteBtn"
                href="#"
                class="delete-confirm"
            >
                Delete
            </a>

        </div>

    </div>

</div>

<script>

function openModal(){
    document.getElementById("modal").style.display = "flex";
}

function closeModal(){
    document.getElementById("modal").style.display = "none";
}

function openDeleteModal(id){
    document.getElementById("deleteModal").style.display = "flex";
    document.getElementById("deleteBtn").href = "?delete=" + id;
}

function closeDeleteModal(){
    document.getElementById("deleteModal").style.display = "none";
}


function formatName(value){

    value = value.toLowerCase();

    return value.replace(/\b\w/g, function(letter){
        return letter.toUpperCase();
    });
}

document.addEventListener("DOMContentLoaded", function(){

    let nameInput = document.querySelector('input[name="name"]');

    if(nameInput){

        nameInput.addEventListener("input", function(){
            this.value = formatName(this.value);
        });

        nameInput.addEventListener("blur", function(){
            this.value = formatName(this.value.trim());
        });

    }

});

/* AUTO HIDE ALERT */

setTimeout(function(){
    let alertBox = document.getElementById("alertBox");
    if(alertBox){
        alertBox.style.display = "none";
    }
},4000);

</script>

</body>
</html>