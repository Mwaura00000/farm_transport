<?php
session_start();
include "db_connect.php";

// STRICT SECURITY: Only Admin can access this page
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'admin'){
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['name'];
$success_msg = "";
$error_msg = "";

// --- BULLETPROOF DATABASE AUTO-UPDATER ---
// Safely adds the columns one by one so it never crashes
$cols = [
    'old_plate_no' => 'VARCHAR(50)', 
    'old_vehicle_type' => 'VARCHAR(50)', 
    'rejection_reason' => 'TEXT'
];
foreach($cols as $col => $type) {
    $check = $conn->query("SHOW COLUMNS FROM transporter_profiles LIKE '$col'");
    if($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE transporter_profiles ADD COLUMN $col $type NULL");
    }
}

// --- 1. NOTIFICATION LOGIC ---
$alert_res = $conn->query("SELECT COUNT(*) as alerts FROM transporter_profiles WHERE kyc_status = 'pending' AND is_verified = 0");
$total_alerts = ($alert_res) ? $alert_res->fetch_assoc()['alerts'] : 0;

// --- 2. PROCESS APPROVE / REJECT ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_user_id = intval($_POST['transporter_id']);
    
    if ($_POST['action'] === 'approve') {
        $sql = "UPDATE transporter_profiles SET kyc_status = 'approved', is_verified = 1, old_plate_no = NULL, old_vehicle_type = NULL, rejection_reason = NULL WHERE user_id = ?";
        $msg = "Transporter approved successfully!";
    } elseif ($_POST['action'] === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? 'Documents did not meet platform standards.');
        $sql = "UPDATE transporter_profiles SET kyc_status = 'rejected', is_verified = 0, rejection_reason = ? WHERE user_id = ?";
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    if ($_POST['action'] === 'reject') {
        mysqli_stmt_bind_param($stmt, "si", $reason, $target_user_id);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $target_user_id);
    }
    
    if(mysqli_stmt_execute($stmt)) {
        $success_msg = $msg ?? "Transporter rejected.";
        $alert_res = $conn->query("SELECT COUNT(*) as alerts FROM transporter_profiles WHERE kyc_status = 'pending' AND is_verified = 0");
        $total_alerts = $alert_res->fetch_assoc()['alerts'];
    } else {
        $error_msg = "Database Error: " . mysqli_error($conn);
    }
}

// --- 3. FETCH PENDING APPLICATIONS ---
$pending_sql = "
    SELECT u.id AS user_id, u.name, u.phone, u.email, 
           tp.plate_no, tp.vehicle_type, tp.id_document, tp.dl_document,
           tp.old_plate_no, tp.old_vehicle_type
    FROM users u 
    JOIN transporter_profiles tp ON u.id = tp.user_id 
    WHERE tp.kyc_status = 'pending' AND tp.is_verified = 0
    ORDER BY u.id ASC
";
$pending_res = $conn->query($pending_sql);

// --- 4. FETCH LIVE PLATFORM ACTIVITY ---
$recent_jobs_sql = "
    SELECT tr.*, p.name AS produce_name, p.weight AS total_amount, u.name AS farmer_name 
    FROM transport_requests tr 
    LEFT JOIN produce p ON tr.produce_id = p.id 
    LEFT JOIN users u ON tr.farmer_id = u.id 
    ORDER BY tr.id DESC LIMIT 6
";
$recent_jobs_res = $conn->query($recent_jobs_sql);

// --- 5. DASHBOARD STATS ---
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM transporter_profiles WHERE kyc_status='pending') as pending_kyc,
    (SELECT COUNT(*) FROM transport_requests WHERE status IN ('accepted', 'in_transit')) as active_trips,
    (SELECT COUNT(*) FROM transport_requests WHERE status='delivered') as completed_trips
";
$stats_res = $conn->query($stats_sql);
$stats = $stats_res ? $stats_res->fetch_assoc() : ['total_users'=>0, 'pending_kyc'=>0, 'active_trips'=>0, 'completed_trips'=>0];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-gray-800 flex h-screen overflow-hidden bg-gray-50/50">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col hidden md:flex flex-shrink-0 z-20 shadow-sm">
        <div class="h-16 flex items-center px-6 border-b border-gray-100">
            <div class="flex items-center gap-2 text-blue-600 text-xl font-bold">
                <i class="fa-solid fa-shield-halved"></i> AgriMove 
                <span class="text-[10px] bg-blue-50 text-blue-600 border border-blue-100 px-2 py-0.5 rounded ml-1 uppercase tracking-wider">Admin</span>
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 px-4">Command Center</div>
            
            <a href="admin_dashboard.php" class="flex items-center justify-between px-4 py-3 bg-blue-50 text-blue-700 rounded-xl font-semibold border border-blue-100 transition shadow-sm">
                <div class="flex items-center gap-3"><i class="fa-solid fa-chart-pie w-5"></i> Overview</div>
                <?php if($total_alerts > 0): ?>
                    <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full animate-pulse"><?php echo $total_alerts; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="manage_users.php" class="flex items-center gap-3 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-900 rounded-xl font-medium transition">
                <i class="fa-solid fa-users w-5"></i> Manage Users
            </a>

            <a href="all_deliveries.php" class="flex items-center gap-3 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-900 rounded-xl font-medium transition">
                <i class="fa-solid fa-route w-5"></i> Platform Logistics
            </a>
        </nav>

        <div class="p-6 border-t border-gray-100">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-50 hover:text-red-600 rounded-xl font-semibold transition">
                <i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        
        <header class="h-16 bg-white/80 backdrop-blur-md border-b border-gray-200 flex items-center justify-between px-6 md:px-8 flex-shrink-0 shadow-sm z-10">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-gray-900 tracking-tight">System Overview</h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($admin_name); ?></p>
                    <p class="text-[10px] text-blue-600 font-bold uppercase tracking-widest mt-0.5">Root Administrator</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-700 font-bold border border-blue-100">
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            <div class="max-w-7xl mx-auto">
                
                <?php if($success_msg): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-100 text-green-700 rounded-xl flex items-center gap-3 shadow-sm">
                        <i class="fa-solid fa-circle-check text-xl"></i>
                        <p class="font-medium text-sm"><?php echo $success_msg; ?></p>
                    </div>
                <?php endif; ?>

                <?php if($error_msg): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-100 text-red-700 rounded-xl flex items-center gap-3 shadow-sm">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                        <p class="font-medium text-sm"><?php echo $error_msg; ?></p>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 group hover:border-gray-300 transition-colors">
                        <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center text-gray-500 text-xl"><i class="fa-solid fa-users"></i></div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">Total Users</p>
                            <p class="text-3xl font-black text-gray-900"><?php echo $stats['total_users']; ?></p>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 group hover:border-orange-200 transition-colors">
                        <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center text-orange-500 text-xl"><i class="fa-solid fa-id-card-clip"></i></div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">Pending KYC</p>
                            <p class="text-3xl font-black text-gray-900"><?php echo $stats['pending_kyc']; ?></p>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 group hover:border-blue-200 transition-colors">
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 text-xl"><i class="fa-solid fa-truck-fast"></i></div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">Active Loads</p>
                            <p class="text-3xl font-black text-gray-900"><?php echo $stats['active_trips']; ?></p>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 group hover:border-green-200 transition-colors">
                        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-600 text-xl"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">Completed</p>
                            <p class="text-3xl font-black text-gray-900"><?php echo $stats['completed_trips']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    
                    <div class="xl:col-span-2" id="kyc-section">
                        <div class="flex justify-between items-end mb-4">
                            <h2 class="font-bold text-gray-900 text-lg flex items-center gap-2">
                                <i class="fa-solid fa-id-card text-orange-500"></i> KYC Verification Queue
                            </h2>
                            <span class="bg-orange-50 text-orange-600 border border-orange-100 text-xs font-bold px-3 py-1 rounded-full"><?php echo $stats['pending_kyc']; ?> Pending</span>
                        </div>

                        <div class="space-y-6">
                            <?php if ($pending_res && $pending_res->num_rows > 0): ?>
                                <?php while($row = $pending_res->fetch_assoc()): ?>
                                    
                                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md hover:border-blue-100 transition-all">
                                        <div class="bg-white px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 bg-gray-50 text-gray-600 rounded-xl flex items-center justify-center font-bold border border-gray-100">
                                                    <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($row['name']); ?></h3>
                                                    <p class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($row['phone'] ?: 'No Phone Number'); ?> • <?php echo htmlspecialchars($row['email']); ?></p>
                                                </div>
                                            </div>
                                            <?php if(!empty($row['old_plate_no'])): ?>
                                                <span class="bg-blue-50 text-blue-600 text-[10px] font-bold px-2.5 py-1 rounded-md uppercase tracking-wider border border-blue-100"><i class="fa-solid fa-pen-to-square"></i> Edit Request</span>
                                            <?php else: ?>
                                                <span class="bg-green-50 text-green-600 text-[10px] font-bold px-2.5 py-1 rounded-md uppercase tracking-wider border border-green-100"><i class="fa-solid fa-seedling"></i> New Driver</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                            
                                            <div class="p-5 rounded-xl bg-gray-50 border border-gray-100 <?php echo empty($row['old_plate_no']) ? 'opacity-50' : ''; ?>">
                                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Previous Details</h4>
                                                <?php if(!empty($row['old_plate_no'])): ?>
                                                    <div class="space-y-3 text-sm">
                                                        <p class="flex justify-between border-b border-gray-200/50 pb-2"><span class="text-gray-500">Plate:</span> <span class="font-mono font-bold text-gray-700"><?php echo htmlspecialchars($row['old_plate_no']); ?></span></p>
                                                        <p class="flex justify-between"><span class="text-gray-500">Type:</span> <span class="text-gray-700 font-medium capitalize"><?php echo htmlspecialchars($row['old_vehicle_type']); ?></span></p>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-xs text-gray-400 font-medium italic mt-2">No previous records. This is a first-time application.</p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="p-5 rounded-xl bg-blue-50/50 border border-blue-100 relative">
                                                <h4 class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mb-3">Requested Updates</h4>
                                                <div class="space-y-3 text-sm mb-5">
                                                    <p class="flex justify-between border-b border-blue-100 pb-2"><span class="text-gray-500">Plate:</span> <span class="font-mono font-black text-blue-700 text-base"><?php echo htmlspecialchars($row['plate_no']); ?></span></p>
                                                    <p class="flex justify-between"><span class="text-gray-500">Type:</span> <span class="text-gray-900 font-medium capitalize"><?php echo htmlspecialchars($row['vehicle_type']); ?></span></p>
                                                </div>
                                                
                                                <div class="flex gap-3">
                                                    <a href="<?php echo htmlspecialchars($row['id_document']); ?>" target="_blank" class="flex-1 text-center text-[10px] font-bold bg-white border border-gray-200 text-gray-700 px-2 py-2.5 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition shadow-sm"><i class="fa-regular fa-id-card text-blue-500 mr-1 text-sm"></i> View ID</a>
                                                    <a href="<?php echo htmlspecialchars($row['dl_document']); ?>" target="_blank" class="flex-1 text-center text-[10px] font-bold bg-white border border-gray-200 text-gray-700 px-2 py-2.5 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition shadow-sm"><i class="fa-solid fa-car-side text-blue-500 mr-1 text-sm"></i> View Logbook</a>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="bg-white px-6 py-5 border-t border-gray-100">
                                            <form method="POST" class="flex flex-col sm:flex-row gap-4 items-end sm:items-center justify-between">
                                                <input type="hidden" name="transporter_id" value="<?php echo $row['user_id']; ?>">
                                                
                                                <div class="w-full sm:w-1/2">
                                                    <input type="text" name="rejection_reason" placeholder="If rejecting, state reason..." class="w-full text-sm px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-red-400 focus:bg-white transition-colors">
                                                </div>

                                                <div class="flex gap-3 w-full sm:w-auto">
                                                    <button type="submit" name="action" value="reject" class="flex-1 sm:flex-none bg-white border border-red-200 text-red-600 text-sm font-bold px-6 py-2.5 rounded-xl hover:bg-red-50 transition shadow-sm">Reject</button>
                                                    <button type="submit" name="action" value="approve" class="flex-1 sm:flex-none bg-blue-600 text-white text-sm font-bold px-6 py-2.5 rounded-xl hover:bg-blue-700 shadow-md shadow-blue-600/20 transition">Approve</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center text-gray-500 border-dashed">
                                    <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center text-green-500 text-3xl mx-auto mb-4 border border-gray-100">
                                        <i class="fa-solid fa-check-double"></i>
                                    </div>
                                    <h3 class="font-bold text-gray-900 text-lg mb-1">Queue is Empty</h3>
                                    <p class="text-sm font-medium text-gray-400">All transporters have been verified and processed.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <h2 class="font-bold text-gray-900 text-lg mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-satellite-dish text-blue-500"></i> Platform Activity
                        </h2>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="divide-y divide-gray-100">
                                <?php if ($recent_jobs_res && $recent_jobs_res->num_rows > 0): ?>
                                    <?php while($job = $recent_jobs_res->fetch_assoc()): ?>
                                        <div class="p-5 hover:bg-gray-50 transition">
                                            <div class="flex justify-between items-center mb-3">
                                                <span class="text-[10px] font-black uppercase text-gray-500 tracking-widest bg-gray-100 px-2 py-1 rounded-md border border-gray-200">TR-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                                <span class="text-[10px] font-bold px-2.5 py-1 rounded-md bg-blue-50 border border-blue-100 text-blue-600 uppercase tracking-wider"><?php echo str_replace('_', ' ', $job['status']); ?></span>
                                            </div>
                                            <h3 class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($job['produce_name']); ?> <span class="text-gray-400 font-medium">(<?php echo floatval($job['total_amount']); ?> units)</span></h3>
                                            <div class="flex items-center gap-2 text-xs text-gray-600 mt-2 font-medium bg-gray-50 w-fit px-3 py-1.5 rounded-lg border border-gray-100">
                                                <?php echo htmlspecialchars($job['pickup_town']); ?> <i class="fa-solid fa-arrow-right text-[8px] text-gray-400"></i> <?php echo htmlspecialchars($job['delivery_town']); ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="p-10 text-center text-gray-400 text-sm font-medium">No activity recorded yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth' });
            });
        });
    </script>
</body>
</html>