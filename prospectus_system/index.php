<?php

session_start();

/* ================= FORCE HTTPS ================= */
if (
    empty($_SERVER['HTTPS']) ||
    $_SERVER['HTTPS'] === 'off'
) {
    $redirect =
        "https://" .
        $_SERVER['HTTP_HOST'] .
        $_SERVER['REQUEST_URI'];

    header("Location: " . $redirect);
    exit();
}

/* ================= DATABASE ================= */
include "prospectus_system/includes/db.php";

/* ================= BASE PATH ================= */
$base_url = "/prospectus_system/";

/* ================= REMEMBER USER ================= */
$remember_user = $_COOKIE['remember_user'] ?? '';

if (!isset($_COOKIE['remember_user'])) {
    $remember_user = "";
}

$message = "";
$message_type = "";

/* ================= LOGIN ================= */
if (isset($_POST['login'])) {

    $username = trim($_POST['student_id']);
    $password = $_POST['password'];

    try {

        /* ================= ADMIN LOGIN ================= */
        $stmt = $conn->prepare("
            SELECT *
            FROM admin
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username
        ]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {

            // NOTE: still plain text compare (same as your original)
            if ($password == $admin['password']) {

                $_SESSION['admin'] = $admin['username'];

                if (isset($_POST['remember'])) {

                    setcookie(
                        "remember_user",
                        $username,
                        time() + (86400 * 30),
                        "/",
                        "",
                        true,
                        true
                    );

                } else {

                    setcookie(
                        "remember_user",
                        "",
                        time() - 3600,
                        "/",
                        "",
                        true,
                        true
                    );
                }

                $_SESSION['login_success'] = "Login successful!";
                $_SESSION['redirect_to'] =
                    $base_url . "admin/admin.php";

                header(
                    "Location: https://" .
                    $_SERVER['HTTP_HOST'] .
                    "/index.php"
                );
                exit();
            }
        }

        /* ================= STUDENT LOGIN ================= */
        $stmt = $conn->prepare("
            SELECT *
            FROM students
            WHERE student_id = :student_id
            LIMIT 1
        ");

        $stmt->execute([
            ':student_id' => $username
        ]);

        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {

            if (
                password_verify(
                    $password,
                    $student['password']
                )
            ) {

                $_SESSION['student_id'] =
                    $student['student_id'];

                if (isset($_POST['remember'])) {

                    setcookie(
                        "remember_user",
                        $username,
                        time() + (86400 * 30),
                        "/",
                        "",
                        true,
                        true
                    );

                } else {

                    setcookie(
                        "remember_user",
                        "",
                        time() - 3600,
                        "/",
                        "",
                        true,
                        true
                    );
                }

                $_SESSION['login_success'] =
                    "Login successful!";

                $_SESSION['redirect_to'] =
                    $base_url . "dashboard.php";

                header(
                    "Location: https://" .
                    $_SERVER['HTTP_HOST'] .
                    "/index.php"
                );
                exit();
            }
        }

    } catch (PDOException $e) {
        die("Login error: " . $e->getMessage());
    }

    $message = "Invalid login credentials.";
    $message_type = "error";
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

    <title>Prospectus System</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
        }

        .header {
            display: none;
            color: #fff;
            padding: 20px;
            font-weight: bold;
            text-align: center;

            background: linear-gradient(
                160deg,
                #1e4f8a 0%,
                #1e5aa8 45%,
                #1f6ed4 100%
            );
        }

        .login-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .login-left {
            flex: 1;
            background:
                linear-gradient(
                    rgba(8,25,70,.85),
                    rgba(8,25,70,.95)
                ),
                url("prospectus_system/img/login.png");

            background-size: cover;
            background-position: center;
            color: #fff;
            display: flex;
            align-items: center;
            padding: 60px;
        }

        .left-content {
            max-width: 520px;
        }

        .badge {
            display: inline-block;
            background: rgba(255,255,255,.1);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .left-title {
            font-size: 48px;
            font-weight: 800;
            line-height: 1.1;
        }

        .left-sub {
            margin-top: 20px;
            opacity: .9;
        }

        .login-right {
            width: 420px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            padding: 35px;
        }

        .login-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .message {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .error {
            background: #fff0f0;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }

        .form-group {
            margin-bottom: 15px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .form-group input {
            width: 100%;
            padding: 12px 12px 12px 35px;
            border-radius: 6px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }

        .remember {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .remember a {
            text-decoration: none;
            color: #1e5aa8;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            border: none;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;

            background: linear-gradient(
                160deg,
                #1e4f8a 0%,
                #1e5aa8 45%,
                #1f6ed4 100%
            );
        }

        .create-account {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #1e5aa8;
            text-decoration: none;
            font-size: 14px;
        }

        .login-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .login-modal-card {
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            width: 320px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,.25);
        }

        .login-modal-icon {
            width: 60px;
            height: 60px;
            margin: auto;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
            margin-bottom: 10px;

            background: linear-gradient(
                160deg,
                #1e4f8a 0%,
                #1e5aa8 45%,
                #1f6ed4 100%
            );
        }

        .login-modal-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .login-modal-text {
            font-size: 14px;
            color: #555;
            margin-bottom: 20px;
        }

        .login-modal-btn {
            padding: 10px 20px;
            border: none;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;

            background: linear-gradient(
                160deg,
                #1e4f8a 0%,
                #1e5aa8 45%,
                #1f6ed4 100%
            );
        }

        @media (max-width: 768px) {

            .header {
                display: block;
            }

            .login-wrapper {
                display: block;
                padding: 20px;
                margin-top: 20px;
            }

            .login-left {
                display: none;
            }

            .login-right {
                width: 100%;
            }

            .login-card {
                padding: 25px;
            }
        }

    </style>

</head>

<body>

<div class="header">
    PROSPECTUS SYSTEM – COLLEGE OF ENGINEERING
</div>

<div class="login-wrapper">

    <div class="login-left">

        <div class="left-content">

            <div class="badge">
                UNIVERSITY OF RIZAL SYSTEM - ANTIPOLO CAMPUS
            </div>

            <div class="left-title">
                College of Engineering<br>
                Prospectus System
            </div>

            <div class="left-sub">
                Manage curriculum, subjects, and academic planning
                for the College of Engineering.
            </div>

        </div>

    </div>

    <div class="login-right">

        <div class="login-card">

            <div class="login-title">
                Welcome back
            </div>

            <?php if ($message != "") { ?>

                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>

            <?php } ?>

            <form method="POST">

                <div class="form-group">

                    <i class="fa fa-user"></i>

                    <input
                        type="text"
                        name="student_id"
                        placeholder="Student ID"
                        value="<?php echo htmlspecialchars($remember_user); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <i class="fa fa-lock"></i>

                    <input
                        type="password"
                        name="password"
                        placeholder="Password"
                        required
                    >

                </div>

                <div class="remember">

                    <label>

                        <input
                            type="checkbox"
                            name="remember"
                            <?php if (!empty($remember_user)) echo "checked"; ?>
                        >

                        Remember Me

                    </label>

                    <a href="https://<?php echo $_SERVER['HTTP_HOST'] . $base_url; ?>forgotpassword.php">
                        Forgot Password?
                    </a>

                </div>

                <button
                    class="login-btn"
                    name="login"
                >
                    Sign In
                </button>

                <a
                    class="create-account"
                    href="https://<?php echo $_SERVER['HTTP_HOST'] . $base_url; ?>register.php"
                >
                    Get Your Account
                </a>

            </form>

        </div>

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

    window.location.href =
        "https://<?php
            echo $_SERVER['HTTP_HOST'] .
            $_SESSION['redirect_to'];
        ?>";
}

</script>

<?php
unset($_SESSION['login_success']);
} ?>

</body>
</html>
