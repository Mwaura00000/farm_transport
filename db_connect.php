<?php
$host = "127.0.0.1";
$user = "root";
$password = "";
$database = "farm_transport_db";
$port = 3307; // your MySQL port

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>