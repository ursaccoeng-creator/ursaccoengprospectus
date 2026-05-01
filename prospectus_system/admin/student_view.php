<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'];

$student = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM students
        WHERE id = '$id'
        "
    )
);

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

/* CONTENT */

.content{
    margin-left:265px;
    margin-right:25px;
    padding:35px;
    padding-top:40px;
}

/* CARD */

.card{
    background:#fff;
    padding:30px;
    border-radius:20px;
    max-width:1050px;
    margin:0 auto;
    box-shadow:0 8px 25px rgba(0,0,0,.05);
}

/* HEADER */

.top-bar{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:25px;
    flex-wrap:wrap;
}

.page-title{
    font-size:24px;
    font-weight:700;
    color:#2c5aa0;
    line-height:1.3;
}

.btn-back{
    background:#eef3ff;
    color:#2c5aa0;
    padding:8px 16px;
    border-radius:10px;
    text-decoration:none;
    font-size:13px;
    border:1px solid #dbe6ff;
    transition:.2s;
}

.btn-back:hover{
    background:#dfe8ff;
}

/* GRID */

.info-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
}

.info-box{
    background:#f8fbff;
    padding:16px;
    border-radius:14px;
    border:1px solid #edf2ff;
    transition:.2s;
}

.info-box:hover{
    background:#f2f6ff;
}

.label{
    font-size:12px;
    color:#8a8a8a;
    margin-bottom:5px;
    font-weight:600;
}

.value{
    font-size:15px;
    font-weight:600;
    color:#222;
    line-height:1.5;
    word-break:break-word;
}

/* ADDRESS FULL WIDTH */

.info-box.full{
    grid-column:span 3;
}

/* BUTTON GRID */

.actions{
    margin-top:25px;
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
}

.btn{
    padding:15px;
    border-radius:12px;
    text-decoration:none;
    color:#fff;
    font-size:14px;
    text-align:center;
    font-weight:600;
    transition:.2s;
}

.btn:hover{
    transform:translateY(-1px);
}

.btn i{
    margin-right:8px;
}

.btn-primary{
    background:#2c5aa0;
}

.btn-warning{
    background:#f39c12;
}

.btn-success{
    background:#27ae60;
}

/* TABLET */

@media (max-width:1024px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:22px;
        padding-top:88px;
    }

    .card{
        max-width:100%;
        padding:24px;
    }

    .info-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .info-box.full{
        grid-column:span 2;
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

    .card{
        padding:18px;
        border-radius:16px;
    }

    .top-bar{
        flex-direction:column;
        align-items:flex-start;
        gap:8px;
        margin-bottom:18px;
    }

    .page-title{
        font-size:20px;
    }

    .info-grid{
        grid-template-columns:1fr;
        gap:12px;
    }

    .info-box.full{
        grid-column:span 1;
    }

    .actions{
        grid-template-columns:1fr;
        gap:12px;
    }

    .btn{
        padding:14px;
    }

}

/* SMALL MOBILE */

@media (max-width:480px){

    .content{
        padding:12px;
        padding-top:82px;
    }

    .card{
        padding:15px;
    }

    .page-title{
        font-size:18px;
    }

    .value{
        font-size:14px;
    }

    .btn{
        font-size:13px;
    }

}

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="top-bar">

        <a 
            href="student_records.php"
            class="btn-back"
        >
            ← Back
        </a>

        <div class="page-title">
            Student Profile
        </div>

    </div>


    <?php
    // SAFE COURSE NAME FETCH
    $course_name = "No Course";

    if (!empty($student['course_id'])) {
        $c = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT course_name FROM courses WHERE id = '{$student['course_id']}'")
        );

        if ($c) {
            $course_name = $c['course_name'];
        }
    }
    ?>


    <div class="card">

        <div class="info-grid">

            <div class="info-box">
                <div class="label">Student ID</div>
                <div class="value">
                    <?php echo $student['student_id']; ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Full Name</div>
                <div class="value">
                    <?php echo $student['full_name']; ?>
                </div>
            </div>

            
            <div class="info-box">
                <div class="label">Course</div>
                <div class="value">
                    <?php echo $course_name; ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Year Level</div>
                <div class="value">
                    <?php echo $student['year_level']; ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Section</div>
                <div class="value">
                    <?php echo $student['section']; ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Entry SY</div>
                <div class="value">
                    <?php echo $student['entry_sy']; ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Status</div>
                <div class="value">
                    <?php echo $student['current_status']; ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Email</div>
                <div class="value">
                    <?php echo $student['email']; ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Contact Number</div>
                <div class="value">
                    <?php echo $student['contact_number']; ?>
                </div>
            </div>

            <div class="info-box full">
                <div class="label">Address</div>
                <div class="value">
                    <?php echo $student['address']; ?>
                </div>
            </div>

        </div>


        <div class="actions">

            <a 
                href="student_subjects.php?id=<?php echo $id; ?>" 
                class="btn btn-primary"
            >
                <i class="fa-solid fa-book"></i>
                View Subjects
            </a>

            <a 
                href="student_enrollment.php?id=<?php echo $id; ?>" 
                class="btn btn-warning"
            >
                <i class="fa-solid fa-file-lines"></i>
                View Enrollment Form
            </a>

            <a 
                href="student_edit.php?id=<?php echo $id; ?>" 
                class="btn btn-success"
            >
                <i class="fa-solid fa-pen-to-square"></i>
                Edit Student
            </a>

        </div>

    </div>

</div>

</body>
</html>