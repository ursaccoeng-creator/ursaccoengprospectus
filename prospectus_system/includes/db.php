<?php

$host = "aws-1-ap-northeast-2.pooler.supabase.com";
$port = "6543";
$db   = "postgres";
$user = "postgres.hfshknytaadfkflrflup";
$pass = "YUa7LSnthajDp0sy";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to Supabase!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

?>
