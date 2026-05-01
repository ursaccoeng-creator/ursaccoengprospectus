<?php

session_start();
include "../includes/db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "../PHPMailer/PHPMailer-master/src/PHPMailer.php";
require "../PHPMailer/PHPMailer-master/src/SMTP.php";
require "../PHPMailer/PHPMailer-master/src/Exception.php";


/* ================= CONFIRM SELECTED ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_selected'])) {

    $sem = (int)($_POST['sem'] ?? 1);
    $students = $_POST['students'] ?? [];

    if (!in_array($sem, [1, 2, 3])) {
        $sem = 1;
    }

    if (!empty($students) && is_array($students)) {

        $confirmedCount = 0;
        $emailSentCount = 0;

        foreach ($students as $sid) {

            $sid = (int)$sid;

            /* ================= CONFIRM GRADES ================= */

            $run = mysqli_query($conn, "
                UPDATE student_subject_history h
                INNER JOIN students s
                    ON s.id = h.student_id
                INNER JOIN subjects sub
                    ON sub.subject_code = h.subject_code
                    AND sub.course_id = s.course_id
                SET h.is_confirmed = 1
                WHERE h.student_id = '$sid'
                AND CAST(sub.semester AS UNSIGNED) = '$sem'
                AND h.grade IS NOT NULL
                AND h.grade <> ''
                AND (
                    h.grade = 'INC'
                    OR h.grade = 'DROP'
                    OR h.grade REGEXP '^[0-9.]+$'
                )
            ");

            if ($run && mysqli_affected_rows($conn) > 0) {

                $confirmedCount++;

                /* ================= GET STUDENT EMAIL ================= */

                $studentQuery = mysqli_query($conn, "
                    SELECT fullname, email
                    FROM students
                    WHERE id = '$sid'
                    LIMIT 1
                ");

                $stu = mysqli_fetch_assoc($studentQuery);

                if ($stu && $stu['email'] != "") {

                    $studentName  = $stu['fullname'];
                    $studentEmail = $stu['email'];

                    /* ================= SEMESTER LABEL ================= */

                    if ($sem == 1) {
                        $semLabel = "1st Semester";
                    } elseif ($sem == 2) {
                        $semLabel = "2nd Semester";
                    } else {
                        $semLabel = "Intersemester";
                    }

                    /* ================= SEND EMAIL ================= */

                    try {

                        $mail = new PHPMailer(true);

                        $mail->isSMTP();
                        $mail->Host       = "smtp.gmail.com";
                        $mail->SMTPAuth   = true;
                        $mail->Username   = "ursaccoeng@gmail.com";
                        $mail->Password   = "ipzyafayynidonwk";
                        $mail->SMTPSecure = "ssl";
                        $mail->Port       = 465;

                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer'       => false,
                                'verify_peer_name'  => false,
                                'allow_self_signed' => true
                            ]
                        ];

                        $mail->setFrom(
                            "ursaccoeng@gmail.com",
                            "Prospectus System"
                        );

                        $mail->addAddress($studentEmail, $studentName);

                        $mail->isHTML(true);
                        $mail->Subject = "Grades Confirmed";

                        $mail->Body = "
                            Hello <b>$studentName</b>, <br><br>

                            Your grades for <b>$semLabel</b> have been officially confirmed.<br><br>

                            You may now log in to the Prospectus System and review your records.<br><br>

                            Thank you.
                        ";

                        $mail->send();
                        $emailSentCount++;

                    } catch (Exception $e) {
                        /* ignore mail error */
                    }
                }
            }
        }

        if ($confirmedCount > 0) {

            $_SESSION['success_msg'] =
                $confirmedCount .
                " student(s) grades confirmed. " .
                $emailSentCount .
                " email(s) sent.";

        } else {

            $_SESSION['error_msg'] = "No grades found to confirm.";
        }

    } else {

        $_SESSION['error_msg'] = "No students selected.";
    }

    header("Location: class_lists.php?sem=$sem");
    exit();
}
?>