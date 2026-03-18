<?php
session_start();
include "db_connect.php";

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'farmer') header("Location: farmer_dashboard.php");
    else header("Location: transporter_dashboard.php");
    exit();
}

$error_msg = "";
$default_role = isset($_GET['role']) && $_GET['role'] === 'transporter' ? 'transporter' : 'farmer';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = trim($_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($role)) {
        $error_msg = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_msg = "Passwords do not match.";
    } else {
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        $check_res = mysqli_stmt_get_result($check_stmt);

        if ($check_res->num_rows > 0) {
            $error_msg = "An account with that email already exists. Please log in.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "sssss", $name, $email, $phone, $hashed_password, $role);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $new_user_id = mysqli_insert_id($conn);
                if ($role === 'transporter') {
                    $profile_sql = "INSERT INTO transporter_profiles (user_id) VALUES (?)";
                    $profile_stmt = mysqli_prepare($conn, $profile_sql);
                    if($profile_stmt) {
                        mysqli_stmt_bind_param($profile_stmt, "i", $new_user_id);
                        mysqli_stmt_execute($profile_stmt);
                    }
                }

                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role;
                $_SESSION['success_msg'] = "Welcome to AgriMove! Your account has been created.";

                if ($role === 'farmer') header("Location: farmer_dashboard.php");
                else header("Location: transporter_dashboard.php");
                exit();
            } else {
                $error_msg = "Database Error: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AgriMove</title>
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
    <style>
        input[type="radio"]:checked + div { border-color: #16a34a; background-color: #f0fdf4; }
        input[type="radio"]:checked + div .check-icon { opacity: 1; transform: scale(1); }
    </style>
</head>
<body class="font-sans text-gray-800 antialiased bg-white flex h-screen overflow-hidden">

    <div class="w-full lg:w-1/2 flex flex-col px-8 sm:px-16 md:px-24 py-8 overflow-y-auto">
        <div class="max-w-md w-full mx-auto my-auto py-8">
            
            <a href="index.php" class="flex items-center gap-2 mb-8 w-fit hover:opacity-80 transition">
                <div class="w-8 h-8 bg-brand-600 rounded-lg flex items-center justify-center text-white text-base shadow-md">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <span class="font-heading font-bold text-xl text-gray-900 tracking-tight">AgriMove</span>
            </a>

            <div class="mb-6">
                <h1 class="text-3xl font-heading font-bold text-gray-900 mb-2">Create an account</h1>
                <p class="text-gray-500">Join AgriMove to connect with the agricultural network.</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-center gap-3 text-sm font-medium">
                    <i class="fa-solid fa-triangle-exclamation text-lg"></i><div><?php echo $error_msg; ?></div>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="space-y-5" id="regForm" onsubmit="return validateRegister(event)">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">I am signing up as a:</label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="cursor-pointer relative">
                            <input type="radio" name="role" value="farmer" class="peer sr-only" <?php echo $default_role === 'farmer' ? 'checked' : ''; ?>>
                            <div class="border-2 border-gray-200 rounded-xl p-4 transition-all duration-200 hover:bg-gray-50">
                                <div class="flex items-center justify-between mb-2">
                                    <i class="fa-solid fa-seedling text-2xl text-green-600"></i>
                                    <div class="check-icon w-5 h-5 rounded-full bg-brand-600 text-white flex items-center justify-center opacity-0 transform scale-50 transition-all duration-200">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </div>
                                </div>
                                <h3 class="font-bold text-gray-900">Farmer</h3>
                                <p class="text-xs text-gray-500 mt-1">I want to move produce</p>
                            </div>
                        </label>

                        <label class="cursor-pointer relative">
                            <input type="radio" name="role" value="transporter" class="peer sr-only" <?php echo $default_role === 'transporter' ? 'checked' : ''; ?>>
                            <div class="border-2 border-gray-200 rounded-xl p-4 transition-all duration-200 hover:bg-gray-50">
                                <div class="flex items-center justify-between mb-2">
                                    <i class="fa-solid fa-truck text-2xl text-blue-600"></i>
                                    <div class="check-icon w-5 h-5 rounded-full bg-brand-600 text-white flex items-center justify-center opacity-0 transform scale-50 transition-all duration-200">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </div>
                                </div>
                                <h3 class="font-bold text-gray-900">Transporter</h3>
                                <p class="text-xs text-gray-500 mt-1">I own a transport vehicle</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-regular fa-user text-gray-400"></i>
                        </div>
                        <input type="text" name="name" id="regName" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition"
                            oninput="clearError('nameError', 'regName')">
                    </div>
                    <p id="nameError" class="hidden text-red-500 text-xs mt-1 font-medium"></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-regular fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" name="email" id="regEmail" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition"
                                oninput="clearError('emailError', 'regEmail')">
                        </div>
                        <p id="emailError" class="hidden text-red-500 text-xs mt-1 font-medium"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone (Kenya)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-phone text-gray-400"></i>
                            </div>
                            <input type="tel" name="phone" id="regPhone" required placeholder="07XX..." value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition"
                                oninput="clearError('phoneError', 'regPhone')">
                        </div>
                        <p id="phoneError" class="hidden text-red-500 text-xs mt-1 font-medium"></p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" name="password" id="regPass" required minlength="6"
                            class="w-full pl-10 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition"
                            oninput="clearError('passError', 'regPass'); checkMatch();">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleVisibility('regPass', 'eyeReg')">
                            <i class="fa-solid fa-eye" id="eyeReg"></i>
                        </div>
                    </div>
                    <p id="passError" class="hidden text-red-500 text-xs mt-1 font-medium"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-check-double text-gray-400"></i>
                        </div>
                        <input type="password" name="confirm_password" id="confPass" required minlength="6"
                            class="w-full pl-10 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition"
                            oninput="clearError('confError', 'confPass'); checkMatch();">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleVisibility('confPass', 'eyeConf')">
                            <i class="fa-solid fa-eye" id="eyeConf"></i>
                        </div>
                    </div>
                    <p id="confError" class="hidden text-red-500 text-xs mt-1 font-medium"></p>
                </div>

                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3.5 rounded-xl transition shadow-lg shadow-brand-500/30 mt-4">
                    Create Account
                </button>
            </form>

            <p class="text-center text-gray-500 text-sm mt-8">
                Already have an account? 
                <a href="login.php" class="font-semibold text-brand-600 hover:text-brand-700">Log in here</a>
            </p>
        </div>
    </div>

    <div class="hidden lg:block w-1/2 relative bg-gray-900">
        <img src="https://images.pexels.com/photos/2199293/pexels-photo-2199293.jpeg?auto=compress&cs=tinysrgb&w=1200" alt="Transport truck" class="absolute inset-0 w-full h-full object-cover opacity-80">
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/40 to-transparent"></div>
        <div class="absolute bottom-12 left-12 right-12 text-white">
            <div class="flex gap-2 mb-6">
                <div class="w-2 h-2 rounded-full bg-white opacity-50"></div>
                <div class="w-8 h-2 rounded-full bg-brand-500"></div>
                <div class="w-2 h-2 rounded-full bg-white opacity-50"></div>
            </div>
            <h2 class="text-3xl font-heading font-bold mb-4">Join the Network.</h2>
            <p class="text-lg text-gray-200 leading-relaxed max-w-lg">
                Create a free account in seconds. Whether you're moving a sack of potatoes or a lorry of maize, AgriMove is built for you.
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

        function checkMatch() {
            const pass = document.getElementById('regPass').value;
            const conf = document.getElementById('confPass').value;
            if(conf.length > 0 && pass !== conf) {
                showError('confError', 'confPass', 'Passwords do not match yet.');
            } else if (conf.length > 0 && pass === conf) {
                clearError('confError', 'confPass');
            }
        }

        function validateRegister(e) {
            let isValid = true;
            
            const name = document.getElementById('regName').value.trim();
            const email = document.getElementById('regEmail').value.trim();
            const phone = document.getElementById('regPhone').value.trim();
            const pass = document.getElementById('regPass').value;
            const conf = document.getElementById('confPass').value;
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            // Kenya phone: starts with 07, 01, +2547, or +2541 followed by exactly 8 digits
            const phoneRegex = /^(07|01|\+2547|\+2541)[0-9]{8}$/;

            if (name.length < 3) {
                showError('nameError', 'regName', 'Name must be at least 3 characters.');
                isValid = false;
            }

            if (!emailRegex.test(email)) {
                showError('emailError', 'regEmail', 'Please enter a valid email address.');
                isValid = false;
            }

            if (!phoneRegex.test(phone)) {
                showError('phoneError', 'regPhone', 'Enter a valid Kenyan phone number (e.g. 0712345678).');
                isValid = false;
            }

            if (pass.length < 6) {
                showError('passError', 'regPass', 'Password must be at least 6 characters.');
                isValid = false;
            }

            if (pass !== conf) {
                showError('confError', 'confPass', 'Passwords must match.');
                isValid = false;
            }

            if (!isValid) e.preventDefault();
            return isValid;
        }
    </script>
</body>
</html>