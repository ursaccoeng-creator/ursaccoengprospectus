<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}


/* ================= FILTERS ================= */

$search     = $_GET['search']     ?? "";
$course_id  = $_GET['course_id']  ?? "";
$section    = $_GET['section']    ?? "";


/* ================= GET COURSES ================= */

$course_query = mysqli_query(
    $conn,
    "SELECT id, course_name FROM courses ORDER BY course_name ASC"
);


/* ================= GET SECTIONS ================= */

$section_query = mysqli_query(
    $conn,
    "
    SELECT DISTINCT section_name 
    FROM sections 
    ORDER BY section_name ASC
    "
);


/* ================= MAIN QUERY ================= */

$query = "
    SELECT 
        s.*,
        c.course_name
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    WHERE 1
";


/* ================= SEARCH ================= */

if ($search != "") {

    $query .= "
        AND (
            s.student_id LIKE '%$search%'
            OR s.full_name LIKE '%$search%'
        )
    ";
}


/* ================= COURSE FILTER ================= */

if ($course_id != "") {
    $query .= " AND s.course_id = '$course_id'";
}


/* ================= SECTION FILTER ================= */

if ($section != "") {
    $query .= " AND s.section = '$section'";
}


/* ================= ORDER ================= */

$query .= " ORDER BY s.student_id ASC";


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

<title>Student Records</title>

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
    margin:0;
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

.page-title{
    font-size:22px;
    font-weight:700;
    margin-bottom:20px;
    color:#2c5aa0;
    line-height:1.3;
}

/* FILTER BAR */

.filters{
    display:flex;
    gap:10px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.input,
.select{
    padding:11px;
    min-height:42px;
    border-radius:10px;
    border:1px solid #dbe6ff;
    background:#fff;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
    outline:none;
    box-sizing:border-box;
    font-size:14px;
    transition:.2s;
}

.input:focus,
.select:focus{
    border-color:#2c5aa0;
    box-shadow:0 0 0 3px rgba(44,90,160,.08);
}

/* TABLE CARD */

.table-card{
    background:#fff;
    padding:20px;
    border-radius:18px;
    overflow-x:auto;
    width:100%;
    box-sizing:border-box;
    box-shadow:0 3px 12px rgba(0,0,0,.05);
    -webkit-overflow-scrolling:touch;
}

/* TABLE */

table{
    width:100%;
    border-collapse:collapse;
    min-width:900px;
}

thead{
    background:#eef3ff;
}

th{
    color:#2c5aa0;
    text-align:center;
    padding:13px;
    font-size:13px;
    font-weight:700;
    border-bottom:1px solid #dbe6ff;
    white-space:nowrap;
}

td{
    padding:12px;
    border-bottom:1px solid #f0f0f0;
    font-size:13px;
    text-align:center;
    white-space:nowrap;
}

tr:last-child td{
    border-bottom:none;
}

/* BUTTON */

.btn{
    padding:7px 14px;
    border-radius:8px;
    border:none;
    font-size:12px;
    font-weight:600;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
    transition:.2s;
}

.btn-view{
    background:#2c5aa0;
    color:#fff;
}

.btn-view:hover{
    background:#1f4580;
}

/* TABLET */

@media (max-width:1024px){

    .content{
        margin-left:0;
        margin-right:0;
        padding:22px;
        padding-top:88px;
    }

    .filters{
        gap:8px;
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

    .filters{
        flex-direction:column;
        width:100%;
        gap:8px;
    }

    .filters .input,
    .filters .select{
        width:100%;
        display:block;
    }

    .table-card{
        padding:14px;
        border-radius:15px;
    }

    table{
        min-width:600px;
    }

    th,
    td{
        font-size:12px;
        padding:10px;
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

    .table-card{
        padding:12px;
    }

    table{
        min-width:560px;
    }

    th,
    td{
        font-size:11px;
        padding:8px;
    }

}

</style>

</head>

<body>

<?php include "adminsidebar.php"; ?>

<div class="content">

    <div class="page-title">
        Student Records
    </div>

    <form method="GET" class="filters">

        <input
            type="text"
            name="search"
            placeholder="Search Student ID / Name"
            class="input"
            value="<?php echo $search; ?>"
        >

        <!-- COURSE FILTER (FIXED) -->
        <select 
            name="course_id" 
            class="select" 
            onchange="this.form.submit()"
        >
            <option value="">All Course</option>

            <?php while ($c = mysqli_fetch_assoc($course_query)) { ?>
                <option
                    value="<?php echo $c['id']; ?>"
                    <?php if ($course_id == $c['id']) echo "selected"; ?>
                >
                    <?php echo $c['course_name']; ?>
                </option>
            <?php } ?>

        </select>

        <!-- SECTION FILTER -->
        <select 
            name="section" 
            class="select" 
            onchange="this.form.submit()"
        >
            <option value="">All Section</option>

            <?php while ($s = mysqli_fetch_assoc($section_query)) { ?>
                <option
                    value="<?php echo $s['section_name']; ?>"
                    <?php if ($section == $s['section_name']) echo "selected"; ?>
                >
                    <?php echo $s['section_name']; ?>
                </option>
            <?php } ?>

        </select>

    </form>

    <div class="table-card">

        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                    <tr>
                        <td><?php echo $row['student_id']; ?></td>

                        <td><?php echo $row['full_name']; ?></td>

                        <!-- FIXED COURSE DISPLAY -->
                        <td><?php echo $row['course_name'] ?? 'No Course'; ?></td>

                        <td><?php echo $row['year_level']; ?></td>

                        <td><?php echo $row['section']; ?></td>

                        <td><?php echo $row['current_status']; ?></td>

                        <td>
                            <a href="student_view.php?id=<?php echo $row['id']; ?>" class="btn btn-view">
                                View
                            </a>
                        </td>
                    </tr>

                <?php } ?>

            </tbody>
        </table>

    </div>

</div>

</body>
</html>