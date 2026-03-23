<?php
include "db_connect.php";

// Set your admin credentials here
$name = "System Admin";
$email = "admin@agrimove.com";
$phone = "0700000000";
$raw_password = "admin123"; 
$hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
$role = "admin";

// Check if admin already exists
$check = $conn->query("SELECT id FROM users WHERE email = '$email'");
if($check->num_rows > 0) {
    die("Admin account already exists! You can log in with $email");
}

$sql = "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $phone, $hashed_password, $role);

if(mysqli_stmt_execute($stmt)) {
    echo "<h2 style='color:green; font-family:sans-serif;'>Success! Admin created.</h2>";
    echo "<p style='font-family:sans-serif;'><b>Email:</b> admin@agrimove.com<br><b>Password:</b> admin123</p>";
    echo "<p style='font-family:sans-serif;'><a href='login.php'>Go to Login Page</a></p>";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>