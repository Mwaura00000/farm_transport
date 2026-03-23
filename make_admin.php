<?php
include "db_connect.php";

$email = "admin@agrimove.com";
// Securely hash the password 'admin123'
$hashed_password = password_hash("admin123", PASSWORD_DEFAULT);

// Check if the admin email is already in the database
$check = $conn->query("SELECT id FROM users WHERE email = '$email'");

if ($check->num_rows > 0) {
    // The account exists, but the role is broken. Let's force it to 'admin'.
    $sql = "UPDATE users SET role = 'admin', password = '$hashed_password' WHERE email = '$email'";
    if ($conn->query($sql)) {
        echo "<h2 style='color: green; font-family: sans-serif;'>✅ Success! Your broken role was fixed and upgraded to 'admin'.</h2>";
    } else {
        echo "Error updating: " . $conn->error;
    }
} else {
    // The account doesn't exist at all. Let's create it perfectly.
    $sql = "INSERT INTO users (name, email, phone, password, role) VALUES ('System Admin', '$email', '0700000000', '$hashed_password', 'admin')";
    if ($conn->query($sql)) {
        echo "<h2 style='color: green; font-family: sans-serif;'>✅ Success! Fresh Admin account created.</h2>";
    } else {
        echo "Error inserting: " . $conn->error;
    }
}

echo "<p style='font-family: sans-serif; font-size: 18px;'>Your credentials are:<br><b>Email:</b> admin@agrimove.com<br><b>Password:</b> admin123</p>";
echo "<a href='login.php' style='display: inline-block; padding: 10px 20px; background: #16a34a; color: white; text-decoration: none; border-radius: 5px; font-family: sans-serif; font-weight: bold;'>Go to Login Page</a>";
?>