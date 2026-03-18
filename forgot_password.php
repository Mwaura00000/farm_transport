<?php
session_start();
include "db_connect.php";

$error_msg = "";
$success_msg = "";
$dev_reset_link = ""; // Used for local testing

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error_msg = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } else {
        // 1. Check if the email exists in our users table
        $sql = "SELECT email FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result->num_rows > 0) {
            // 2. Generate a secure, unique token
            $token = bin2hex(random_bytes(50));
            
            // 3. Save the token to the database
            // First, delete any old tokens for this email so they don't pile up
            $del_sql = "DELETE FROM password_resets WHERE email = ?";
            $del_stmt = mysqli_prepare($conn, $del_sql);
            mysqli_stmt_bind_param($del_stmt, "s", $email);
            mysqli_stmt_execute($del_stmt);

            // Insert the new token
            $insert_sql = "INSERT INTO password_resets (email, token) VALUES (?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "ss", $email, $token);
            mysqli_stmt_execute($insert_stmt);

            // 4. Create the reset link
            // Adjust this URL to match your exact localhost path
            $reset_link = "http://localhost/farm_transport/reset_password.php?token=" . $token;

            // 5. Attempt to send the email
            $to = $email;
            $subject = "Password Reset Request - AgriMove";
            $message = "Hi there,\n\nYou requested a password reset. Click the link below to set a new password:\n\n" . $reset_link . "\n\nIf you did not request this, please ignore this email.";
            $headers = "From: noreply@agrimove.co.ke";

            // The @ symbol suppresses the ugly PHP error if mail() fails on localhost
            if (@mail($to, $subject, $message, $headers)) {
                $success_msg = "We have sent a password reset link to your email.";
            } else {
                // LOCALHOST DEV MODE: Show the link on screen if email fails
                $success_msg = "Email sending failed (Localhost Mode). Please use the secure link below to reset your password.";
                $dev_reset_link = $reset_link;
            }
        } else {
            // Security best practice: Don't tell hackers if an email exists or not. 
            // Give the exact same success message even if the email wasn't found.
            $success_msg = "If that email is in our system, we have sent a reset link.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body class="font-sans text-gray-800 antialiased bg-gray-50 flex h-screen overflow-hidden items-center justify-center">

    <div class="max-w-md w-full mx-auto p-8 bg-white rounded-2xl shadow-xl border border-gray-100">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-brand-50 text-brand-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                <i class="fa-solid fa-key"></i>
            </div>
            <h1 class="text-2xl font-heading font-bold text-gray-900 mb-2">Forgot Password?</h1>
            <p class="text-gray-500 text-sm">No worries, we'll send you reset instructions.</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm text-center">
                <i class="fa-solid fa-circle-check mb-2 text-xl"></i><br>
                <?php echo $success_msg; ?>
                
                <?php if (!empty($dev_reset_link)): ?>
                    <div class="mt-4 p-3 bg-white border border-green-200 rounded text-left break-all">
                        <p class="text-xs text-gray-500 uppercase font-bold mb-1">Dev Reset Link:</p>
                        <a href="<?php echo $dev_reset_link; ?>" class="text-blue-600 hover:underline font-medium"><?php echo $dev_reset_link; ?></a>
                    </div>
                <?php endif; ?>
            </div>
            <a href="login.php" class="w-full block text-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3.5 rounded-xl transition">Back to Login</a>
        <?php else: ?>

            <?php if (!empty($error_msg)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-center gap-3 text-sm font-medium">
                    <i class="fa-solid fa-triangle-exclamation text-lg"></i><div><?php echo $error_msg; ?></div>
                </div>
            <?php endif; ?>

            <form action="forgot_password.php" method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-regular fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" name="email" required placeholder="Enter the email you registered with"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition">
                    </div>
                </div>

                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3.5 rounded-xl transition shadow-lg shadow-green-500/30">
                    Reset Password
                </button>
            </form>

            <p class="text-center text-gray-500 text-sm mt-8">
                <a href="login.php" class="font-semibold text-gray-600 hover:text-green-600 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to login
                </a>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>