<?php
include "db_connect.php";

// 1. Force the admin email to actually have the 'admin' role
$update_admin = $conn->query("UPDATE users SET role = 'admin' WHERE email = 'admin@agrimove.com'");

// 2. Clean up ALL roles in the database (forces them to lowercase, no spaces)
// This permanently stops the ping-pong redirect loop for Farmers and Transporters too!
$clean_roles = $conn->query("UPDATE users SET role = LOWER(TRIM(role))");

if ($update_admin && $clean_roles) {
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
    echo "<h1 style='color: green;'>Database Successfully Patched! 🛠️</h1>";
    echo "<p>Your account is now officially an Admin, and all roles have been cleaned.</p>";
    echo "<a href='logout.php' style='display: inline-block; padding: 10px 20px; background: #16a34a; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Click here to log in fresh</a>";
    echo "</div>";
} else {
    echo "Error updating database: " . $conn->error;
}
?>