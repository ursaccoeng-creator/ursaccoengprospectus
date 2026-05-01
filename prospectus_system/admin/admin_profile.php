<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['admin'];


/* =========================
   GET ADMIN
========================= */

$query = mysqli_query(
    $conn,
    "SELECT * 
     FROM admin 
     WHERE username = '$username'"
);

$admin = mysqli_fetch_assoc($query);


/* =========================
   UPDATE NAME
========================= */

if (isset($_POST['update_name'])) {

    $new_name = $_POST['username'];

    mysqli_query(
        $conn,
        "UPDATE admin 
         SET username = '$new_name'
         WHERE username = '$username'"
    );

    $_SESSION['admin'] = $new_name;

    header("Location: admin_profile.php");
    exit();
}


/* =========================
   CHANGE PASSWORD
========================= */

if (isset($_POST['change_password'])) {

    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];

    if ($current == $admin['password']) {

        mysqli_query(
            $conn,
            "UPDATE admin 
             SET password = '$new'
             WHERE username = '$username'"
        );

        $success = "Password updated successfully";

    } else {
        $error = "Current password incorrect";
    }
}


/* =========================
   REPLACE PROGRAM HEAD
========================= */

if (isset($_POST['replace_admin'])) {

    $new_admin = $_POST['new_admin'];
    $new_pass  = $_POST['new_password'];

    mysqli_query(
        $conn,
        "INSERT INTO admin (username, password, status)
         VALUES ('$new_admin', '$new_pass', 'active')"
    );

    mysqli_query(
        $conn,
        "UPDATE admin 
         SET status = 'inactive'
         WHERE username = '$username'"
    );

    session_destroy();

    header("Location: ../login.php");
    exit();
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

<title>Admin Profile</title>

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
    margin-bottom:20px;
    font-weight:700;
    color:#2c5aa0;
    line-height:1.3;
}

.card{
    background:#fff;
    padding:22px;
    border-radius:18px;
    margin-bottom:20px;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
}

.card h3{
    margin:0 0 16px;
    color:#2c5aa0;
    font-size:19px;
    line-height:1.3;
}

.input{
    width:100%;
    height:44px;
    padding:0 12px;
    margin-top:5px;
    margin-bottom:12px;
    border:1px solid #dbe6ff;
    border-radius:10px;
    background:#fff;
    font-size:14px;
    transition:.2s;
}

textarea.input{
    height:auto;
    min-height:110px;
    padding:12px;
    resize:vertical;
}

.input:focus{
    outline:none;
    border-color:#2c5aa0;
    box-shadow:0 0 0 3px rgba(44,90,160,.08);
}

.btn{
    padding:11px 18px;
    border:none;
    border-radius:10px;
    background:#2c5aa0;
    color:#fff;
    cursor:pointer;
    font-size:14px;
    font-weight:600;
    transition:.2s;
}

.btn:hover{
    background:#1f4580;
}

.btn-danger{
    background:#e74c3c;
}

.btn-danger:hover{
    background:#c0392b;
}

.message,
.error{
    margin-bottom:12px;
    padding:12px 14px;
    border-radius:10px;
    font-size:14px;
    font-weight:600;
    line-height:1.4;
}

.message{
    background:#eafaf1;
    color:#27ae60;
    border:1px solid #c8efd8;
}

.error{
    background:#fff0f0;
    color:#e74c3c;
    border:1px solid #ffd1d1;
}

/* TABLET */

@media (max-width:1024px){

    .content{
        margin-left:220px;
        margin-right:15px;
        padding:24px;
        padding-top:34px;
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

    .page-title{
        font-size:20px;
        margin-bottom:16px;
    }

    .card{
        padding:18px;
        border-radius:16px;
    }

    .card h3{
        font-size:18px;
        margin-bottom:14px;
    }

    .btn{
        width:100%;
    }

}

/* SMALL MOBILE */

@media (max-width:480px){

    .content{
        padding:12px;
        padding-top:84px;
    }

    .page-title{
        font-size:18px;
    }

    .card{
        padding:15px;
    }

    .card h3{
        font-size:17px;
    }

}

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="page-title">
        Admin Profile
    </div>


    <div class="card">

        <h3>Admin Information</h3>

        <form method="POST">

            <label>Username</label>

            <input 
                type="text"
                name="username"
                class="input"
                value="<?php echo $admin['username']; ?>"
                required
            >

            <button 
                class="btn"
                name="update_name"
            >
                Update Name
            </button>

        </form>

    </div>


    <div class="card">

        <h3>Change Password</h3>

        <?php if (isset($success)) { ?>
            <div class="message"><?php echo $success; ?></div>
        <?php } ?>

        <?php if (isset($error)) { ?>
            <div class="error"><?php echo $error; ?></div>
        <?php } ?>

        <form method="POST">

            <label>Current Password</label>

            <input 
                type="password"
                name="current_password"
                class="input"
                required
            >

            <label>New Password</label>

            <input 
                type="password"
                name="new_password"
                class="input"
                required
            >

            <button 
                class="btn"
                name="change_password"
            >
                Change Password
            </button>

        </form>

    </div>


    <div class="card">

        <h3>Replace Program Head</h3>

        <p>
            Create a new admin before deactivating this account.
        </p>

        <form method="POST">

            <label>New Admin Username</label>

            <input 
                type="text"
                name="new_admin"
                class="input"
                required
            >

            <label>New Admin Password</label>

            <input 
                type="password"
                name="new_password"
                class="input"
                required
            >

            <button 
                class="btn btn-danger"
                name="replace_admin"
                onclick="return confirm('Replace current program head?')"
            >
                Replace Program Head
            </button>

        </form>

    </div>

</div>

</body>
</html>