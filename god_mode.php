<?php
session_start();
include "db_connect.php";

echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";

// 1. UPGRADE THE COLUMN: Change 'role' to a flexible string so it stops rejecting 'admin'
$alter_sql = "ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'farmer'";
if ($conn->query($alter_sql)) {
    echo "<p style='color: green;'>✅ Database column upgraded successfully!</p>";
} else {
    echo "<p style='color: red;'>⚠️ Column upgrade note: " . $conn->error . "</p>";
}

$email = "admin@agrimove.com";
$hashed_password = password_hash("admin123", PASSWORD_DEFAULT);

// 2. FORCE THE ADMIN ROLE
$check = $conn->query("SELECT id FROM users WHERE email = '$email'");

if ($check->num_rows > 0) {
    $sql = "UPDATE users SET role = 'admin', password = '$hashed_password' WHERE email = '$email'";
    $conn->query($sql);
    echo "<h2 style='color: green;'>✅ Admin account successfully patched!</h2>";
} else {
    $sql = "INSERT INTO users (name, email, phone, password, role) VALUES ('System Admin', '$email', '0700000000', '$hashed_password', 'admin')";
    $conn->query($sql);
    echo "<h2 style='color: green;'>✅ Admin account successfully created!</h2>";
}

// 3. NUKE THE CACHE
session_unset();
session_destroy();
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, '/');
}

echo "<p style='font-size: 18px;'>Your credentials are ready:<br><b>Email:</b> admin@agrimove.com<br><b>Password:</b> admin123</p>";
echo "<a href='login.php' style='display: inline-block; padding: 12px 24px; background: #16a34a; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 20px;'>Go to Login Page</a>";
echo "</div>";
?>