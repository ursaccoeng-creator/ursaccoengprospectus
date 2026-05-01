<?php
session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ==========================================================
   FLASH MESSAGE
========================================================== */
function flash($msg, $type = "success")
{
    $_SESSION['msg']  = $msg;
    $_SESSION['type'] = $type;
}

/* ==========================================================
   CREATE TABLE IF NOT EXISTS
========================================================== */
mysqli_query($conn, "
CREATE TABLE IF NOT EXISTS curriculum_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT NOT NULL,
    subject_id INT NULL,
    subject_code VARCHAR(100),
    subject_title VARCHAR(255),
    course_id INT,
    year_level VARCHAR(30),
    semester INT,
    units INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

/* ==========================================================
   ADD CURRICULUM
========================================================== */
if (isset($_POST['add_curriculum'])) {

    $course_id = intval($_POST['course_id']);
    $name      = mysqli_real_escape_string($conn, $_POST['curriculum_name']);
    $year      = mysqli_real_escape_string($conn, $_POST['curriculum_year']);

    $check = mysqli_query($conn, "
        SELECT id
        FROM curricula
        WHERE course_id = '$course_id'
        AND curriculum_name = '$name'
        AND curriculum_year = '$year'
    ");

    if (mysqli_num_rows($check) > 0) {

        flash("Curriculum already exists.", "error");

    } else {

        mysqli_query($conn, "
            INSERT INTO curricula
            (
                curriculum_name,
                curriculum_year,
                is_active,
                course_id
            )
            VALUES
            (
                '$name',
                '$year',
                0,
                '$course_id'
            )
        ");

        $cid = mysqli_insert_id($conn);

        $subs = mysqli_query($conn, "
            SELECT *
            FROM subjects
            WHERE course_id = '$course_id'
            ORDER BY year_level, semester, subject_code
        ");

        while ($s = mysqli_fetch_assoc($subs)) {

            mysqli_query($conn, "
                INSERT INTO curriculum_subjects
                (
                    curriculum_id,
                    subject_id,
                    subject_code,
                    subject_title,
                    course_id,
                    year_level,
                    semester,
                    units
                )
                VALUES
                (
                    '$cid',
                    '".$s['id']."',
                    '".$s['subject_code']."',
                    '".$s['subject_title']."',
                    '".$s['course_id']."',
                    '".$s['year_level']."',
                    '".$s['semester']."',
                    '".$s['units']."'
                )
            ");
        }

        flash("Curriculum created successfully.");
    }

    header("Location: curriculum_update.php");
    exit();
}

/* ==========================================================
   USE CURRICULUM
========================================================== */
if (isset($_GET['use'])) {

    $id = intval($_GET['use']);

    $get = mysqli_query($conn, "
        SELECT course_id
        FROM curricula
        WHERE id = '$id'
    ");

    $row = mysqli_fetch_assoc($get);
    $course_id = $row['course_id'];

    mysqli_query($conn, "
        UPDATE curricula
        SET is_active = 0
        WHERE course_id = '$course_id'
    ");

    mysqli_query($conn, "
        UPDATE curricula
        SET is_active = 1
        WHERE id = '$id'
    ");

    flash("Curriculum activated.");

    header("Location: curriculum_update.php");
    exit();
}

/* ==========================================================
   SAVE CURRICULUM
========================================================== */
if (isset($_POST['save_curriculum'])) {

    $id   = intval($_POST['id']);
    $name = mysqli_real_escape_string($conn, $_POST['curriculum_name']);
    $year = mysqli_real_escape_string($conn, $_POST['curriculum_year']);

    mysqli_query($conn, "
        UPDATE curricula
        SET curriculum_name = '$name',
            curriculum_year = '$year'
        WHERE id = '$id'
    ");

    flash("Curriculum updated.");

    header("Location: curriculum_update.php");
    exit();
}

/* ==========================================================
   SAVE SUBJECT
========================================================== */
if (isset($_POST['save_subject'])) {

    $id   = intval($_POST['id']);
    $cid  = intval($_POST['curriculum_id']);
    $yr   = mysqli_real_escape_string($conn, $_POST['year_level']);
    $sem  = intval($_POST['semester']);
    $unit = intval($_POST['units']);

    mysqli_query($conn, "
        UPDATE curriculum_subjects
        SET year_level = '$yr',
            semester = '$sem',
            units = '$unit'
        WHERE id = '$id'
    ");

    flash("Subject updated.");

    header("Location: curriculum_update.php?edit=".$cid);
    exit();
}

/* ==========================================================
   REMOVE SUBJECT
========================================================== */
if (isset($_GET['remove_subject'])) {

    $id  = intval($_GET['remove_subject']);
    $cid = intval($_GET['cid']);

    mysqli_query($conn, "
        DELETE FROM curriculum_subjects
        WHERE id = '$id'
    ");

    flash("Subject removed.");

    header("Location: curriculum_update.php?edit=".$cid);
    exit();
}

/* ==========================================================
   FETCH
========================================================== */
$courses = mysqli_query($conn, "
    SELECT *
    FROM courses
    ORDER BY course_name
");

$curricula = mysqli_query($conn, "
    SELECT curricula.*, courses.course_name
    FROM curricula
    LEFT JOIN courses
        ON courses.id = curricula.course_id
    ORDER BY courses.course_name, curricula.id DESC
");

$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;

/* ==========================================================
   AUTO SYNC SUBJECTS IF EMPTY
========================================================== */
if ($edit_id > 0) {

    $check = mysqli_query($conn, "
        SELECT id
        FROM curriculum_subjects
        WHERE curriculum_id = '$edit_id'
        LIMIT 1
    ");

    if (mysqli_num_rows($check) == 0) {

        $get = mysqli_query($conn, "
            SELECT course_id
            FROM curricula
            WHERE id = '$edit_id'
        ");

        $row = mysqli_fetch_assoc($get);
        $course_id = $row['course_id'];

        $subs = mysqli_query($conn, "
            SELECT *
            FROM subjects
            WHERE course_id = '$course_id'
            ORDER BY year_level, semester, subject_code
        ");

        while ($s = mysqli_fetch_assoc($subs)) {

            mysqli_query($conn, "
                INSERT INTO curriculum_subjects
                (
                    curriculum_id,
                    subject_id,
                    subject_code,
                    subject_title,
                    course_id,
                    year_level,
                    semester,
                    units
                )
                VALUES
                (
                    '$edit_id',
                    '".$s['id']."',
                    '".$s['subject_code']."',
                    '".$s['subject_title']."',
                    '".$s['course_id']."',
                    '".$s['year_level']."',
                    '".$s['semester']."',
                    '".$s['units']."'
                )
            ");
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

    <title>Curriculum Update</title>

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family:Arial, Helvetica, sans-serif;
            background:#f4f7fc;
        }

        .content{
            margin-left:265px;
            padding:30px;
            padding-top:40px;
        }

        .page-title{
            font-size:24px;
            font-weight:700;
            color:#2c5aa0;
            margin-bottom:20px;
        }

        .card{
            background:#fff;
            padding:22px;
            border-radius:18px;
            margin-bottom:20px;
            box-shadow:0 3px 12px rgba(0,0,0,.05);
        }

        input,
        select{
            width:100%;
            height:42px;
            padding:0 12px;
            border:1px solid #dbe6ff;
            border-radius:10px;
            margin-bottom:10px;
            font-size:14px;
        }

        .btn{
            height:42px;
            padding:0 18px;
            border:none;
            border-radius:10px;
            background:#2c5aa0;
            color:#fff;
            cursor:pointer;
            font-size:14px;
            font-weight:600;
        }

        .btn-green{
            background:#27ae60;
        }

        .btn-red{
            background:#e74c3c;
        }

        .actions{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }

        .row{
            padding:14px 0;
            border-bottom:1px solid #eee;
        }

        .row:last-child{
            border-bottom:none;
        }

        .badge{
            background:#e8f5e9;
            color:#27ae60;
            padding:4px 10px;
            border-radius:20px;
            font-size:12px;
            font-weight:700;
        }

        .table-wrap{
            overflow-x:auto;
        }

        table{
            width:100%;
            min-width:700px;
            border-collapse:collapse;
        }

        th,
        td{
            padding:10px;
            border-bottom:1px solid #eee;
            text-align:left;
            font-size:13px;
            vertical-align:middle;
        }

        .modal{
            position:fixed;
            inset:0;
            background:rgba(0,0,0,.45);
            display:flex;
            justify-content:center;
            align-items:center;
            z-index:9999;
            padding:15px;
        }

        .modal-box{
            background:#fff;
            padding:24px;
            border-radius:18px;
            width:340px;
            max-width:100%;
        }

        @media (max-width:1024px){

            .content{
                margin-left:0;
                padding:20px;
                padding-top:85px;
            }

        }

        @media (max-width:768px){

            .content{
                margin-left:0;
                padding:14px;
                padding-top:80px;
            }

            .actions{
                flex-direction:column;
            }

            .btn{
                width:100%;
            }

            table{
                min-width:620px;
            }

        }

    </style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="page-title">
        Curriculum Update
    </div>

    <?php if (isset($_SESSION['msg'])) { ?>

        <div
            class="modal"
            id="msgModal"
        >

            <div class="modal-box">

                <h3>
                    <?php echo $_SESSION['type'] == "error" ? "Warning" : "Success"; ?>
                </h3>

                <p style="margin:12px 0;">
                    <?php echo $_SESSION['msg']; ?>
                </p>

                <button
                    class="btn"
                    onclick="document.getElementById('msgModal').style.display='none'"
                >
                    OK
                </button>

            </div>

        </div>

        <?php
        unset($_SESSION['msg']);
        unset($_SESSION['type']);
        ?>

    <?php } ?>

    <div class="card">

        <form method="POST">

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

            <input
                type="text"
                name="curriculum_name"
                placeholder="Curriculum Name"
                required
            >

            <input
                type="text"
                name="curriculum_year"
                placeholder="2025-2026"
                required
            >

            <button
                class="btn"
                name="add_curriculum"
            >
                Create Curriculum
            </button>

        </form>

    </div>

    <div class="card">

        <?php while ($row = mysqli_fetch_assoc($curricula)) { ?>

            <div class="row">

                <b>
                    <?php echo $row['course_name']; ?> -
                    <?php echo $row['curriculum_name']; ?> -
                    <?php echo $row['curriculum_year']; ?>
                </b>

                <?php if ($row['is_active'] == 1) { ?>
                    <span class="badge">ACTIVE</span>
                <?php } ?>

                <br><br>

                <form method="POST">

                    <input
                        type="hidden"
                        name="id"
                        value="<?php echo $row['id']; ?>"
                    >

                    <input
                        type="text"
                        name="curriculum_name"
                        value="<?php echo $row['curriculum_name']; ?>"
                    >

                    <input
                        type="text"
                        name="curriculum_year"
                        value="<?php echo $row['curriculum_year']; ?>"
                    >

                    <div class="actions">

                        <button
                            class="btn"
                            name="save_curriculum"
                        >
                            Save
                        </button>

                        <a href="?use=<?php echo $row['id']; ?>">
                            <button
                                type="button"
                                class="btn btn-green"
                            >
                                Use
                            </button>
                        </a>

                        <a href="?edit=<?php echo $row['id']; ?>">
                            <button
                                type="button"
                                class="btn"
                            >
                                Edit Subjects
                            </button>
                        </a>

                    </div>

                </form>

            </div>

        <?php } ?>

    </div>

    <?php if ($edit_id > 0) { ?>

        <div class="card">

            <h3 style="margin-bottom:15px;">
                Editable Subjects
            </h3>

            <div class="table-wrap">

                <table>

                    <tr>
                        <th>Code</th>
                        <th>Title</th>
                        <th>Year</th>
                        <th>Sem</th>
                        <th>Units</th>
                        <th>Action</th>
                    </tr>

                    <?php

                    $list = mysqli_query(
                        $conn,
                        "
                        SELECT *
                        FROM curriculum_subjects
                        WHERE curriculum_id = '$edit_id'
                        ORDER BY year_level, semester, subject_code
                        "
                    );

                    while ($s = mysqli_fetch_assoc($list)) {

                    ?>

                        <tr>

                            <form method="POST">

                                <td>
                                    <?php echo $s['subject_code']; ?>
                                </td>

                                <td>
                                    <?php echo $s['subject_title']; ?>
                                </td>

                                <td>

                                    <input
                                        type="text"
                                        name="year_level"
                                        value="<?php echo $s['year_level']; ?>"
                                    >

                                </td>

                                <td>

                                    <select name="semester">

                                        <option
                                            value="1"
                                            <?php if ($s['semester'] == 1) echo "selected"; ?>
                                        >
                                            1st
                                        </option>

                                        <option
                                            value="2"
                                            <?php if ($s['semester'] == 2) echo "selected"; ?>
                                        >
                                            2nd
                                        </option>

                                        <option
                                            value="3"
                                            <?php if ($s['semester'] == 3) echo "selected"; ?>
                                        >
                                            Inter
                                        </option>

                                    </select>

                                </td>

                                <td>

                                    <input
                                        type="number"
                                        name="units"
                                        value="<?php echo $s['units']; ?>"
                                    >

                                </td>

                                <td>

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo $s['id']; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="curriculum_id"
                                        value="<?php echo $edit_id; ?>"
                                    >

                                    <div class="actions">

                                        <button
                                            class="btn"
                                            name="save_subject"
                                        >
                                            Save
                                        </button>

                                        <a href="?remove_subject=<?php echo $s['id']; ?>&cid=<?php echo $edit_id; ?>">
                                            <button
                                                type="button"
                                                class="btn btn-red"
                                            >
                                                Remove
                                            </button>
                                        </a>

                                    </div>

                                </td>

                            </form>

                        </tr>

                    <?php } ?>

                </table>

            </div>

        </div>

    <?php } ?>

</div>

<script>

setTimeout(function () {

    let x =
        document.getElementById("msgModal");

    if (x) {
        x.style.display = "none";
    }

}, 2500);

</script>

</body>
</html>