<?php
$host = "localhost:3307";  // <-- add :3307 because MySQL runs on this port now
$user = "root";            // XAMPP default
$password = "";            // XAMPP default
$database = "farm_transport_db";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>