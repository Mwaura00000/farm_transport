<?php
session_start();
// Empty all session variables
$_SESSION = array();
// Destroy the session
session_destroy();
// Physically delete the PHPSESSID cookie from Chrome
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, '/');
}
?>
<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; text-align: center; margin-top: 100px;">
    <h1 style="color: #16a34a;">💥 Memory Nuked!</h1>
    <p>Chrome's stuck session cookie has been completely destroyed.</p>
    <a href="login.php" style="display: inline-block; padding: 12px 24px; background: #16a34a; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 20px;">Return to Login</a>
</body>
</html>