<?php
session_start();
include "db_connect.php";

// STRICT SECURITY: Only Admin can access this page
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'admin'){
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['name'];
$admin_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// --- PROCESS EDIT & DELETE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        $target_id = intval($_POST['user_id']);

        // 1. HANDLE DELETE
        if ($_POST['action'] === 'delete') {
            if ($target_id === intval($admin_id)) {
                $error_msg = "Safety protocol: You cannot delete your own Admin account!";
            } else {
                // Delete the user (this will also delete their profile/requests if your DB uses CASCADE)
                $conn->query("DELETE FROM users WHERE id = $target_id");
                $success_msg = "User completely removed from the platform.";
            }
        } 
        // 2. HANDLE EDIT
        elseif ($_POST['action'] === 'edit') {
            $name = mysqli_real_escape_string($conn, trim($_POST['name']));
            $email = mysqli_real_escape_string($conn, trim($_POST['email']));
            $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
            $role = mysqli_real_escape_string($conn, strtolower(trim($_POST['role'])));

            $update_sql = "UPDATE users SET name='$name', email='$email', phone='$phone', role='$role' WHERE id=$target_id";
            if ($conn->query($update_sql)) {
                $success_msg = "User profile updated successfully!";
            } else {
                $error_msg = "Error updating user: " . $conn->error;
            }
        }
    }
}

// FETCH ALL USERS
$users_sql = "SELECT id, name, email, phone, role, created_at FROM users ORDER BY created_at DESC";
$users_res = $conn->query($users_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; } </style>
</head>
<body class="text-gray-800 flex h-screen overflow-hidden">

    <aside class="w-64 bg-slate-900 text-gray-300 border-r border-slate-800 flex flex-col hidden md:flex flex-shrink-0 z-20 h-full absolute md:relative">
        <div class="h-16 flex items-center px-6 border-b border-slate-800 bg-slate-950">
            <div class="flex items-center gap-2 text-white text-xl font-black tracking-tight">
                <i class="fa-solid fa-shield-halved text-blue-500"></i> AgriMove <span class="text-[10px] font-bold text-blue-500 uppercase tracking-widest ml-1 bg-blue-500/10 px-2 py-1 rounded">Admin</span>
            </div>
            <button id="closeSidebar" class="md:hidden ml-auto text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 px-4">Command Center</div>
            
            <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg font-medium transition">
                <i class="fa-solid fa-chart-pie w-5"></i> Overview
            </a>
            
            <a href="manage_users.php" class="flex items-center justify-between px-4 py-3 bg-blue-600 text-white rounded-lg font-medium shadow-lg shadow-blue-500/20">
                <div class="flex items-center gap-3"><i class="fa-solid fa-users w-5"></i> Manage Users</div>
            </a>

            <a href="all_deliveries.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg font-medium transition">
                <i class="fa-solid fa-map-location-dot w-5"></i> All Deliveries
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800 bg-slate-950">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-500/10 rounded-lg font-medium transition">
                <i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 md:px-8 flex-shrink-0 shadow-sm z-10">
            <div class="flex items-center gap-4">
                <button id="openSidebar" class="md:hidden text-gray-500 hover:text-gray-900 text-xl focus:outline-none"><i class="fa-solid fa-bars"></i></button>
                <h1 class="text-xl font-bold text-gray-800 hidden sm:block">User Directory</h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($admin_name); ?></p>
                    <p class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">Super Administrator</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold border-2 border-slate-200 shadow-sm">
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 md:p-8">
            <div class="max-w-7xl mx-auto">
                
                <?php if (!empty($success_msg)): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl flex items-center gap-3 font-medium shadow-sm">
                        <i class="fa-solid fa-circle-check text-xl"></i><div><?php echo $success_msg; ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center gap-3 font-medium shadow-sm">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i><div><?php echo $error_msg; ?></div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4 bg-gradient-to-r from-white to-blue-50/30">
                        <h2 class="font-bold text-gray-900 text-lg flex items-center gap-2">
                            <i class="fa-solid fa-address-book text-blue-500"></i> All Registered Users
                        </h2>
                        <div class="relative w-full sm:w-72">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                            <input type="text" id="searchInput" placeholder="Search name, email, or role..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                        </div>
                    </div>

                    <?php if ($users_res && $users_res->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse" id="usersTable">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 border-b border-gray-200">
                                        <th class="px-6 py-4 font-bold">User</th>
                                        <th class="px-6 py-4 font-bold">Role</th>
                                        <th class="px-6 py-4 font-bold">Contact</th>
                                        <th class="px-6 py-4 font-bold">Joined Date</th>
                                        <th class="px-6 py-4 font-bold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php while($user = $users_res->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50/50 transition user-row">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-9 h-9 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-sm border border-slate-200">
                                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <p class="font-bold text-gray-900 text-sm user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                                                        <p class="text-xs text-gray-500">ID: #<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 user-role">
                                                <?php 
                                                    $role = strtolower(trim($user['role']));
                                                    if ($role === 'admin') {
                                                        echo '<span class="bg-purple-100 text-purple-700 text-[10px] font-bold px-2.5 py-1 rounded border border-purple-200 uppercase tracking-wide">Admin</span>';
                                                    } elseif ($role === 'farmer') {
                                                        echo '<span class="bg-green-100 text-green-700 text-[10px] font-bold px-2.5 py-1 rounded border border-green-200 uppercase tracking-wide">Farmer</span>';
                                                    } else {
                                                        echo '<span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2.5 py-1 rounded border border-blue-200 uppercase tracking-wide">Transporter</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 user-contact">
                                                <p class="text-sm text-gray-700"><i class="fa-regular fa-envelope text-gray-400 mr-1.5"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                                                <p class="text-xs text-gray-500 mt-0.5"><i class="fa-solid fa-phone text-gray-400 mr-1.5"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600">
                                                <?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
                                                
                                                <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>', '<?php echo addslashes($user['email']); ?>', '<?php echo addslashes($user['phone']); ?>', '<?php echo $role; ?>')" class="text-gray-400 hover:text-blue-600 transition p-2 bg-gray-50 hover:bg-blue-50 rounded-lg border border-transparent hover:border-blue-200" title="Edit User">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                
                                                <?php if($user['id'] != $admin_id): ?>
                                                <form method="POST" class="inline-block m-0" onsubmit="return confirm('⚠️ WARNING: Are you sure you want to permanently delete <?php echo addslashes($user['name']); ?>? This action cannot be undone.');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="text-gray-400 hover:text-red-600 transition p-2 bg-gray-50 hover:bg-red-50 rounded-lg border border-transparent hover:border-red-200" title="Delete User">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>

                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-12 text-center text-gray-500">
                            <p>No users found in the database.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <div id="editModal" class="fixed inset-0 bg-slate-900/60 z-[100] hidden items-center justify-center p-4 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform scale-95 transition-transform" id="editModalContent">
            <div class="px-6 py-4 border-b border-gray-100 bg-slate-50 flex justify-between items-center">
                <h3 class="font-bold text-gray-900 text-lg"><i class="fa-solid fa-user-pen text-blue-500 mr-2"></i>Edit User</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-700 transition"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Full Name</label>
                    <input type="text" name="name" id="edit_name" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Email Address</label>
                    <input type="email" name="email" id="edit_email" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Platform Role</label>
                    <select name="role" id="edit_role" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="farmer">Farmer</option>
                        <option value="transporter">Transporter</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-white text-gray-700 border border-gray-300 font-bold py-2.5 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white font-bold py-2.5 rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- 1. LIVE SEARCH FUNCTIONALITY ---
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.user-row');

            rows.forEach(row => {
                let name = row.querySelector('.user-name').innerText.toLowerCase();
                let contact = row.querySelector('.user-contact').innerText.toLowerCase();
                let role = row.querySelector('.user-role').innerText.toLowerCase();
                
                if(name.includes(filter) || contact.includes(filter) || role.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // --- 2. MODAL FUNCTIONALITY ---
        const modal = document.getElementById('editModal');
        const modalContent = document.getElementById('editModalContent');

        function openEditModal(id, name, email, phone, role) {
            // Populate the form fields
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_role').value = role;
            
            // Show the modal with a smooth animation
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 10);
        }

        function closeEditModal() {
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 200);
        }

        // --- 3. MOBILE SIDEBAR ---
        const sidebar = document.querySelector('aside');
        document.getElementById('openSidebar').addEventListener('click', () => {
            sidebar.classList.remove('hidden');
            sidebar.classList.add('absolute', 'z-50', 'shadow-2xl');
        });
        document.getElementById('closeSidebar').addEventListener('click', () => {
            sidebar.classList.add('hidden');
            sidebar.classList.remove('absolute', 'z-50', 'shadow-2xl');
        });
    </script>
</body>
</html>