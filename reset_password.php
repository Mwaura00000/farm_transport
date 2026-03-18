<?php
// Turn on error reporting so we never get a "blank screen" again
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "db_connect.php";

$error_msg = "";
$success_msg = "";
$valid_token = false;
$email = "";
$token = "";

// 1. Verify the Token from the URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Check if token exists and hasn't expired
    $sql = "SELECT email FROM password_resets WHERE token = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && $result->num_rows == 1) {
            $valid_token = true;
            $email = $result->fetch_assoc()['email'];
        } else {
            $error_msg = "This password reset link is invalid or has expired. Please request a new one.";
        }
        mysqli_stmt_close($stmt);
    } else {
        // This catches database setup errors instead of crashing!
        $error_msg = "Database Error: " . mysqli_error($conn) . " <br><br><b>Developer Note:</b> Did you run the SQL command to create the `password_resets` table?";
    }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
    $error_msg = "No reset token provided in the URL.";
}

// 2. Handle the New Password Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_POST['email']; 
    $token = $_POST['token']; 

    if (empty($new_password) || empty($confirm_password)) {
        $error_msg = "Please fill out all fields.";
        $valid_token = true; 
    } elseif (strlen($new_password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
        $valid_token = true;
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "Passwords do not match.";
        $valid_token = true;
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the user's password in the users table
        $update_sql = "UPDATE users SET password = ? WHERE email = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        
        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $email);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Delete the token so it can't be used again
                $del_sql = "DELETE FROM password_resets WHERE email = ?";
                $del_stmt = mysqli_prepare($conn, $del_sql);
                if($del_stmt) {
                    mysqli_stmt_bind_param($del_stmt, "s", $email);
                    mysqli_stmt_execute($del_stmt);
                }

                $success_msg = "Your password has been successfully reset! You can now log in.";
                $valid_token = false; // Hide the form
            } else {
                $error_msg = "Failed to update password. Please try again.";
                $valid_token = true;
            }
        } else {
             $error_msg = "Database Update Error: " . mysqli_error($conn);
             $valid_token = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body class="font-sans text-gray-800 antialiased bg-gray-50 flex h-screen overflow-hidden items-center justify-center">

    <div class="max-w-md w-full mx-auto p-8 bg-white rounded-2xl shadow-xl border border-gray-100">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="text-2xl font-heading font-bold text-gray-900 mb-2">Set New Password</h1>
            <p class="text-gray-500 text-sm">Please create a strong password for your account.</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="mb-8 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm text-center">
                <i class="fa-solid fa-circle-check mb-2 text-xl"></i><br>
                <?php echo $success_msg; ?>
            </div>
            <a href="login.php" class="w-full block text-center bg-brand-600 hover:bg-brand-700 text-white font-bold py-3.5 rounded-xl transition shadow-lg shadow-brand-500/30">Go to Login</a>
        <?php else: ?>

            <?php if (!empty($error_msg)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-start gap-3 text-sm font-medium">
                    <i class="fa-solid fa-triangle-exclamation text-lg mt-0.5"></i><div><?php echo $error_msg; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($valid_token): ?>
                <form action="reset_password.php" method="POST" class="space-y-5">
                    <input type="hidden" name="reset_password" value="1">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" name="new_password" id="newPass" required minlength="6"
                                class="w-full pl-10 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleVisibility('newPass', 'eyeNew')">
                                <i class="fa-solid fa-eye" id="eyeNew"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-check-double text-gray-400"></i>
                            </div>
                            <input type="password" name="confirm_password" id="confPass" required minlength="6"
                                class="w-full pl-10 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleVisibility('confPass', 'eyeConf')">
                                <i class="fa-solid fa-eye" id="eyeConf"></i>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition shadow-lg shadow-blue-500/30 mt-4">
                        Update Password
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center mt-6">
                    <a href="forgot_password.php" class="text-brand-600 hover:text-brand-700 font-medium flex items-center justify-center gap-2">
                        <i class="fa-solid fa-arrow-left"></i> Request a new reset link
                    </a>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <script>
        function toggleVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");
            }
        }
    </script>
</body>
</html>