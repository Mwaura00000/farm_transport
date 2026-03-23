<?php
session_start();
include "db_connect.php";

// --- THE LOOP BREAKER ---
// If you are already logged in, we check your role exactly.
if (isset($_SESSION['user_id'])) {
    $current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

    if ($current_role === 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    } elseif ($current_role === 'farmer') {
        header("Location: farmer_dashboard.php");
        exit();
    } elseif ($current_role === 'transporter') {
        header("Location: transporter_dashboard.php");
        exit();
    } else {
        // If your role is corrupted (not one of the 3 above), DESTROY the session instead of bouncing you!
        session_unset();
        session_destroy();
    }
}

$error_msg = "";
$success_msg = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : "";
unset($_SESSION['success_msg']); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error_msg = "Please enter both email and password.";
    } else {
        $sql = "SELECT id, name, password, role FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                
                $clean_role = strtolower(trim($user['role']));
                
                // 1. Check if the role is actually valid BEFORE setting the session
                if ($clean_role === 'admin' || $clean_role === 'farmer' || $clean_role === 'transporter') {
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $clean_role;
                    
                    if ($clean_role === 'admin') {
                        header("Location: admin_dashboard.php");
                        exit();
                    } elseif ($clean_role === 'farmer') {
                        header("Location: farmer_dashboard.php");
                        exit();
                    } elseif ($clean_role === 'transporter') {
                        header("Location: transporter_dashboard.php");
                        exit();
                    }
                } else {
                    // 2. If the database has a weird role, stop them here and show an error!
                    $error_msg = "System Error: Your account role ('$clean_role') is not recognized. Please check the database.";
                }
                
            } else {
                $error_msg = "Incorrect password. Please try again.";
            }
        } else {
            $error_msg = "No account found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], heading: ['Montserrat', 'sans-serif'], },
                    colors: { brand: { 50: '#f0fdf4', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', } }
                }
            }
        }
    </script>
</head>
<body class="font-sans text-gray-800 antialiased bg-white flex h-screen overflow-hidden">

    <div class="w-full lg:w-1/2 flex flex-col justify-center px-8 sm:px-16 md:px-24 overflow-y-auto">
        <div class="max-w-md w-full mx-auto">
            
            <a href="index.php" class="flex items-center gap-2 mb-12 w-fit hover:opacity-80 transition">
                <div class="w-10 h-10 bg-brand-600 rounded-lg flex items-center justify-center text-white text-xl shadow-md">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <span class="font-heading font-bold text-2xl text-gray-900 tracking-tight">AgriMove</span>
            </a>

            <div class="mb-8">
                <h1 class="text-3xl font-heading font-bold text-gray-900 mb-2">Welcome back</h1>
                <p class="text-gray-500">Please enter your details to sign in.</p>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg flex items-center gap-3 text-sm font-medium">
                    <i class="fa-solid fa-circle-check text-lg"></i><div><?php echo $success_msg; ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-center gap-3 text-sm font-medium">
                    <i class="fa-solid fa-triangle-exclamation text-lg"></i><div><?php echo $error_msg; ?></div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-5" id="loginForm" onsubmit="return validateLogin(event)">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-regular fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" name="email" id="loginEmail" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition"
                            oninput="clearError('emailError', 'loginEmail')">
                    </div>
                    <p id="emailError" class="hidden text-red-500 text-xs mt-1 font-medium"></p>
                </div>

                <div>
                    <div class="flex justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <a href="forgot_password.php" class="text-sm font-medium text-brand-600 hover:text-brand-700">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" name="password" id="passwordField" required 
                            class="w-full pl-10 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition"
                            oninput="clearError('passError', 'passwordField')">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleVisibility('passwordField', 'eyeIcon')">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </div>
                    </div>
                    <p id="passError" class="hidden text-red-500 text-xs mt-1 font-medium"></p>
                </div>

                <div class="flex items-center justify-between mt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" class="w-4 h-4 text-brand-600 rounded border-gray-300 focus:ring-brand-500">
                        <span class="text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3.5 rounded-xl transition shadow-lg shadow-brand-500/30 mt-4">
                    Sign In
                </button>
            </form>

            <p class="text-center text-gray-500 text-sm mt-8">
                Don't have an account? 
                <a href="register.php" class="font-semibold text-brand-600 hover:text-brand-700">Sign up for free</a>
            </p>
        </div>
    </div>

    <div class="hidden lg:block w-1/2 relative bg-gray-900">
        <img src="https://images.pexels.com/photos/2255938/pexels-photo-2255938.jpeg?auto=compress&cs=tinysrgb&w=1200" alt="Agriculture transport" class="absolute inset-0 w-full h-full object-cover opacity-80">
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/40 to-transparent"></div>
        <div class="absolute bottom-12 left-12 right-12 text-white">
            <h2 class="text-3xl font-heading font-bold mb-4">Empowering Kenya's Farmers.</h2>
            <p class="text-lg text-gray-200 leading-relaxed max-w-lg">
                Join thousands of farmers and transporters using AgriMove to connect, negotiate, and deliver agricultural produce safely across the country.
            </p>
        </div>
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

        function showError(errorId, inputId, message) {
            const errorEl = document.getElementById(errorId);
            const inputEl = document.getElementById(inputId);
            errorEl.innerText = message;
            errorEl.classList.remove('hidden');
            inputEl.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
            inputEl.classList.remove('focus:border-brand-500', 'focus:ring-brand-500');
        }

        function clearError(errorId, inputId) {
            const errorEl = document.getElementById(errorId);
            const inputEl = document.getElementById(inputId);
            errorEl.classList.add('hidden');
            inputEl.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
            inputEl.classList.add('focus:border-brand-500', 'focus:ring-brand-500');
        }

        function validateLogin(e) {
            let isValid = true;
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('passwordField').value.trim();
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailRegex.test(email)) {
                showError('emailError', 'loginEmail', 'Please enter a valid email address.');
                isValid = false;
            }

            if (password === "") {
                showError('passError', 'passwordField', 'Password cannot be empty.');
                isValid = false;
            }

            if (!isValid) e.preventDefault();
            return isValid;
        }
    </script>
</body>
</html>