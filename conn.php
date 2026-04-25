<?php
$host = 'localhost';
$username = 'rtayfoor';
$password = 'hrTCeSP7HN';
$database = 'rtayfoor';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>