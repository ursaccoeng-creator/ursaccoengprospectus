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

/* ================= CLEAR SESSION ================= */
$_SESSION = [];

/* ================= DELETE SESSION COOKIE ================= */
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        true,
        true
    );
}

/* ================= DESTROY SESSION ================= */
session_destroy();

/* ================= REDIRECT HTTPS ================= */
header(
    "Location: https://" .
    $_SERVER['HTTP_HOST'] .
    "/index.php"
);
exit();

?>