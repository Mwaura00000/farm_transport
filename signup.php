<?php
session_start();
include "db.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

$message = "";

if (isset($_POST['signup'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if (!$check) die("Prepare failed: " . $conn->error);
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Email already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare(
                "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
            );
            if (!$insert) die("Prepare failed: " . $conn->error);
            $insert->bind_param("ssss", $name, $email, $hashed_password, $role);

            if ($insert->execute()) {
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['role'] = $role;
                if ($role === 'transporter') {
                    header("Location: transporter_profile.php");
                } else {
                    header("Location: farmer_dashboard.php");
                }
                exit;
            } else {
                $message = "Error creating account.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign Up | AgriMove Farm Transport</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{background:#f6f8fb;color:#2c3e50;display:flex;justify-content:center;align-items:center;min-height:100vh;}
a{text-decoration:none;color:#2e7d32;transition:0.3s;}
a:hover{color:#1b5e20;}
form{background:white;padding:40px 30px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.1);width:100%;max-width:450px;animation:fadeIn 1s ease;}
h2{text-align:center;margin-bottom:20px;color:#2e7d32;font-family:'Montserrat',sans-serif;}
label{display:block;margin-bottom:6px;font-weight:500;}
input, select{width:100%;padding:12px 15px;margin-bottom:20px;border:1px solid #ccc;border-radius:8px;transition:0.3s;font-size:16px;}
input:focus, select:focus{outline:none;border-color:#2e7d32;background:#e8f5e9;box-shadow:0 4px 12px rgba(46,125,50,0.2);}
button{width:100%;padding:15px;background:#2e7d32;color:white;font-weight:600;border:none;border-radius:8px;font-size:16px;cursor:pointer;transition:0.3s;}
button:hover{transform:scale(1.05);filter:brightness(1.1);box-shadow:0 8px 20px rgba(0,0,0,0.2);}
.message{background:#ffdddd;color:#d8000c;padding:12px 15px;border-radius:8px;margin-bottom:15px;text-align:center;}
.bottom-text{text-align:center;margin-top:10px;font-size:14px;}
.bottom-text a{font-weight:600;}
@keyframes fadeIn{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
</style>
</head>
<body>

<form method="POST" action="">
    <h2>Create Your Account</h2>
    <?php if ($message) echo "<div class='message'>$message</div>"; ?>

    <label>Full Name</label>
    <input type="text" name="name" placeholder="John Doe" required>

    <label>Email</label>
    <input type="email" name="email" placeholder="you@example.com" required>

    <label>Password</label>
    <input type="password" name="password" placeholder="Enter password" required>

    <label>Confirm Password</label>
    <input type="password" name="confirm_password" placeholder="Re-enter password" required>

    <label>Role</label>
    <select name="role" required>
        <option value="">Select role</option>
        <option value="farmer">Farmer11</option>
        <option value="transporter">Transporter</option>
    </select>

    <button type="submit" name="signup">Sign Up</button>

    <div class="bottom-text">
        Already have an account? <a href="login.php">Login</a>
    </div>
</form>

</body>
</html>