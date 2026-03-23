<?php
include "db_connect.php";

echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";

// 1. Add the OTP column to the table (Ignores if it already exists)
$sql_alter = "ALTER TABLE transport_requests ADD COLUMN otp_code VARCHAR(10) NULL";
if ($conn->query($sql_alter)) {
    echo "<p style='color: green;'>✅ OTP Column added to database.</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Column note: " . $conn->error . "</p>";
}

// 2. Generate a random 4-digit PIN for any job that doesn't have one yet
$sql_update = "UPDATE transport_requests SET otp_code = LPAD(FLOOR(RAND() * 9999), 4, '0') WHERE otp_code IS NULL OR otp_code = ''";
if ($conn->query($sql_update)) {
    echo "<h2 style='color: green;'>✅ Security System Patched! All jobs now have a secret PIN.</h2>";
} else {
    echo "<p style='color: red;'>Error generating PINs: " . $conn->error . "</p>";
}

echo "<p>You can close this tab now.</p>";
echo "</div>";
?>