<?php

include "includes/db.php";

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

$message = "";
$message_type = "";

$token = isset($_GET['token'])
    ? mysqli_real_escape_string(
        $conn,
        trim($_GET['token'])
    )
    : "";

/* ================= CHECK TOKEN ================= */

$check_token = mysqli_query(
    $conn,
    "
    SELECT id
    FROM students
    WHERE reset_token = '$token'
    LIMIT 1
    "
);

if (
    $token == "" ||
    mysqli_num_rows($check_token) == 0
) {

    $message = "Invalid or expired reset link.";
    $message_type = "error";
}

/* ================= UPDATE PASSWORD ================= */

if (
    isset($_POST['update']) &&
    $message == ""
) {

    $password =
        trim($_POST['password']);

    $confirm_password =
        trim($_POST['confirm_password']);

    if (
        $password == "" ||
        $confirm_password == ""
    ) {

        $message =
            "Please fill in all fields.";

        $message_type = "error";

    } elseif (
        $password != $confirm_password
    ) {

        $message =
            "Passwords do not match.";

        $message_type = "error";

    } else {

        $hashed_password =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        mysqli_query(
            $conn,
            "
            UPDATE students
            SET password = '$hashed_password',
                reset_token = NULL
            WHERE reset_token = '$token'
            "
        );

        echo "
            <script>
                alert('Password Updated Successfully');
                window.location =
                'https://" .
                $_SERVER['HTTP_HOST'] .
                "/index.php';
            </script>
        ";

        exit();
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

<title>Reset Password</title>

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
}

body{
    font-family:Arial, Helvetica, sans-serif;
    background:#f5f7fb;
}

/* HEADER */
.header{
    color:#fff;
    padding:14px 16px;
    font-size:14px;
    font-weight:700;
    background:linear-gradient(
        160deg,
        #1e4f8a 0%,
        #1e5aa8 45%,
        #1f6ed4 100%
    );
}

/* WRAPPER */
.wrapper{
    display:flex;
    justify-content:center;
    padding:20px 12px;
}

/* CARD */
.card{
    width:100%;
    max-width:420px;
    background:#fff;
    padding:20px 16px;
    border-radius:12px;
    box-shadow:0 8px 25px rgba(0,0,0,.08);
}

/* TITLE */
.title{
    text-align:center;
    font-size:22px;
    margin-bottom:18px;
    font-weight:700;
    color:#1e5aa8;
}

/* MESSAGE */
.message{
    padding:10px;
    border-radius:8px;
    margin-bottom:14px;
    font-size:13px;
}

.success{
    background:#eef4ff;
    color:#1e5aa8;
    border:1px solid #d6e4ff;
}

.error{
    background:#fff0f0;
    color:#d32f2f;
    border:1px solid #ffcdd2;
}

/* FORM */
.group{
    position:relative;
    margin-bottom:12px;
}

.group i:first-child{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    color:#999;
    font-size:14px;
}

.group input{
    width:100%;
    height:44px;
    padding:0 40px 0 40px;
    border-radius:8px;
    border:1px solid #ddd;
    font-size:14px;
    outline:none;
    background:#fff;
}

.group input:focus{
    border-color:#1e5aa8;
}

/* EYE */
.eye{
    position:absolute;
    right:14px;
    top:50%;
    transform:translateY(-50%);
    color:#777;
    cursor:pointer;
    font-size:14px;
}

/* BUTTON */
.btn{
    width:100%;
    height:44px;
    border:none;
    color:#fff;
    border-radius:8px;
    cursor:pointer;
    font-size:14px;
    font-weight:700;
    background:linear-gradient(
        160deg,
        #1e4f8a 0%,
        #1e5aa8 45%,
        #1f6ed4 100%
    );
}

/* LINK */
.back-login{
    display:block;
    text-align:center;
    margin-top:14px;
    color:#1e5aa8;
    text-decoration:none;
    font-size:13px;
    font-weight:500;
}

/* TABLET / DESKTOP */
@media (min-width:768px){

    .header{
        padding:22px 24px;
        font-size:18px;
    }

    .wrapper{
        padding:70px 20px;
    }

    .card{
        max-width:430px;
        padding:30px;
        border-radius:14px;
    }

    .title{
        font-size:26px;
    }

    .group input,
    .btn{
        height:46px;
        font-size:15px;
    }

    .back-login{
        font-size:14px;
    }

}

</style>

</head>

<body>

<div class="header">
    Prospectus System
</div>

<div class="wrapper">

    <div class="card">

        <div class="title">
            Change Password
        </div>

        <?php if ($message != "") { ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php } ?>

        <?php if ($message_type != "error" || $message == "") { ?>

        <form method="POST">

            <div class="group">

                <i class="fa fa-lock"></i>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="New Password"
                    required
                >

                <span
                    class="eye"
                    onclick="togglePassword('password',this)"
                >
                    <i class="fa fa-eye-slash"></i>
                </span>

            </div>

            <div class="group">

                <i class="fa fa-lock"></i>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm Password"
                    required
                >

                <span
                    class="eye"
                    onclick="togglePassword('confirm_password',this)"
                >
                    <i class="fa fa-eye-slash"></i>
                </span>

            </div>

            <button
                class="btn"
                name="update"
            >
                Update Password
            </button>

        </form>

        <?php } ?>

        <a
            class="back-login"
            href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/index.php"
        >
            ← Back to Login
        </a>

    </div>

</div>

<script>

function togglePassword(id, el){

    let input =
        document.getElementById(id);

    let icon =
        el.querySelector("i");

    if(input.type === "password"){

        input.type = "text";
        icon.className = "fa fa-eye";

    }else{

        input.type = "password";
        icon.className = "fa fa-eye-slash";
    }
}

</script>

</body>
</html>