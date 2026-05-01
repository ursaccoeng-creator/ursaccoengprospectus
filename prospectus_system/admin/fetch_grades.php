<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    exit("Unauthorized access");
}

if (
    !isset($_GET['student_id']) ||
    !isset($_GET['year']) ||
    !isset($_GET['sem'])
) {
    exit("Invalid request");
}

$student_id = (int)$_GET['student_id'];
$year       = (int)$_GET['year'];
$sem        = (int)$_GET['sem'];

if (!in_array($sem,[1,2,3])) {
    $sem = 1;
}


/* ================= SAVE GRADE ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_code'])) {

    $subject_code = mysqli_real_escape_string($conn, trim($_POST['subject_code']));
    $grade_input  = strtoupper(trim($_POST['grade'] ?? ''));

    $valid = false;
    $saveValue = '';

    if ($grade_input === 'INC' || $grade_input === 'DROP') {

        $valid = true;
        $saveValue = $grade_input;

    } elseif (preg_match('/^[1-5](\.\d{1,2})?$/', $grade_input)) {

        $num = round((float)$grade_input,2);

        if ($num >= 1.00 && $num <= 5.00) {
            $valid = true;
            $saveValue = number_format($num,2,'.','');
        }
    }

    if (!$valid) {
        echo "INVALID";
        exit();
    }

    $check = mysqli_query($conn,"
        SELECT id,is_confirmed
        FROM student_subject_history
        WHERE student_id='$student_id'
        AND subject_code='$subject_code'
        LIMIT 1
    ");

    if ($check && mysqli_num_rows($check) > 0) {

        $old = mysqli_fetch_assoc($check);
        $confirmed = (int)$old['is_confirmed'];

        $save = mysqli_query($conn,"
            UPDATE student_subject_history
            SET grade='$saveValue',
                is_confirmed='$confirmed'
            WHERE student_id='$student_id'
            AND subject_code='$subject_code'
        ");

    } else {

        $save = mysqli_query($conn,"
            INSERT INTO student_subject_history
            (
                student_id,
                subject_code,
                grade,
                is_confirmed
            )
            VALUES
            (
                '$student_id',
                '$subject_code',
                '$saveValue',
                0
            )
        ");
    }

    echo $save ? "OK" : "SQLERROR";
    exit();
}


/* ================= FETCH SUBJECTS ================= */

$query = mysqli_query($conn,"
    SELECT
        s.subject_code,
        s.subject_title,
        s.units,
        COALESCE(h.grade,'') AS grade,
        COALESCE(h.is_confirmed,0) AS is_confirmed
    FROM students st
    INNER JOIN subjects s
        ON s.course_id = st.course_id
        AND CAST(s.year_level AS UNSIGNED) = '$year'
        AND CAST(s.semester AS UNSIGNED) = '$sem'
    LEFT JOIN student_subject_history h
        ON h.student_id = st.id
        AND h.subject_code = s.subject_code
    WHERE st.id = '$student_id'
    ORDER BY s.subject_code ASC
");

?>

<style>
.fetch-wrap{
    width:100%;
    overflow-x:auto;
    overflow-y:hidden;
    -webkit-overflow-scrolling:touch;
}

.fetch-table{
    width:100%;
    border-collapse:collapse;
    font-family:Arial, Helvetica, sans-serif;
    min-width:680px; /* only for horizontal scroll */
}

.fetch-table thead{
    background:#eef3ff;
}

.fetch-table th{
    padding:10px;
    color:#2c5aa0;
    font-size:13px;
    font-weight:700;
    text-align:center;
    white-space:nowrap;
}

.fetch-table td{
    padding:10px;
    border-bottom:1px solid #eee;
    font-size:13px;
    text-align:center;
    vertical-align:middle;
    white-space:nowrap;
}

/* DO NOT affect main class_lists table */
#modalContent .fetch-table th,
#modalContent .fetch-table td{
    font-family:Arial, Helvetica, sans-serif;
}

.grade-input{
    width:70px;
    padding:7px;
    border:1px solid #ccc;
    border-radius:8px;
    text-align:center;
    font-size:13px;
    background:#fff;
}

.save-btn{
    padding:7px 12px;
    border:none;
    background:#2c5aa0;
    color:#fff;
    border-radius:8px;
    cursor:pointer;
    font-size:12px;
    font-weight:700;
}

.save-btn:hover{
    opacity:.95;
}

.badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:700;
}

.confirmed{
    background:#e8fff0;
    color:#27ae60;
}

.pending{
    background:#fff4e5;
    color:#d68910;
}

.alert{
    padding:10px;
    border-radius:8px;
    margin-bottom:10px;
    font-size:13px;
    font-weight:700;
    font-family:Arial, Helvetica, sans-serif;
}

.success{
    background:#e8fff0;
    color:#27ae60;
}

.error{
    background:#ffeaea;
    color:#c0392b;
}

/* ONLY modal responsiveness */
@media (max-width:768px){

    .fetch-table{
        min-width:640px;
    }

    .grade-input{
        width:64px;
    }

}

@media (max-width:480px){

    .fetch-table{
        min-width:620px;
    }

}
</style>

<div id="msgArea"></div>

<div class="fetch-wrap">

<table class="fetch-table">

<thead>
<tr>
<th>Code</th>
<th>Subject</th>
<th>Units</th>
<th>Grade</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = mysqli_fetch_assoc($query)) { ?>

<?php
$grade = '';

if ($row['grade'] !== '') {
    $grade = is_numeric($row['grade'])
        ? number_format((float)$row['grade'],2)
        : $row['grade'];
}

$confirmed = ((int)$row['is_confirmed'] === 1);
$inputId = "grade_" . md5($row['subject_code']);
?>

<tr>

<td><?php echo htmlspecialchars($row['subject_code']); ?></td>

<td><?php echo htmlspecialchars($row['subject_title']); ?></td>

<td><?php echo (int)$row['units']; ?></td>

<td>
<input
type="text"
id="<?php echo $inputId; ?>"
value="<?php echo htmlspecialchars($grade); ?>"
class="grade-input"
maxlength="5">
</td>

<td>
<?php if ($row['grade'] === '') { ?>
<span class="badge pending">Not Encoded</span>
<?php } elseif ($confirmed) { ?>
<span class="badge confirmed">Confirmed</span>
<?php } else { ?>
<span class="badge pending">Pending</span>
<?php } ?>
</td>

<td>
<button
type="button"
class="save-btn"
onclick="
let fd=new FormData();
fd.append('subject_code','<?php echo addslashes($row['subject_code']); ?>');
fd.append('grade',document.getElementById('<?php echo $inputId; ?>').value);

fetch('fetch_grades.php?student_id=<?php echo $student_id; ?>&year=<?php echo $year; ?>&sem=<?php echo $sem; ?>',{
method:'POST',
body:fd
})
.then(r=>r.text())
.then(t=>{
t=t.trim();

if(t==='OK'){
document.getElementById('msgArea').innerHTML='<div class=\'alert success\'>Grade saved successfully!</div>';
}else if(t==='INVALID'){
document.getElementById('msgArea').innerHTML='<div class=\'alert error\'>Invalid grade input.</div>';
}else{
document.getElementById('msgArea').innerHTML='<div class=\'alert error\'>Save failed.</div>';
}
});
">
Save
</button>
</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>