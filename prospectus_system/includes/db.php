<?php

$conn = mysqli_connect(
    "sql100.infinityfree.com",
    "if0_41720805",
    "F12SA7S0s11BtFD",
    "if0_41720805_prospectus_system"
);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

?>