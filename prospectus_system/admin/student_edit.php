<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

$id = (int)$_GET['id'];

/* ================= STUDENT ================= */
$student = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "
        SELECT 
            s.*,
            c.course_name,
            cu.curriculum_name
        FROM students s
        LEFT JOIN courses c
            ON c.id = s.course_id
        LEFT JOIN curricula cu
            ON cu.id = s.curriculum_id
        WHERE s.id = '$id'
        LIMIT 1
        "
    )
);

if (!$student) {
    die("Student not found.");
}

/* ================= COURSE ID ================= */
$course_id = (int)$student['course_id'];

/* ================= COURSES ================= */
$courses = mysqli_query(
    $conn,
    "
    SELECT *
    FROM courses
    ORDER BY course_name ASC
    "
);

/* ================= CURRICULA ================= */
$curricula = mysqli_query(
    $conn,
    "
    SELECT *
    FROM curricula
    WHERE course_id = '$course_id'
    ORDER BY curriculum_name ASC
    "
);

/* ================= YEARS ================= */
$years = mysqli_query(
    $conn,
    "
    SELECT DISTINCT year_name
    FROM year_levels
    WHERE course_id = '$course_id'
    ORDER BY CAST(year_name AS UNSIGNED) ASC
    "
);

/* ================= SECTIONS ================= */
$sections = mysqli_query(
    $conn,
    "
    SELECT DISTINCT section_name
    FROM sections
    WHERE course_id = '$course_id'
    ORDER BY section_name ASC
    "
);

/* ================= SAVE ================= */
if (isset($_POST['save'])) {

    $name          = mysqli_real_escape_string($conn, $_POST['name']);
    $course_id     = (int)$_POST['course_id'];
    $curriculum_id = (int)$_POST['curriculum_id'];
    $year          = mysqli_real_escape_string($conn, $_POST['year']);
    $section       = mysqli_real_escape_string($conn, $_POST['section']);
    $status        = mysqli_real_escape_string($conn, $_POST['status']);

    /* ================= VALIDATE CURRICULUM ================= */
    $checkCurriculum = mysqli_query(
        $conn,
        "
        SELECT id
        FROM curricula
        WHERE id = '$curriculum_id'
        AND course_id = '$course_id'
        LIMIT 1
        "
    );

    if (mysqli_num_rows($checkCurriculum) == 0) {

        $active = mysqli_query(
            $conn,
            "
            SELECT id
            FROM curricula
            WHERE course_id = '$course_id'
            AND is_active = 1
            LIMIT 1
            "
        );

        if ($a = mysqli_fetch_assoc($active)) {
            $curriculum_id = (int)$a['id'];
        } else {
            $curriculum_id = 0;
        }
    }

    mysqli_query(
        $conn,
        "
        UPDATE students SET
            full_name      = '$name',
            course_id      = '$course_id',
            curriculum_id  = '$curriculum_id',
            year_level     = '$year',
            section        = '$section',
            current_status = '$status'
        WHERE id = '$id'
        "
    );

    header("Location: student_view.php?id=$id");
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

<title>Edit Student</title>

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

.top-bar{
    display:flex;
    align-items:center;
    gap:15px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.page-title{
    font-size:22px;
    font-weight:700;
    color:#2c5aa0;
    line-height:1.3;
}

.btn-back{
    background:#eef3ff;
    color:#2c5aa0;
    padding:8px 14px;
    border-radius:10px;
    text-decoration:none;
    font-size:13px;
    border:1px solid #dbe6ff;
    transition:.2s;
}

.btn-back:hover{
    background:#dfe8ff;
}

.form-card{
    background:#fff;
    padding:25px;
    border-radius:18px;
    max-width:720px;
    margin:auto;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
}

.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
}

.form-group{
    display:flex;
    flex-direction:column;
}

.form-group.full{
    grid-column:span 2;
}

label{
    font-size:13px;
    margin-bottom:6px;
    color:#555;
    font-weight:600;
}

input,
select{
    width:100%;
    height:44px;
    padding:0 12px;
    border-radius:10px;
    border:1px solid #dbe6ff;
    font-size:14px;
    background:#fff;
    transition:.2s;
}

input:focus,
select:focus{
    outline:none;
    border-color:#2c5aa0;
    box-shadow:0 0 0 3px rgba(44,90,160,.08);
}

.form-actions{
    margin-top:20px;
    display:flex;
    gap:10px;
    justify-content:center;
    flex-wrap:wrap;
}

.btn{
    width:180px;
    height:42px;
    border-radius:10px;
    border:none;
    color:#fff;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
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
    text-decoration:none;
    display:flex;
    align-items:center;
    justify-content:center;
}

.btn-cancel:hover{
    background:#7f8c8d;
}

/* TABLET */

@media (max-width:1024px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:22px;
        padding-top:88px;
    }

    .form-card{
        max-width:100%;
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
        gap:10px;
        margin-bottom:16px;
    }

    .page-title{
        font-size:20px;
    }

    .form-card{
        padding:18px;
        border-radius:15px;
    }

    .form-grid{
        grid-template-columns:1fr;
        gap:14px;
    }

    .form-group.full{
        grid-column:span 1;
    }

    .form-actions{
        flex-direction:column;
        align-items:stretch;
    }

    .btn{
        width:100%;
        max-width:100%;
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

    .form-card{
        padding:15px;
    }

    input,
    select{
        font-size:13px;
    }

}

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="top-bar">

        <a href="student_view.php?id=<?php echo $id; ?>" class="btn-back">
            Back
        </a>

        <div class="page-title">
            Edit Student
        </div>

    </div>

    <form method="POST" class="form-card">

        <div class="form-grid">

            <div class="form-group full">
                <label>Full Name</label>
                <input
                    type="text"
                    name="name"
                    value="<?php echo $student['full_name']; ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Course</label>
                <select name="course_id" required>

                    <?php while ($c = mysqli_fetch_assoc($courses)) { ?>

                        <option
                            value="<?php echo $c['id']; ?>"
                            <?php if ($student['course_id'] == $c['id']) echo "selected"; ?>
                        >
                            <?php echo $c['course_name']; ?>
                        </option>

                    <?php } ?>

                </select>
            </div>

            <div class="form-group">
                <label>Curriculum</label>
                <select name="curriculum_id" required>

                    <?php while ($cu = mysqli_fetch_assoc($curricula)) { ?>

                        <option
                            value="<?php echo $cu['id']; ?>"
                            <?php if ($student['curriculum_id'] == $cu['id']) echo "selected"; ?>
                        >
                            <?php echo $cu['curriculum_name']; ?>
                        </option>

                    <?php } ?>

                </select>
            </div>

            <div class="form-group">
                <label>Year</label>
                <select name="year" required>

                    <?php while ($y = mysqli_fetch_assoc($years)) { ?>

                        <option
                            value="<?php echo $y['year_name']; ?>"
                            <?php if ($student['year_level'] == $y['year_name']) echo "selected"; ?>
                        >
                            Year <?php echo $y['year_name']; ?>
                        </option>

                    <?php } ?>

                </select>
            </div>

            <div class="form-group">
                <label>Section</label>
                <select name="section" required>

                    <?php while ($s = mysqli_fetch_assoc($sections)) { ?>

                        <option
                            value="<?php echo $s['section_name']; ?>"
                            <?php if ($student['section'] == $s['section_name']) echo "selected"; ?>
                        >
                            <?php echo $s['section_name']; ?>
                        </option>

                    <?php } ?>

                </select>
            </div>

            <div class="form-group full">
                <label>Status</label>

                <select name="status">

                    <option
                        value="Regular"
                        <?php if ($student['current_status']=="Regular") echo "selected"; ?>
                    >
                        Regular
                    </option>

                    <option
                        value="Irregular"
                        <?php if ($student['current_status']=="Irregular") echo "selected"; ?>
                    >
                        Irregular
                    </option>

                </select>

            </div>

        </div>

        <div class="form-actions">

            <button type="submit" name="save" class="btn btn-save">
                Save
            </button>

            <a href="student_view.php?id=<?php echo $id; ?>" class="btn btn-cancel">
                Cancel
            </a>

        </div>

    </form>

</div>

</body>
</html>