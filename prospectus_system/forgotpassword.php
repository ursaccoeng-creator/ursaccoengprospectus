<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "PHPMailer/PHPMailer-master/src/PHPMailer.php";
require "PHPMailer/PHPMailer-master/src/SMTP.php";
require "PHPMailer/PHPMailer-master/src/Exception.php";

include "includes/db.php";

$message = "";
$message_type = "";

/* ================= RESET PASSWORD ================= */

if (isset($_POST['reset'])) {

    $email = mysqli_real_escape_string(
        $conn,
        trim($_POST['email'])
    );

    $query = "
        SELECT id, full_name, email
        FROM students
        WHERE email = '$email'
        LIMIT 1
    ";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {

        $row = mysqli_fetch_assoc($result);

        $full_name = $row['full_name'];

        $token = md5(
            uniqid() .
            time() .
            rand()
        );

        $update = mysqli_query(
            $conn,
            "
            UPDATE students
            SET reset_token = '$token'
            WHERE email = '$email'
            "
        );

        if ($update) {

            $reset_link =
                "https://ursaccoengprospectus.wuaze.com/resetpassword.php?token=$token";

            $mail = new PHPMailer(true);

            try {

                /* SAME SETTINGS AS WORKING REGISTER FILE */

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
                    "Reset Password Request";

                $mail->Body = "
                    Hello <b>$full_name</b>, <br><br>

                    We received a request to reset your password.<br><br>

                    Click the button below:<br><br>

                    <a href='$reset_link'
                    style='
                        background:#1e5aa8;
                        color:#fff;
                        padding:10px 18px;
                        text-decoration:none;
                        border-radius:6px;
                        display:inline-block;
                    '>
                        Reset Password
                    </a>

                    <br><br>

                    If you did not request this,
                    simply ignore this email.

                    <br><br>

                    Thank you.
                ";

                $mail->AltBody =
                    "Reset Password Link: $reset_link";

                if ($mail->send()) {

                    $message =
                        "Reset link successfully sent to your email.";

                    $message_type =
                        "success";

                } else {

                    $message =
                        "Unable to send email.";

                    $message_type =
                        "error";
                }

            } catch (Exception $e) {

                $message =
                    "Mailer Error: " .
                    $mail->ErrorInfo;

                $message_type =
                    "error";
            }

        } else {

            $message =
                "Failed to generate reset token.";

            $message_type =
                "error";
        }

    } else {

        $message =
            "Email not found.";

        $message_type =
            "error";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0"
>

<meta
name="format-detection"
content="telephone=no"
>

<title>Forgot Password</title>

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
    width:100%;
    color:#fff;
    padding:16px;
    font-size:15px;
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
    padding:25px 14px;
}

/* CARD */

.card{
    width:100%;
    max-width:420px;
    background:#fff;
    padding:22px 18px;
    border-radius:14px;
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
    padding:12px;
    border-radius:8px;
    margin-bottom:14px;
    font-size:13px;
    line-height:1.4;
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
    margin-bottom:14px;
}

.group i{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    color:#999;
    font-size:14px;
}

.group input{
    width:100%;
    height:45px;
    padding:0 12px 0 40px;
    border-radius:8px;
    border:1px solid #ddd;
    font-size:14px;
    outline:none;
    background:#fff;
}

.group input:focus{
    border-color:#1e5aa8;
}

/* BUTTON */

.btn{
    width:100%;
    height:45px;
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

.btn:hover{
    opacity:.95;
}

/* LINK */

.back-login{
    display:block;
    text-align:center;
    margin-top:15px;
    color:#1e5aa8;
    text-decoration:none;
    font-size:13px;
    font-weight:600;
}

/* DESKTOP */

@media (min-width:768px){

    .header{
        padding:24px 28px;
        font-size:18px;
    }

    .wrapper{
        padding:70px 20px;
    }

    .card{
        max-width:430px;
        padding:30px;
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
                Forgot Password
            </div>

            <?php if ($message != "") { ?>

                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>

            <?php } ?>

            <form method="POST">

                <div class="group">

                    <i class="fa fa-envelope"></i>

                    <input
                        type="email"
                        name="email"
                        placeholder="Enter your email"
                        required
                    >

                </div>

                <button
                    class="btn"
                    name="reset"
                    type="submit"
                >
                    Send Reset Link
                </button>

                <a
                    class="back-login"
                	href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/index.php"
                >
                    ← Back to Login
                </a>

            </form>

        </div>

    </div>

</body>
</html>