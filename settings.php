<?php
session_start();
include "db_connect.php";

// Ensure only logged-in farmers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- 1. UPDATE PROFILE DETAILS ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $new_name = trim($_POST['name']);
        $new_email = trim($_POST['email']);
        $new_phone = trim($_POST['phone']);
        
        if (empty($new_name) || empty($new_email) || empty($new_phone)) {
            $error_msg = "All profile fields are required.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Please enter a valid email address.";
        } else {
            // Check if the new email already exists for a DIFFERENT user
            $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "si", $new_email, $farmer_id);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);
            
            if ($check_res->num_rows > 0) {
                $error_msg = "That email is already registered to another account.";
            } else {
                // Update the user details
                $update_sql = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "sssi", $new_name, $new_email, $new_phone, $farmer_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success_msg = "Profile updated successfully!";
                    $_SESSION['name'] = $new_name; // Update session name so the UI changes instantly
                } else {
                    $error_msg = "Database Error: " . mysqli_error($conn);
                }
            }
        }
    }
    
    // --- 2. UPDATE PASSWORD ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_msg = "All password fields are required.";
        } elseif (strlen($new_password) < 6) {
            $error_msg = "New password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error_msg = "New passwords do not match.";
        } else {
            // Verify current password first
            $pw_sql = "SELECT password FROM users WHERE id = ?";
            $pw_stmt = mysqli_prepare($conn, $pw_sql);
            mysqli_stmt_bind_param($pw_stmt, "i", $farmer_id);
            mysqli_stmt_execute($pw_stmt);
            $pw_res = mysqli_stmt_get_result($pw_stmt);
            $user_data = $pw_res->fetch_assoc();
            
            if (password_verify($current_password, $user_data['password'])) {
                // Current password is correct, hash and save the new one
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pw_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_pw_stmt = mysqli_prepare($conn, $update_pw_sql);
                mysqli_stmt_bind_param($update_pw_stmt, "si", $hashed_password, $farmer_id);
                
                if (mysqli_stmt_execute($update_pw_stmt)) {
                    $success_msg = "Password changed successfully! Please use your new password next time you log in.";
                } else {
                    $error_msg = "Failed to update password. Please try again.";
                }
            } else {
                $error_msg = "The current password you entered is incorrect.";
            }
        }
    }
}

// Fetch Current User Details to Pre-fill the form
$user_sql = "SELECT name, email, phone, role FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $farmer_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$current_user = $user_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        /* Adjusted padding so text doesn't overlap the left or right icons */
        .input-field { width: 100%; padding: 0.625rem 2.5rem; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; outline: none; transition: border-color 0.2s; }
        .input-field:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
        .input-label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.375rem; }
    </style>
</head>
<body class="text-gray-800 flex h-screen overflow-hidden">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col hidden md:flex flex-shrink-0 z-20">
        <div class="h-16 flex items-center px-6 border-b border-gray-200">
            <div class="flex items-center gap-2 text-green-600 text-xl font-bold">
                <i class="fa-solid fa-truck-fast"></i> AgriMove
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="farmer_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-house w-5"></i> Dashboard
            </a>
            <a href="create_request.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-plus w-5"></i> Create Request
            </a>
            <a href="my_requests.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-list w-5"></i> My Requests
            </a>
            <a href="messages.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-regular fa-message w-5"></i> Messages
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 bg-green-50 text-green-700 rounded-lg font-medium transition">
                <i class="fa-solid fa-gear w-5"></i> Settings
            </a>
        </nav>

        <div class="p-4 border-t border-gray-200">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
                <i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div class="flex items-center gap-4">
                <button class="md:hidden text-gray-500 hover:text-gray-700 text-xl">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="text-xl font-semibold text-gray-800 hidden sm:block">Account Settings</h1>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($current_user['name']); ?></p>
                        <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($current_user['role']); ?> Account</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-bold border border-green-200">
                        <?php echo strtoupper(substr($current_user['name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            
            <div class="mb-8 max-w-4xl mx-auto">
                <h2 class="text-2xl font-bold text-gray-900">Settings</h2>
                <p class="text-gray-500 text-sm mt-1">Manage your personal information and security preferences.</p>
            </div>

            <div class="max-w-4xl mx-auto">
                
                <?php if (!empty($success_msg)): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg flex items-center gap-3">
                        <i class="fa-solid fa-circle-check text-xl"></i><div><?php echo $success_msg; ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-center gap-3">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i><div><?php echo $error_msg; ?></div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 h-fit">
                        <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2 border-b border-gray-50 pb-3">
                            <i class="fa-regular fa-id-card text-green-600"></i> Profile Information
                        </h3>
                        
                        <form action="settings.php" method="POST" class="space-y-5">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div>
                                <label class="input-label">Full Name</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-regular fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" name="name" class="input-field" value="<?php echo htmlspecialchars($current_user['name']); ?>" required>
                                </div>
                            </div>

                            <div>
                                <label class="input-label">Email Address</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-regular fa-envelope text-gray-400"></i>
                                    </div>
                                    <input type="email" name="email" class="input-field" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                                </div>
                            </div>

                            <div>
                                <label class="input-label">Phone Number</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-phone text-gray-400"></i>
                                    </div>
                                    <input type="tel" name="phone" class="input-field" value="<?php echo htmlspecialchars($current_user['phone']); ?>" required>
                                </div>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 px-4 rounded-lg transition shadow-sm">
                                    Save Profile Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 h-fit">
                        <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2 border-b border-gray-50 pb-3">
                            <i class="fa-solid fa-shield-halved text-blue-600"></i> Security
                        </h3>
                        
                        <form action="settings.php" method="POST" class="space-y-5">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div>
                                <label class="input-label">Current Password</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" name="current_password" id="curr_pass" class="input-field" placeholder="Enter current password" required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleVisibility('curr_pass', 'icon_curr')">
                                        <i class="fa-solid fa-eye" id="icon_curr"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="input-label">New Password</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-key text-gray-400"></i>
                                    </div>
                                    <input type="password" name="new_password" id="new_pass" class="input-field" placeholder="Enter new password" required minlength="6">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleVisibility('new_pass', 'icon_new')">
                                        <i class="fa-solid fa-eye" id="icon_new"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Must be at least 6 characters long.</p>
                            </div>

                            <div>
                                <label class="input-label">Confirm New Password</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-check-double text-gray-400"></i>
                                    </div>
                                    <input type="password" name="confirm_password" id="conf_pass" class="input-field" placeholder="Confirm new password" required minlength="6">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleVisibility('conf_pass', 'icon_conf')">
                                        <i class="fa-solid fa-eye" id="icon_conf"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white font-medium py-2.5 px-4 rounded-lg transition shadow-sm">
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </main>
    </div>

    <script>
        function toggleVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>