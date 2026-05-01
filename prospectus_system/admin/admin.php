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

include "../includes/db.php";

/* ================= CHECK LOGIN ================= */
if (!isset($_SESSION['admin'])) {

    header(
        "Location: https://" .
        $_SERVER['HTTP_HOST'] .
        "/index.php"
    );
    exit();
}

/* ================= COUNTS ================= */

$total_students = mysqli_num_rows(
    mysqli_query($conn, "SELECT * FROM students")
);

$total_courses = mysqli_num_rows(
    mysqli_query($conn, "SELECT * FROM courses")
);

$total_sections = mysqli_num_rows(
    mysqli_query($conn, "SELECT * FROM sections")
);

$total_years = mysqli_num_rows(
    mysqli_query($conn, "SELECT * FROM year_levels")
);

$regular_students = mysqli_num_rows(
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM students
        WHERE current_status = 'Regular'
        "
    )
);

$irregular_students = mysqli_num_rows(
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM students
        WHERE current_status = 'Irregular'
        "
    )
);

$settings = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM academic_settings
        WHERE id = 1
        "
    )
);

/* ================= RECENT STUDENTS ================= */

$recent_students = mysqli_query(
    $conn,
    "
    SELECT
        s.student_id,
        s.full_name,
        c.course_name,
        s.year_level,
        s.section
    FROM students s
    LEFT JOIN courses c
        ON c.id = s.course_id
    ORDER BY s.id DESC
    LIMIT 5
    "
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

    <title>Admin Dashboard</title>

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
}

body{
    font-family:Arial, Helvetica, sans-serif;
    background:#f4f7fc;
}

/* CONTENT */

.content{
    margin-left:265px;
    margin-right:25px;
    padding:30px;
    padding-top:40px;
}

/* HEADER */

.welcome{
    font-size:24px;
    font-weight:700;
    color:#2c5aa0;
    margin-bottom:22px;
    line-height:1.3;
}

/* CARDS */

.card-container{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:25px;
}

.card{
    background:#fff;
    padding:18px;
    border-radius:16px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
    transition:.2s ease;
}

.card:hover{
    transform:translateY(-2px);
}

.card-title{
    font-size:12px;
    color:#777;
    margin-bottom:6px;
}

.card-value{
    font-size:18px;
    font-weight:700;
    color:#2c5aa0;
    word-break:break-word;
}

/* SECTION */

.section{
    background:#fff;
    padding:22px;
    border-radius:18px;
    margin-bottom:20px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
}

.section h3{
    margin:0 0 16px;
    color:#2c5aa0;
    font-size:20px;
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    line-height:1.3;
}

.section h3 i{
    font-size:18px;
}

/* QUICK ACTION */

.quick-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
}

.quick-btn{
    background:#f8fbff;
    padding:18px;
    border-radius:14px;
    text-decoration:none;
    color:#2c5aa0;
    border:1px solid #edf2ff;
    transition:.2s ease;
    font-weight:600;
    display:flex;
    align-items:center;
    gap:12px;
    font-size:15px;
    min-height:58px;
}

.quick-btn i{
    width:20px;
    text-align:center;
    font-size:16px;
    flex-shrink:0;
}

.quick-btn:hover{
    background:#eef3ff;
    color:#1f4580;
    transform:translateY(-2px);
}

/* TABLE */

.table-wrapper{
    width:100%;
    overflow-x:auto;
    overflow-y:hidden;
    border-radius:14px;
    -webkit-overflow-scrolling:touch;
}

table{
    width:100%;
    min-width:700px;
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
    border-bottom:1px solid #dbe6ff;
    white-space:nowrap;
}

td{
    padding:12px;
    font-size:13px;
    text-align:center;
    border-bottom:1px solid #f0f0f0;
}

tr:last-child td{
    border-bottom:none;
}

tr:hover{
    background:#fafcff;
}

/* TABLET */

@media (max-width:1024px){

    .content{
        margin-left:265px;
        margin-right:20px;
        padding:20px;
        padding-top:30px;
    }

    .card-container{
        grid-template-columns:repeat(2,1fr);
    }

    .welcome{
        font-size:22px;
    }

}

/* MOBILE */

@media (max-width:768px){

    .content{
        margin-left:0;
        margin-right:0;
        width:100%;
        max-width:100%;
        padding:15px;
        padding-top:90px;
    }

    .welcome{
        font-size:20px;
        margin-bottom:18px;
    }

    .card-container,
    .quick-grid{
        grid-template-columns:1fr;
        gap:12px;
    }

    .card,
    .section{
        padding:16px;
        border-radius:14px;
    }

    .card-title{
        font-size:13px;
    }

    .card-value{
        font-size:18px;
    }

    .section h3{
        font-size:18px;
        gap:8px;
    }

    .quick-btn{
        font-size:14px;
        padding:15px;
    }

    table{
        min-width:650px;
    }

}

/* SMALL MOBILE */

@media (max-width:480px){

    .content{
        padding:12px;
        padding-top:85px;
    }

    .welcome{
        font-size:18px;
    }

    .card,
    .section{
        padding:14px;
    }

    .card-value{
        font-size:17px;
    }

    .section h3{
        font-size:17px;
    }

}

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="welcome">
        Welcome, Admin
    </div>

    <!-- SUMMARY -->

    <div class="card-container">

        <div class="card">
            <div class="card-title">Total Students</div>
            <div class="card-value">
                <?php echo $total_students; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Total Courses</div>
            <div class="card-value">
                <?php echo $total_courses; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Total Sections</div>
            <div class="card-value">
                <?php echo $total_sections; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Total Year Levels</div>
            <div class="card-value">
                <?php echo $total_years; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Regular Students</div>
            <div class="card-value">
                <?php echo $regular_students; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Irregular Students</div>
            <div class="card-value">
                <?php echo $irregular_students; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Active School Year</div>
            <div class="card-value">
                <?php echo $settings['school_year']; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Active Semester</div>
            <div class="card-value">
                <?php echo $settings['current_semester']; ?>
            </div>
        </div>

    </div>

    <!-- QUICK ACTIONS -->

    <div class="section">

        <h3>
            <i class="fa-solid fa-bolt"></i>
            Quick Actions
        </h3>

        <div class="quick-grid">

            <a href="student_records.php" class="quick-btn">
                <i class="fa-solid fa-folder-open"></i>
                View Student Records
            </a>

            <a href="students.php" class="quick-btn">
                <i class="fa-solid fa-users"></i>
                Manage Students
            </a>

            <a href="class_lists.php" class="quick-btn">
                <i class="fa-solid fa-chart-line"></i>
                Open Grades Tracker
            </a>

            <a href="subjects.php" class="quick-btn">
                <i class="fa-solid fa-book"></i>
                Manage Subjects
            </a>

        </div>

    </div>

    <!-- RECENTLY ADDED -->

    <div class="section">

        <h3>
            <i class="fa-solid fa-clock-rotate-left"></i>
            Recently Added
        </h3>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Section</th>
                    </tr>
                </thead>

                <tbody>

                <?php while ($row = mysqli_fetch_assoc($recent_students)) { ?>

                    <tr>

                        <td>
                            <?php echo $row['student_id']; ?>
                        </td>

                        <td>
                            <?php echo $row['full_name']; ?>
                        </td>

                        <td>
                            <?php echo $row['course_name']; ?>
                        </td>

                        <td>
                            <?php echo $row['year_level']; ?>
                        </td>

                        <td>
                            <?php echo $row['section']; ?>
                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>