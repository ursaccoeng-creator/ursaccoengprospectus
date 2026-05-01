<?php
session_start();
include "includes/db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

$query = mysqli_query($conn,"
    SELECT *
    FROM students
    WHERE student_id='$student_id'
");

$data = mysqli_fetch_assoc($query);

$full_name = $data['full_name'];

/* CHECK IF HAS IMAGE */
$has_image = !empty($data['profile_image']);

$profile_img = $has_image
    ? "uploads/" . $data['profile_image']
    : "img/default.png";

/* MESSAGE */
$msg   = "";
$error = "";

/* ================= IMAGE UPLOAD ================= */

if (isset($_POST['upload_image'])) {

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] == 0 &&
        !empty($_FILES['image']['name'])
    ) {

        $img_name = $_FILES['image']['name'];
        $img_tmp  = $_FILES['image']['tmp_name'];

        $ext = strtolower(
            pathinfo($img_name, PATHINFO_EXTENSION)
        );

        $allowed = ['jpg','jpeg','png','gif'];

        if (!in_array($ext,$allowed)) {

            $error = "Invalid image format.";

        } else {

            if (!is_dir("uploads")) {
                mkdir("uploads",0777,true);
            }

            $img = time() . "_" . str_replace(" ","_",$img_name);

            if (
                move_uploaded_file(
                    $img_tmp,
                    "uploads/" . $img
                )
            ) {

                mysqli_query($conn,"
                    UPDATE students
                    SET profile_image='$img'
                    WHERE student_id='$student_id'
                ");

                header("Location: student_profile.php");
                exit();

            } else {

                $error = "Failed to upload image.";
            }
        }

    } else {

        $error = "Please choose an image.";
    }
}

/* ================= PASSWORD CHANGE ================= */

if (isset($_POST['change_password'])) {

    /* REQUIRE IMAGE FIRST */
    if (!$has_image) {

        $error =
        "Please upload your profile image first before changing password.";

    } else {

        $current_password = $_POST['current_password'];
        $new_password     = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (
            password_verify(
                $current_password,
                $data['password']
            )
        ) {

            if ($new_password === $confirm_password) {

                $new_hash = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                mysqli_query($conn,"
                    UPDATE students
                    SET password='$new_hash'
                    WHERE student_id='$student_id'
                ");

                $msg = "Password updated successfully.";

            } else {

                $error = "New passwords do not match.";
            }

        } else {

            $error = "Current password incorrect.";
        }
    }
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

<title>Student Profile</title>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
/>

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
    margin-right:25px;
    padding:30px;
    padding-top:40px;
    position:relative;
    z-index:1;
}

.page-title{
    font-size:22px;
    margin-bottom:20px;
    font-weight:700;
    color:#2c5aa0;
    line-height:1.3;
}

/* ================= SECTION ================= */

.profile-section{
    background:#fff;
    padding:22px;
    border-radius:18px;
    margin-bottom:22px;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
    position:relative;
    z-index:1;
}

.profile-section-title{
    font-size:18px;
    margin-bottom:16px;
    font-weight:700;
    color:#2c5aa0;
}

/* ================= PROFILE ================= */

.profile-box{
    display:flex;
    align-items:center;
    gap:20px;
}

.profile-box img{
    width:95px;
    height:95px;
    border-radius:50%;
    object-fit:cover;
    border:3px solid #2c5aa0;
    background:#eef3ff;
    flex-shrink:0;
}

.student-profile-name{
    font-size:18px;
    font-weight:700;
    color:#111;
    margin-bottom:4px;
    line-height:1.3;
}

.profile-id{
    font-size:14px;
    color:#666;
}

/* ================= WARNING ================= */

.profile-warning{
    background:#fff7e6;
    color:#b76b00;
    border:1px solid #ffd591;
    padding:12px 14px;
    border-radius:12px;
    font-size:14px;
    font-weight:600;
    margin-bottom:15px;
    line-height:1.4;
}

/* ================= INPUT ================= */

.profile-input{
    width:100%;
    height:48px;
    padding:0 46px 0 14px;
    border-radius:14px;
    border:1px solid #dbe6ff;
    font-size:14px;
    transition:.2s;
    background:#fff;
}

.profile-input:focus{
    outline:none;
    border-color:#2c5aa0;
    box-shadow:0 0 0 3px rgba(44,90,160,.10);
}

.profile-input:disabled{
    background:#f3f4f6;
    color:#888;
    cursor:not-allowed;
}

/* ================= PASSWORD ================= */

.profile-password-field{
    position:relative;
    margin-bottom:14px;
}

.toggle-icon{
    position:absolute;
    right:16px;
    top:50%;
    transform:translateY(-50%);
    cursor:pointer;
    font-size:15px;
    color:#6b7280;
}

.toggle-icon:hover{
    color:#2c5aa0;
}

/* ================= FILE ================= */

.profile-file{
    border:1px solid #dbe6ff;
    border-radius:12px;
    padding:8px;
    background:#f9fbff;
}

.profile-file input{
    width:100%;
    font-size:14px;
}

.profile-file input::file-selector-button{
    background:#2c5aa0;
    color:#fff;
    border:none;
    padding:9px 14px;
    border-radius:8px;
    cursor:pointer;
    margin-right:10px;
    font-weight:600;
}

.profile-file input::file-selector-button:hover{
    background:#1f4580;
}

/* ================= BUTTON ================= */

.profile-btn{
    background:#2c5aa0;
    color:#fff;
    border:none;
    padding:11px 18px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
    font-size:14px;
    transition:.2s;
    width:100%;
}

.profile-btn:hover{
    background:#1f4580;
}

.profile-btn:disabled{
    background:#9ca3af;
    cursor:not-allowed;
}

/* ================= MESSAGE ================= */

.profile-msg,
.profile-error{
    margin-top:14px;
    padding:12px 14px;
    border-radius:10px;
    font-size:14px;
    font-weight:600;
    line-height:1.4;
}

.profile-msg{
    background:#eafaf1;
    color:#1e874b;
    border:1px solid #c8efd8;
}

.profile-error{
    background:#fff0f0;
    color:#d83c3c;
    border:1px solid #ffd1d1;
}

/* ================= TABLET ================= */

@media (max-width:1024px){

    .content{
        margin-left:0 !important;
        margin-right:0;
        padding:22px;
        padding-top:90px;
    }

}

/* ================= MOBILE ================= */

@media (max-width:768px){

    /* PAGE BELOW HEADER */
    .content{
        margin:0 !important;
        padding:15px !important;
        padding-top:88px !important;
        position:relative;
        z-index:1 !important;
    }

    /* SIDENAV ON TOP */
    .sidebar{
        position:fixed !important;
        top:75px !important;
        left:-260px;
        width:230px;
        height:calc(100vh - 90px);
        max-height:calc(100vh - 90px);
        overflow-y:auto;
        overflow-x:hidden;
        z-index:9999 !important;
    }

    .sidebar.active{
        left:15px !important;
    }

    .overlay{
        z-index:9998 !important;
    }

    .page-title{
        font-size:20px;
        margin-bottom:16px;
    }

    .profile-section{
        padding:18px;
        border-radius:16px;
    }

    .profile-section-title{
        font-size:17px;
    }

    .profile-box{
        flex-direction:column;
        text-align:center;
        gap:14px;
    }

    .profile-box img{
        width:90px;
        height:90px;
    }

    .student-profile-name{
        font-size:17px;
    }

    .profile-id{
        font-size:13px;
    }

    .profile-input{
        height:46px;
        font-size:14px;
    }

    /* FIX BUTTON UNDER SIDENAV */
    .profile-btn{
        width:100%;
        padding:12px;
        position:relative;
        z-index:1 !important;
    }

}

/* ================= SMALL MOBILE ================= */

@media (max-width:480px){

    .content{
        padding:12px !important;
        padding-top:84px !important;
    }

    .page-title{
        font-size:18px;
    }

    .profile-section{
        padding:15px;
    }

    .profile-box img{
        width:82px;
        height:82px;
    }

    .student-profile-name{
        font-size:16px;
    }

}

</style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="content">

    <div class="page-title">Student Profile</div>

    <!-- PROFILE -->
    <div class="profile-section">

        <div class="profile-box">

            <img src="<?php echo $profile_img; ?>" id="preview">

            <div>
                <div class="student-profile-name">
                    <?php echo $full_name; ?>
                </div>

                <div class="profile-id">
                    <?php echo $student_id; ?>
                </div>
            </div>

        </div>

    </div>

    <!-- IMAGE -->
    <div class="profile-section">

        <div class="profile-section-title">
            Change Profile Image
        </div>

        <?php if(!$has_image){ ?>
            <div class="profile-warning">
                Profile picture is required. Please upload first.
            </div>
        <?php } ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="profile-file">
                <input
                    type="file"
                    name="image"
                    onchange="previewImage(event)"
                    required
                >
            </div>

            <br>

            <button
                class="profile-btn"
                name="upload_image"
            >
                Upload Image
            </button>

        </form>

    </div>

    <!-- PASSWORD -->
    <div class="profile-section">

        <div class="profile-section-title">
            Change Password
        </div>

        <?php if(!$has_image){ ?>
            <div class="profile-warning">
                Upload your profile image first before changing password.
            </div>
        <?php } ?>

        <form method="POST">

            <div class="profile-password-field">

                <input
                    class="profile-input"
                    type="password"
                    name="current_password"
                    id="c"
                    placeholder="Current Password"
                    required
                    <?php if(!$has_image) echo "disabled"; ?>
                >

                <i
                    class="fa-solid fa-eye-slash toggle-icon"
                    onclick="togglePass('c', this)"
                ></i>

            </div>

            <div class="profile-password-field">

                <input
                    class="profile-input"
                    type="password"
                    name="new_password"
                    id="n"
                    placeholder="New Password"
                    required
                    <?php if(!$has_image) echo "disabled"; ?>
                >

                <i
                    class="fa-solid fa-eye-slash toggle-icon"
                    onclick="togglePass('n', this)"
                ></i>

            </div>

            <div class="profile-password-field">

                <input
                    class="profile-input"
                    type="password"
                    name="confirm_password"
                    id="cf"
                    placeholder="Confirm Password"
                    required
                    <?php if(!$has_image) echo "disabled"; ?>
                >

                <i
                    class="fa-solid fa-eye-slash toggle-icon"
                    onclick="togglePass('cf', this)"
                ></i>

            </div>

            <button
                class="profile-btn"
                name="change_password"
                <?php if(!$has_image) echo "disabled"; ?>
            >
                Update Password
            </button>

        </form>

        <?php if($msg){ ?>
            <div class="profile-msg">
                <?php echo $msg; ?>
            </div>
        <?php } ?>

        <?php if($error){ ?>
            <div class="profile-error">
                <?php echo $error; ?>
            </div>
        <?php } ?>

    </div>

</div>

</body>

<script>

function togglePass(id, el){

    const input = document.getElementById(id);

    if(input.disabled){
        return;
    }

    if (input.type === "password") {

        input.type = "text";

        el.classList.remove("fa-eye-slash");
        el.classList.add("fa-eye");

    } else {

        input.type = "password";

        el.classList.remove("fa-eye");
        el.classList.add("fa-eye-slash");
    }
}

function previewImage(event){

    const reader = new FileReader();

    reader.onload = function(){

        document.getElementById("preview").src =
        reader.result;
    }

    reader.readAsDataURL(event.target.files[0]);
}

</script>

</body>
</html>