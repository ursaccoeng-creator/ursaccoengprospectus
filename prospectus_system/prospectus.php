<?php

session_start();
include "includes/db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

$student_id = mysqli_real_escape_string(
    $conn,
    $_SESSION['student_id']
);

/* ================= GET STUDENT ================= */

$student_query = mysqli_query(
    $conn,
    "
    SELECT
        s.*,
        c.course_name
    FROM students s
    LEFT JOIN courses c
        ON c.id = s.course_id
    WHERE s.student_id = '$student_id'
    LIMIT 1
    "
);

$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    die("Student not found.");
}

/* ================= CURRICULUM ================= */

$curriculum_id = 0;

if (!empty($student['curriculum_id'])) {

    $curriculum_id = (int) $student['curriculum_id'];

} else {

    $cur = mysqli_query(
        $conn,
        "
        SELECT id
        FROM curricula
        WHERE course_id = '{$student['course_id']}'
        AND is_active = 1
        LIMIT 1
        "
    );

    if ($cr = mysqli_fetch_assoc($cur)) {
        $curriculum_id = (int) $cr['id'];
    }
}

if ($curriculum_id <= 0) {
    die("No curriculum assigned.");
}

/* ================= FILTERS ================= */

$selected_year = isset($_GET['year'])
    ? (int) filter_var(
        $_GET['year'],
        FILTER_SANITIZE_NUMBER_INT
    )
    : 1;

$selected_sem = isset($_GET['sem'])
    ? (int) $_GET['sem']
    : 1;

if (!in_array($selected_sem, [1, 2, 3])) {
    $selected_sem = 1;
}

/* ================= CURRENT STANDING ================= */

$is_irregular = false;

if (!($selected_year == 1 && $selected_sem == 1)) {

    $standing = mysqli_query(
        $conn,
        "
        SELECT grade
        FROM student_subject_history
        WHERE student_id = '{$student['id']}'
        AND is_confirmed = 1
        "
    );

    while ($row = mysqli_fetch_assoc($standing)) {

        $grade = strtoupper(
            trim($row['grade'] ?? '')
        );

        if (
            $grade == 'INC' ||
            $grade == 'DROP' ||
            (
                is_numeric($grade) &&
                (float) $grade > 3.00
            )
        ) {
            $is_irregular = true;
            break;
        }
    }
}

/* ================= LOAD SUBJECTS ================= */

$subjects = mysqli_query(
    $conn,
    "
    SELECT *
    FROM curriculum_subjects
    WHERE curriculum_id = '$curriculum_id'
    AND CAST(year_level AS UNSIGNED) = '$selected_year'
    AND CAST(semester AS UNSIGNED) = '$selected_sem'
    ORDER BY subject_code ASC
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

<meta
    name="format-detection"
    content="telephone=no"
>

<title>Prospectus</title>

<style>

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fc;
}

.content {
    margin-left: 265px;
    margin-right: 25px;
    padding: 30px;
    padding-top: 40px;
}

.page-title {
    font-size: 22px;
    margin-bottom: 20px;
    font-weight: 600;
    color: #2c5aa0;
}

.filter-box {
    background: white;
    padding: 18px;
    border-radius: 18px;
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

.filter-row {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.filter-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

select {
    height: 36px;
    padding: 5px 30px 5px 10px;
    font-size: 13px;
    border-radius: 10px;
    border: 1px solid #dbe6ff;
    background: #fff;
}

.table-wrapper {
    background: white;
    border-radius: 18px;
    overflow: auto;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

thead {
    background: #eef3ff;
}

th {
    color: #2c5aa0;
    padding: 14px;
    font-size: 13px;
    text-align: center;
}

td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
    text-align: center;
}

.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.passed {
    background: #e6f7ee;
    color: #27ae60;
}

.failed {
    background: #fdecea;
    color: #e74c3c;
}

.locked {
    background: #f2f2f2;
    color: #777;
}

.no-grade {
    background: #eef2f7;
    color: #666;
}

@media (max-width:768px) {

    .content {
        margin-left: 0;
        margin-right: 0;
        padding: 15px;
        padding-top: 90px;
    }

    .page-title {
        font-size: 20px;
    }

    .filter-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .filter-item {
        width: 100%;
        justify-content: space-between;
    }

    select {
        width: 160px;
    }

}

</style>

</head>

<body>

<?php include "includes/sidebar.php"; ?>

<div class="content">

    <div class="page-title">
        Prospectus -
        <?php echo htmlspecialchars($student['course_name']); ?>
    </div>

    <div class="filter-box">

        <form method="GET">

            <div class="filter-row">

                <div class="filter-item">

                    Year:

                    <select
                        name="year"
                        onchange="this.form.submit()"
                    >

                        <?php

                        $yrs = mysqli_query(
                            $conn,
                            "
                            SELECT DISTINCT year_level
                            FROM curriculum_subjects
                            WHERE curriculum_id = '$curriculum_id'
                            ORDER BY CAST(year_level AS UNSIGNED)
                            "
                        );

                        while ($y = mysqli_fetch_assoc($yrs)) {

                            $year_value = (int) $y['year_level'];

                        ?>

                        <option
                            value="<?php echo $year_value; ?>"
                            <?php if ($selected_year == $year_value) echo "selected"; ?>
                        >
                            Year <?php echo $year_value; ?>
                        </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="filter-item">

                    Semester:

                    <select
                        name="sem"
                        onchange="this.form.submit()"
                    >

                        <option
                            value="1"
                            <?php if ($selected_sem == 1) echo "selected"; ?>
                        >
                            1st Semester
                        </option>

                        <option
                            value="2"
                            <?php if ($selected_sem == 2) echo "selected"; ?>
                        >
                            2nd Semester
                        </option>

                        <option
                            value="3"
                            <?php if ($selected_sem == 3) echo "selected"; ?>
                        >
                            Intersemester
                        </option>

                    </select>

                </div>

                <div class="filter-item">

                    Standing:

                    <b>
                        <?php echo $is_irregular ? "Irregular" : "Regular"; ?>
                    </b>

                </div>

            </div>

        </form>

    </div>

    <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>Subject Code</th>
                    <th>Subject Title</th>
                    <th>Units</th>
                    <th>Grade</th>
                    <th>Pre-Requisite</th>
                    <th>Year Level</th>
                    <th>Semester</th>
                    <th>Status</th>
                </tr>

            </thead>

            <tbody>

                <?php while ($row = mysqli_fetch_assoc($subjects)) { ?>

                <?php

                $subject_code = trim($row['subject_code']);
                $title        = $row['subject_title'];
                $units        = (int) $row['units'];

                ?>

                <tr>

                    <td><?php echo htmlspecialchars($subject_code); ?></td>
                    <td><?php echo htmlspecialchars($title); ?></td>
                    <td><?php echo $units; ?></td>
                    <td>-</td>
                    <td>None</td>
                    <td>Year <?php echo (int) $row['year_level']; ?></td>

                    <td>
                        <?php
                        echo $row['semester'] == 1
                            ? "1st Semester"
                            : (
                                $row['semester'] == 2
                                ? "2nd Semester"
                                : "Intersemester"
                            );
                        ?>
                    </td>

                    <td>
                        <span class="status-badge no-grade">
                            No Grade
                        </span>
                    </td>

                </tr>

                <?php } ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>