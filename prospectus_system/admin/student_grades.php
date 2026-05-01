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
        "SELECT * FROM students WHERE id = '$id'"
    )
);

$semester = $_GET['semester'] ?? "1";
$year     = $_GET['year'] ?? $student['year_level'];

$query = "
    SELECT *
    FROM grades
    WHERE student_id = '$id'
    AND semester = '$semester'
    AND year_level = '$year'
    ORDER BY subject_code ASC
";

$result = mysqli_query($conn, $query);

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

<title>Student Enrollment</title>

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
    color:#333;
}

.content{
    margin-left:265px;
    margin-right:25px;
    padding:30px;
    padding-top:40px;
}

/* HEADER */

.top-bar{
    display:flex;
    align-items:center;
    gap:15px;
    margin-bottom:22px;
    flex-wrap:wrap;
}

.page-title{
    font-size:24px;
    font-weight:700;
    color:#2c5aa0;
    margin:0;
    line-height:1.3;
}

.btn-back{
    background:#eef3ff;
    color:#2c5aa0;
    padding:10px 16px;
    border-radius:10px;
    text-decoration:none;
    font-size:13px;
    border:1px solid #dbe6ff;
    transition:.2s;
}

.btn-back:hover{
    background:#dfeaff;
}

/* STUDENT INFO */

.student-box{
    background:#fff;
    padding:18px;
    border-radius:16px;
    margin-bottom:18px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
    line-height:1.8;
    font-size:13px;
}

/* TABLE SECTION */

.section{
    background:#fff;
    padding:22px;
    border-radius:18px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
}

.table-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    flex-wrap:wrap;
    margin-bottom:16px;
}

.table-head h3{
    margin:0;
    color:#2c5aa0;
    font-size:20px;
    line-height:1.3;
}

/* SEARCH */

.search-box{
    width:320px;
    max-width:100%;
}

.search-box input{
    width:100%;
    height:42px;
    padding:0 14px;
    border:1px solid #dbe6ff;
    border-radius:10px;
    font-size:13px;
    outline:none;
    background:#fff;
}

.search-box input:focus{
    border-color:#2c5aa0;
    box-shadow:0 0 0 3px rgba(44,90,160,.08);
}

/* TABLE */

.table-wrapper{
    width:100%;
    overflow-x:auto;
    border-radius:14px;
    -webkit-overflow-scrolling:touch;
}

table{
    width:100%;
    min-width:560px;
    border-collapse:collapse;
}

thead{
    background:#eef3ff;
}

th{
    color:#2c5aa0;
    padding:13px;
    font-size:13px;
    text-align:center;
    font-weight:700;
    border-bottom:1px solid #dbe6ff;
    white-space:nowrap;
}

td{
    padding:12px;
    font-size:13px;
    text-align:center;
    border-bottom:1px solid #f0f0f0;
    white-space:nowrap;
}

tr:last-child td{
    border-bottom:none;
}

tbody tr:hover{
    background:#f9fbff;
}

/* BADGES */

.badge{
    display:inline-block;
    padding:6px 11px;
    border-radius:30px;
    font-size:12px;
    font-weight:700;
}

.badge-enrolled{
    background:#eaf6ff;
    color:#2c5aa0;
}

.badge-completed{
    background:#e6f7ee;
    color:#27ae60;
}

.badge-pending{
    background:#fff8df;
    color:#d68910;
}

/* SUMMARY */

.summary{
    margin-top:18px;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px;
}

.summary-box{
    background:#fff;
    padding:18px;
    border-radius:14px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
    text-align:center;
    font-size:14px;
}

.summary-box b{
    display:block;
    margin-top:8px;
    color:#2c5aa0;
    font-size:22px;
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
        padding-top:85px;
    }

    .top-bar{
        flex-direction:row;
        align-items:center;
        gap:10px;
    }

    .page-title{
        font-size:22px;
    }

    .student-box{
        padding:16px;
    }

    .section{
        padding:16px;
        border-radius:15px;
    }

    .table-head{
        flex-direction:column;
        align-items:stretch;
    }

    .table-head h3{
        font-size:18px;
    }

    .search-box{
        width:100%;
    }

    table{
        min-width:540px;
    }

    .summary{
        grid-template-columns:1fr;
    }

}

/* SMALL MOBILE */

@media (max-width:480px){

    .content{
        padding:12px;
        padding-top:80px;
    }

    .page-title{
        font-size:19px;
    }

    .student-box,
    .section,
    .summary-box{
        padding:14px;
    }

    th,
    td{
        font-size:12px;
        padding:9px;
    }

}

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="top-bar">

        <a href="student_view.php?id=<?php echo $id; ?>" class="btn-back">
            ← Back
        </a>

        <div class="page-title">
            Student Grades
        </div>

    </div>

    <div class="student-info">

        <div class="student-name">
            <?php echo $student['full_name']; ?>
        </div>

        <div class="student-sub">
            ID: <?php echo $student['student_id']; ?> |
            <?php echo $student['course']; ?> |
            Year <?php echo $student['year_level']; ?> |
            Section <?php echo $student['section']; ?>
        </div>

    </div>

    <form method="GET" class="filters">

        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <select name="semester" class="select" onchange="this.form.submit()">
            <option value="1" <?php if ($semester == 1) echo "selected"; ?>>1st Semester</option>
            <option value="2" <?php if ($semester == 2) echo "selected"; ?>>2nd Semester</option>
        </select>

        <select name="year" class="select" onchange="this.form.submit()">
            <option value="1" <?php if ($year == 1) echo "selected"; ?>>1st Year</option>
            <option value="2" <?php if ($year == 2) echo "selected"; ?>>2nd Year</option>
            <option value="3" <?php if ($year == 3) echo "selected"; ?>>3rd Year</option>
            <option value="4" <?php if ($year == 4) echo "selected"; ?>>4th Year</option>
        </select>

    </form>

    <div class="table-card">

        <table>

            <thead>
            <tr>
                <th>Subject</th>
                <th>Semester</th>
                <th>SY</th>
                <th>Grade</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>

            <?php while ($row = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td><?php echo $row['subject_code']; ?></td>
                <td><?php echo $row['semester']; ?></td>
                <td><?php echo $row['school_year']; ?></td>
                <td><?php echo $row['grade']; ?></td>
                <td><?php echo $row['status']; ?></td>

                <td>
                    <a href="#" class="btn btn-edit">Edit</a>
                    <a href="#" class="btn btn-delete">Delete</a>
                    <a href="#" class="btn btn-lock">Lock</a>
                </td>

            </tr>

            <?php } ?>

        </table>

    </div>

    <div class="bottom-actions">

        <button onclick="window.print()" class="btn-print">
            Print Grades
        </button>

        <a href="student_view.php?id=<?php echo $id; ?>" class="btn-profile">
            Back to Profile
        </a>

    </div>

</div>

</body>
</html>