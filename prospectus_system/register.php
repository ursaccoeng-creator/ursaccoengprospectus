<?php

session_start();
include "includes/db.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "PHPMailer/PHPMailer-master/src/PHPMailer.php";
require "PHPMailer/PHPMailer-master/src/SMTP.php";
require "PHPMailer/PHPMailer-master/src/Exception.php";

$error   = "";
$success = "";

/* ================= FORCE HTTPS ================= */

if (
    empty($_SERVER['HTTPS']) ||
    $_SERVER['HTTPS'] === "off"
) {
    header(
        "Location: https://" .
        $_SERVER['HTTP_HOST'] .
        $_SERVER['REQUEST_URI']
    );
    exit();
}

/* ================= FETCH DATA ================= */

$courses = mysqli_query($conn, "
    SELECT id, course_name
    FROM courses
    ORDER BY course_name ASC
");

$selected_course_id = $_POST['course_id'] ?? 0;

$years = mysqli_query($conn, "
    SELECT DISTINCT year_name
    FROM year_levels
    " . ($selected_course_id ? "WHERE course_id='$selected_course_id'" : "") . "
    ORDER BY CAST(year_name AS UNSIGNED) ASC
");

$sections = mysqli_query($conn, "
    SELECT section_name
    FROM sections
    " . ($selected_course_id ? "WHERE course_id='$selected_course_id'" : "") . "
    ORDER BY section_name ASC
");

/* ================= REGISTER ================= */

if (isset($_POST['register'])) {

    $student_id       = strtoupper(trim($_POST['student_id']));
    $full_name        = trim($_POST['full_name']);
    $email            = trim($_POST['email']);
    $course_id        = (int)trim($_POST['course_id']);
    $year_level       = trim($_POST['year_level']);
    $section          = trim($_POST['section']);
    $current_status   = trim($_POST['current_status']);
    $entry_sy         = trim($_POST['entry_sy']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password != $confirm_password) {

        $error = "Password does not match.";

    } else {

        $check_id = mysqli_query($conn, "
            SELECT id
            FROM students
            WHERE student_id='$student_id'
        ");

        if (mysqli_num_rows($check_id) > 0) {

            $error = "Student ID already exists.";

        } else {

            $check_email = mysqli_query($conn, "
                SELECT id
                FROM students
                WHERE email='$email'
            ");

            if (mysqli_num_rows($check_email) > 0) {

                $error = "Email already registered.";

            } else {

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

                    if (
                        isset($_FILES['profile_image']) &&
                        $_FILES['profile_image']['error'] == 0
                    ) {

                        $image_name = $_FILES['profile_image']['name'];
                        $image_tmp  = $_FILES['profile_image']['tmp_name'];

                        $image_name = str_replace(" ", "_", $image_name);

                        $ext = strtolower(
                            pathinfo($image_name, PATHINFO_EXTENSION)
                        );

                        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

                        if (!in_array($ext, $allowed)) {

                            $error = "Invalid image format.";

                        } else {

                            $upload_dir = "uploads/";

                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }

                            $new_image = time() . "_" . $image_name;
                            $path = $upload_dir . $new_image;

                            if (move_uploaded_file($image_tmp, $path)) {

                                $hashed_password =
                                    password_hash(
                                        $password,
                                        PASSWORD_DEFAULT
                                    );

                                $insert = mysqli_query($conn, "
                                    INSERT INTO students
                                    (
                                        student_id,
                                        full_name,
                                        course_id,
                                        curriculum_id,
                                        year_level,
                                        section,
                                        current_status,
                                        entry_sy,
                                        email,
                                        password,
                                        profile_image
                                    )
                                    VALUES
                                    (
                                        '$student_id',
                                        '$full_name',
                                        '$course_id',
                                        '$curriculum_id',
                                        '$year_level',
                                        '$section',
                                        '$current_status',
                                        '$entry_sy',
                                        '$email',
                                        '$hashed_password',
                                        '$new_image'
                                    )
                                ");

                                if ($insert) {

                                    try {

                                        $mail = new PHPMailer(true);

                                        $mail->isSMTP();
                                        $mail->Host = "smtp.gmail.com";
                                        $mail->SMTPAuth = true;
                                        $mail->Username =
                                            "ursaccoeng@gmail.com";
                                        $mail->Password =
                                            "ipzyafayynidonwk";
                                        $mail->SMTPSecure =
                                            PHPMailer::ENCRYPTION_SMTPS;
                                        $mail->Port = 465;
                                        $mail->CharSet = "UTF-8";

                                        $mail->SMTPOptions = [
                                            'ssl' => [
                                                'verify_peer' => false,
                                                'verify_peer_name' => false,
                                                'allow_self_signed' => true
                                            ]
                                        ];

                                        $mail->setFrom(
                                            "ursaccoeng@gmail.com",
                                            "Prospectus System"
                                        );

                                        $mail->addAddress(
                                            $email,
                                            $full_name
                                        );

                                        $mail->isHTML(true);
                                        $mail->Subject =
                                            "Account Created Successfully";

                                        $mail->Body = "
                                            Hello <b>$full_name</b>, <br><br>

                                            Your account has been created successfully.<br><br>

                                            <b>Student ID:</b> $student_id <br>
                                            <b>Email:</b> $email <br><br>

                                            You may now login to the system.<br><br>

                                            Thank you.
                                        ";

                                        if ($mail->send()) {

                                            $_SESSION['login_success'] =
                                                "Account created successfully! Email sent.";

                                        } else {

                                            $_SESSION['login_success'] =
                                                "Account created successfully!";
                                        }

                                    } catch (Exception $e) {

                                        $_SESSION['login_success'] =
                                            "Account created successfully!";
                                    }

                                    $_SESSION['redirect_to'] =
                                        "https://" .
                                        $_SERVER['HTTP_HOST'] .
                                        "/";

                                    header("Location: register.php");
                                    exit();

                                } else {

                                    $error = "Failed to create account.";
                                }

                            } else {

                                $error = "Failed to upload image.";
                            }
                        }

                    } else {

                        $error = "Please upload a profile image.";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>

<title>Create Account</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link 
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>

<style>

body{
    margin:0;
    font-family:Arial, Helvetica, sans-serif;
    background:#f5f7fb;
}

/* HEADER */
.header{
    color:#fff;
    padding:25px 30px;
    font-size:18px;
    font-weight:700;
    background:linear-gradient(
        160deg,
        #1e4f8a 0%,
        #1e5aa8 45%,
        #1f6ed4 100%
    );
}

/* WRAPPER */
.register-wrapper{
    display:flex;
    justify-content:center;
    padding:80px 20px;
}

/* CARD */
.register-card{
    width:420px;
    background:#fff;
    padding:35px;
    border-radius:14px;
    box-shadow:0 8px 25px rgba(0,0,0,.08);
}

.register-title{
    text-align:center;
    font-size:24px;
    margin-bottom:20px;
    color:#1e5aa8;
    font-weight:600;
}

/* ERROR */
.error{
    background:#fff0f0;
    color:#d32f2f;
    padding:10px;
    border-radius:8px;
    margin-bottom:15px;
    font-size:14px;
}

/* FORM */
.form-group{
    margin-bottom:15px;
    position:relative;
}

/* LEFT ICON ONLY */
.form-group > i{
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    color:#999;
    z-index:2;
}

/* INPUTS */
.form-group input:not([type="file"]),
.form-group select{
    width:100%;
    padding:12px 12px 12px 38px;
    border-radius:8px;
    border:1px solid #ddd;
    box-sizing:border-box;
    font-size:14px;
    outline:none;
    transition:.2s;
    background:#fff;
}

.form-group input:not([type="file"]):focus,
.form-group select:focus{
    border-color:#1e5aa8;
    box-shadow:0 0 0 3px rgba(30,90,168,.08);
}

/* PASSWORD */
.password-group input{
    padding-right:42px !important;
}

.toggle-eye{
    position:absolute;
    right:12px;
    top:50%;
    transform:translateY(-50%);
    cursor:pointer;
    color:#888;
    z-index:5;
    font-size:15px;
}

.toggle-eye:hover{
    color:#1e5aa8;
}

.toggle-eye i{
    position:static !important;
    transform:none !important;
}

/* FILE INPUT */
.form-group input[type="file"]{
    width:100%;
    padding:8px 12px;
    height:42px;
    background:#f9fbff;
    cursor:pointer;
    font-size:13px;
    color:#555;
    border:1px solid #ddd;
    border-radius:8px;
    box-sizing:border-box;
}

.form-group input[type="file"]::file-selector-button{
    margin-right:10px;
    padding:6px 12px;
    border:none;
    border-radius:6px;
    background:#1e5aa8;
    color:#fff;
    cursor:pointer;
    font-size:13px;
    transition:.2s;
}

.form-group input[type="file"]::file-selector-button:hover{
    background:#174a8a;
}

/* FILE LABEL */
.file-title{
    display:block;
    margin-bottom:6px;
    font-size:14px;
    font-weight:500;
    color:#333;
}

.upload-hint{
    display:block;
    margin-top:5px;
    margin-bottom:12px;
    font-size:12px;
    color:#888;
}

/* BUTTON */
.register-btn{
    width:100%;
    padding:12px;
    border:none;
    color:#fff;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
    font-size:15px;
    background:linear-gradient(
        160deg,
        #1e4f8a 0%,
        #1e5aa8 45%,
        #1f6ed4 100%
    );
    transition:.2s;
}

.register-btn:hover{
    opacity:.92;
    transform:translateY(-1px);
}

/* LINK */
.login-link{
    display:block;
    text-align:center;
    margin-top:15px;
    color:#1e5aa8;
    text-decoration:none;
    font-size:14px;
    font-weight:500;
}

.login-link:hover{
    text-decoration:underline;
}

/* MODAL */
.login-modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:9999;
}

.login-modal-card{
    background:#fff;
    padding:30px;
    border-radius:16px;
    width:320px;
    text-align:center;
    box-shadow:0 20px 50px rgba(0,0,0,.25);
    animation:pop .25s ease;
}

@keyframes pop{
    from{
        transform:scale(.9);
        opacity:0;
    }
    to{
        transform:scale(1);
        opacity:1;
    }
}

.login-modal-icon{
    width:60px;
    height:60px;
    margin:auto;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:24px;
    margin-bottom:10px;
    background:linear-gradient(
        160deg,
        #1e4f8a 0%,
        #1e5aa8 45%,
        #1f6ed4 100%
    );
}

.login-modal-title{
    font-size:18px;
    font-weight:600;
    margin-bottom:5px;
}

.login-modal-text{
    font-size:14px;
    color:#555;
    margin-bottom:20px;
}

.login-modal-btn{
    padding:10px 20px;
    border:none;
    color:#fff;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
    background:linear-gradient(
        160deg,
        #1e4f8a 0%,
        #1e5aa8 45%,
        #1f6ed4 100%
    );
}

/* MOBILE (UPDATED) */
@media (max-width:768px){

    body{
        font-size:14px;
    }

    .header{
        padding:18px 15px;
        font-size:16px;
        text-align:center;
    }

    .register-wrapper{
        padding:25px 12px;
        align-items:flex-start;
    }

    .register-card{
        width:100%;
        padding:20px 16px;
        border-radius:10px;
        box-shadow:0 5px 15px rgba(0,0,0,.08);
    }

    .register-title{
        font-size:20px;
        margin-bottom:15px;
    }

    .form-group{
        margin-bottom:12px;
    }

    .form-group input:not([type="file"]),
    .form-group select{
        padding:11px 10px 11px 36px;
        font-size:14px;
    }

    .form-group > i{
        left:10px;
        font-size:13px;
    }

    .toggle-eye{
        right:10px;
        font-size:14px;
    }

    .form-group input[type="file"]{
        height:auto;
        padding:6px;
        font-size:12px;
    }

    .file-title{
        font-size:13px;
    }

    .upload-hint{
        font-size:11px;
    }

    .register-btn{
        padding:12px;
        font-size:14px;
    }

    .login-link{
        font-size:13px;
    }

    .login-modal-card{
        width:90%;
        padding:20px;
        border-radius:12px;
    }

    .login-modal-title{
        font-size:16px;
    }

    .login-modal-text{
        font-size:13px;
    }

    .login-modal-btn{
        width:100%;
        padding:10px;
    }

}

</style>

</head>

<body>

<div class="header">
    Prospectus System
</div>

<div class="register-wrapper">

    <div class="register-card">

        <div class="register-title">
            Create Your Account
        </div>

        <?php if ($error != "") { ?>

        <div class="error">
            <?php echo $error; ?>
        </div>

        <?php } ?>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <!-- STUDENT ID -->
            <div class="form-group">

                <i class="fa fa-id-card"></i>

                <input
                    type="text"
                    name="student_id"
                    placeholder="Student ID"
                    required
                    oninput="this.value=this.value.toUpperCase()"
                >

            </div>

            <!-- FULL NAME -->
            <div class="form-group">

                <i class="fa fa-user"></i>

                <input
                    type="text"
                    name="full_name"
                    placeholder="Full Name"
                    required
                    oninput="formatFullName(this)"
                >

            </div>

            <!-- EMAIL -->
            <div class="form-group">

                <i class="fa fa-envelope"></i>

                <input
                    type="email"
                    name="email"
                    placeholder="Email Address"
                    required
                >

            </div>

            <!-- IMAGE -->
            <div class="form-group">

                <label class="file-title">
                    Choose Profile Picture
                </label>

                <input
                    type="file"
                    id="profile_image"
                    name="profile_image"
                    accept="image/*"
                    required
                    onchange="updateFileName(this)"
                >

            </div>

            <small class="upload-hint">
                Upload a clear 2x2 ID picture (JPG, PNG, max 2MB)
            </small>

            <!-- COURSE -->
            <div class="form-group">

                <i class="fa fa-graduation-cap"></i>

                <select
                    name="course_id"
                    required
                >

                    <option value="">
                        Select Course
                    </option>

                    <?php while ($c = mysqli_fetch_assoc($courses)) { ?>

                    <option value="<?php echo $c['id']; ?>">
                        <?php echo $c['course_name']; ?>
                    </option>

                    <?php } ?>

                </select>

            </div>

            <!-- YEAR -->
            <div class="form-group">

                <i class="fa fa-layer-group"></i>

                <select
                    name="year_level"
                    required
                >

                    <option value="">
                        Year Level
                    </option>

                    <?php while ($y = mysqli_fetch_assoc($years)) { ?>

                    <option value="<?php echo $y['year_name']; ?>">
                        <?php echo $y['year_name']; ?> Year
                    </option>

                    <?php } ?>

                </select>

            </div>

            <!-- STATUS -->
            <div class="form-group">

                <i class="fa fa-user-check"></i>

                <select
                    name="current_status"
                    required
                >

                    <option value="">
                        Current Status
                    </option>

                    <option value="Regular">
                        Regular
                    </option>

                    <option value="Irregular">
                        Irregular
                    </option>

                </select>

            </div>

            <!-- SECTION -->
            <div class="form-group">

                <i class="fa fa-users"></i>

                <select
                    name="section"
                    required
                >

                    <option value="">
                        Select Section
                    </option>

                    <?php while ($sec = mysqli_fetch_assoc($sections)) { ?>

                    <option value="<?php echo $sec['section_name']; ?>">
                        <?php echo $sec['section_name']; ?>
                    </option>

                    <?php } ?>

                </select>

            </div>

            <!-- ENTRY SY -->
            <div class="form-group">

                <i class="fa fa-calendar"></i>

                <input
                    type="text"
                    name="entry_sy"
                    placeholder="Academic Entry S.Y. (ex. 2023-2024)"
                    required
                >

            </div>

            <!-- PASSWORD -->
            <div class="form-group password-group">

                <i class="fa fa-lock"></i>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Password"
                    required
                >

                <span
                    class="toggle-eye"
                    onclick="togglePassword('password',this)"
                >
                    <i class="fa fa-eye-slash"></i>
                </span>

            </div>

            <!-- CONFIRM PASSWORD -->
            <div class="form-group password-group">

                <i class="fa fa-lock"></i>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm Password"
                    required
                >

                <span
                    class="toggle-eye"
                    onclick="togglePassword('confirm_password',this)"
                >
                    <i class="fa fa-eye-slash"></i>
                </span>

            </div>

            <button
                class="register-btn"
                name="register"
            >
                Sign Up
            </button>

            <a
                class="login-link"
                href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/"
            >
                Already have an account? Login
            </a>

        </form>

    </div>

</div>

<?php if (isset($_SESSION['login_success'])) { ?>

<div class="login-modal">

    <div class="login-modal-card">

        <div class="login-modal-icon">
            <i class="fa fa-check"></i>
        </div>

        <div class="login-modal-title">
            Success
        </div>

        <div class="login-modal-text">
            <?php echo $_SESSION['login_success']; ?>
        </div>

        <button
            class="login-modal-btn"
            onclick="redirectNow()"
        >
            Continue
        </button>

    </div>

</div>

<script>

setTimeout(function () {
    redirectNow();
}, 1500);

function redirectNow() {
    window.location =
    "<?php echo $_SESSION['redirect_to']; ?>";
}

</script>

<?php
unset($_SESSION['login_success']);
}
?>

<script>

/* ================= AUTO SAVE FORM ================= */

const form = document.querySelector("form");
const fields = form.querySelectorAll("input, select");

/* LOAD SAVED DATA */
window.addEventListener("load", () => {

    fields.forEach(field => {

        const saved = localStorage.getItem(field.name);

        if (saved && field.type !== "file") {
            field.value = saved;
        }

    });

});

/* SAVE ON INPUT */
fields.forEach(field => {

    field.addEventListener("input", () => {

        if (field.type !== "file") {
            localStorage.setItem(field.name, field.value);
        }

    });

});

/* CLEAR AFTER SUCCESS */
<?php if (isset($_SESSION['login_success'])) { ?>
localStorage.clear();
<?php } ?>

/* PASSWORD SHOW HIDE */

function togglePassword(id, el) {

    let input = document.getElementById(id);
    let icon  = el.querySelector("i");

    if (input.type === "password") {

        input.type = "text";
        icon.className = "fa fa-eye";

    } else {

        input.type = "password";
        icon.className = "fa fa-eye-slash";
    }
}

/* FULL NAME FORMAT */

function formatFullName(input) {

    let words =
        input.value.toLowerCase().split(" ");

    for (let i = 0; i < words.length; i++) {

        if (words[i] != "") {

            words[i] =
                words[i].charAt(0).toUpperCase() +
                words[i].slice(1);
        }
    }

    input.value = words.join(" ");
}

</script>

</body>
</html>