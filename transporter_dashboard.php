<?php
session_start();
include "db_connect.php";

// Ensure only logged-in transporters can access this page
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'transporter'){
    header("Location: login.php");
    exit();
}

$transporter_id = $_SESSION['user_id'];

// Helper function for safe queries
function safeQuery($conn, $sql){
    $res = $conn->query($sql);
    if(!$res) die("SQL Error: " . $conn->error);
    return $res;
}

// 1. Get Transporter Details & Verification Status
$nameRes = safeQuery($conn, "SELECT u.name, tp.is_verified FROM users u LEFT JOIN transporter_profiles tp ON u.id = tp.user_id WHERE u.id='$transporter_id'");
$nameRow = $nameRes->fetch_assoc();
$name = $nameRow['name'] ?? 'Driver';
$is_verified = (isset($nameRow['is_verified']) && $nameRow['is_verified'] == 1) ? true : false;

// 2. Dashboard Stats
$completedDeliveriesRes = safeQuery($conn, "SELECT COUNT(*) as total FROM transport_requests WHERE transporter_id='$transporter_id' AND status='delivered'");
$completedDeliveries = $completedDeliveriesRes->fetch_assoc()['total'] ?? 0;

$activeJobsRes = safeQuery($conn, "SELECT COUNT(*) as total FROM transport_requests WHERE transporter_id='$transporter_id' AND status IN ('accepted', 'in_transit')");
$activeJobsCount = $activeJobsRes->fetch_assoc()['total'] ?? 0;

$pendingMarketRes = safeQuery($conn, "SELECT COUNT(*) as total FROM transport_requests WHERE status='pending'");
$availableJobsCount = $pendingMarketRes->fetch_assoc()['total'] ?? 0;

// 3. Unread Messages Check
$unreadMsgRes = safeQuery($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id='$transporter_id' AND is_read=0");
$unreadMessages = $unreadMsgRes->fetch_assoc()['total'] ?? 0;

// 4. Fetch Available Jobs for the Market Feed (Limit 5)
$marketSql = "SELECT tr.*, p.name AS produce_name, p.weight AS total_amount, u.name AS farmer_name 
              FROM transport_requests tr 
              LEFT JOIN produce p ON tr.produce_id = p.id
              LEFT JOIN users u ON tr.farmer_id = u.id
              WHERE tr.status = 'pending' 
              ORDER BY tr.id DESC LIMIT 5";
$available_jobs = safeQuery($conn, $marketSql);

// 5. Fetch Current Active Job (if they have one)
$activeJobSql = "SELECT tr.*, p.name AS produce_name, p.weight AS total_amount, u.name AS farmer_name, u.phone AS farmer_phone, u.id as farmer_id
                 FROM transport_requests tr 
                 LEFT JOIN produce p ON tr.produce_id = p.id
                 LEFT JOIN users u ON tr.farmer_id = u.id
                 WHERE tr.transporter_id = '$transporter_id' AND tr.status IN ('accepted', 'in_transit') 
                 ORDER BY tr.id DESC LIMIT 1";
$active_job_res = safeQuery($conn, $activeJobSql);
$active_job = ($active_job_res->num_rows > 0) ? $active_job_res->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .locked-overlay { backdrop-filter: blur(4px); }
    </style>
</head>
<body class="text-gray-800 flex h-screen overflow-hidden">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col hidden md:flex flex-shrink-0 z-20">
        <div class="h-16 flex items-center px-6 border-b border-gray-200">
            <div class="flex items-center gap-2 text-blue-600 text-xl font-bold">
                <i class="fa-solid fa-truck-fast"></i> AgriMove
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="transporter_dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-blue-50 text-blue-700 rounded-lg font-medium transition">
                <i class="fa-solid fa-house w-5"></i> Dashboard
            </a>
            
            <a href="<?php echo $is_verified ? 'find_jobs.php' : '#'; ?>" 
               onclick="<?php echo $is_verified ? '' : 'return false;'; ?>"
               class="flex items-center justify-between px-4 py-3 text-gray-600 <?php echo $is_verified ? 'hover:bg-gray-50 hover:text-gray-900' : 'opacity-50 cursor-not-allowed'; ?> rounded-lg font-medium transition">
                <div class="flex items-center gap-3"><i class="fa-solid fa-magnifying-glass-location w-5"></i> Find Jobs</div>
                <?php if($is_verified && $availableJobsCount > 0): ?>
                    <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $availableJobsCount; ?></span>
                <?php endif; ?>
            </a>

            <a href="my_deliveries.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-route w-5"></i> My Deliveries
            </a>
            <a href="messages.php" class="flex items-center justify-between px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <div class="flex items-center gap-3"><i class="fa-regular fa-message w-5"></i> Messages</div>
                <?php if($unreadMessages > 0): ?>
                    <span class="bg-red-100 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $unreadMessages; ?></span>
                <?php endif; ?>
            </a>
            <a href="transporter_settings.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-gear w-5"></i> Settings
            </a>
        </nav>

        <div class="p-4 border-t border-gray-200">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
                <i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden text-gray-500 hover:text-gray-700 text-xl focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="text-xl font-semibold text-gray-800 hidden sm:block">Overview</h1>
            </div>
            
            <div class="flex items-center gap-4">
                <?php if($is_verified): ?>
                    <div class="hidden sm:flex items-center gap-2 text-green-600 text-xs font-bold px-2">
                        <i class="fa-solid fa-shield-check"></i> Verified
                    </div>
                <?php endif; ?>
                
                <button class="text-gray-400 hover:text-gray-600 relative">
                    <i class="fa-regular fa-bell text-xl"></i>
                    <?php if($unreadMessages > 0): ?>
                        <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                </button>
                <div class="h-8 w-px bg-gray-200 mx-2"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($name); ?></p>
                        <p class="text-xs text-gray-500">Transporter Account</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-700 font-bold border border-blue-200">
                        <?php echo strtoupper(substr($name, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            
            <?php if(!$is_verified): ?>
                <div class="mb-8 bg-blue-50 border border-blue-100 rounded-xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-lg flex-shrink-0">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-blue-900">Verification in Progress</h3>
                            <p class="text-blue-700 text-xs mt-0.5">Admin is reviewing your documents. Bidding will unlock once approved.</p>
                        </div>
                    </div>
                    <a href="transporter_settings.php" class="whitespace-nowrap bg-white text-blue-600 border border-blue-200 px-4 py-2 rounded-lg font-medium text-sm hover:bg-blue-50 transition">View Status</a>
                </div>
            <?php endif; ?>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Welcome to the road, <?php echo htmlspecialchars(explode(' ', trim($name))[0]); ?>! 👋</h2>
                <p class="text-gray-500 text-sm mt-1">Here's what's happening with your transport business today.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 text-xl">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Available Jobs</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $availableJobsCount; ?></p>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center text-orange-500 text-xl">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Active Deliveries</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $activeJobsCount; ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-600 text-xl">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Completed Trips</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $completedDeliveries; ?></p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white">
                        <h3 class="font-bold text-gray-800">Live Load Board</h3>
                        <?php if($is_verified): ?>
                            <a href="find_jobs.php" class="text-sm font-medium text-blue-600 hover:text-blue-700">View All</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="relative p-6">
                        <?php if(!$is_verified): ?>
                            <div class="absolute inset-0 z-10 rounded-b-xl flex items-center justify-center locked-overlay bg-white/60">
                                <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-100 text-center max-w-xs">
                                    <div class="w-12 h-12 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-lock"></i></div>
                                    <h4 class="font-bold text-gray-900 mb-1">Board Locked</h4>
                                    <p class="text-xs text-gray-500">Wait for Admin verification to start viewing and bidding on farm loads.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if($available_jobs->num_rows > 0): ?>
                            <div class="space-y-4">
                                <?php while($job = $available_jobs->fetch_assoc()): ?>
                                <div class="bg-white rounded-lg border border-gray-100 p-5 hover:bg-gray-50 transition">
                                    <div class="flex flex-col sm:flex-row justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="bg-gray-100 text-gray-600 text-[10px] uppercase font-bold px-2 py-1 rounded">TR-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                                <span class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($job['produce_name']); ?></span>
                                                <span class="text-xs text-gray-500">• <?php echo floatval($job['total_amount']); ?> Units</span>
                                            </div>
                                            <div class="flex items-center gap-3 mt-3">
                                                <div class="flex flex-col items-center justify-center">
                                                    <i class="fa-solid fa-circle-dot text-blue-400 text-[10px]"></i>
                                                    <div class="w-px h-4 bg-gray-200 my-0.5"></div>
                                                    <i class="fa-solid fa-location-dot text-red-400 text-[10px]"></i>
                                                </div>
                                                <div class="flex flex-col gap-2 text-sm text-gray-700">
                                                    <p><span class="font-medium"><?php echo htmlspecialchars($job['pickup_town']); ?></span></p>
                                                    <p><span class="font-medium"><?php echo htmlspecialchars($job['delivery_town']); ?></span></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex flex-row sm:flex-col items-center sm:items-end justify-between sm:justify-center border-t sm:border-t-0 sm:border-l border-gray-100 pt-4 sm:pt-0 sm:pl-4 min-w-[120px]">
                                            <div class="text-left sm:text-right mb-0 sm:mb-3">
                                                <p class="text-xs text-gray-500 uppercase font-medium mb-0.5">Distance</p>
                                                <p class="font-bold text-blue-600"><?php echo floatval($job['distance']); ?> KM</p>
                                            </div>
                                            <a href="<?php echo $is_verified ? 'job_details.php?id=' . $job['id'] : '#'; ?>" 
                                               onclick="<?php echo $is_verified ? '' : 'return false;'; ?>"
                                               class="<?php echo $is_verified ? 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-200' : 'bg-gray-100 text-gray-400 cursor-not-allowed'; ?> font-medium py-1.5 px-4 rounded-lg transition text-sm text-center">
                                                Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12 text-gray-500">
                                <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 text-xl mx-auto mb-3"><i class="fa-solid fa-mug-hot"></i></div>
                                <h3 class="font-medium text-gray-900 mb-1">No pending jobs</h3>
                                <p class="text-sm">Grab a coffee. New requests will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 h-fit">
                    <h3 class="font-bold text-gray-800 mb-6">Current Active Job</h3>
                    
                    <?php if($active_job): ?>
                        <div class="bg-white rounded-lg border border-blue-100 overflow-hidden">
                            <div class="bg-blue-50 px-4 py-3 border-b border-blue-100 flex justify-between items-center">
                                <span class="text-[10px] font-bold uppercase text-blue-700 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span> 
                                    <?php echo str_replace('_', ' ', $active_job['status']); ?>
                                </span>
                                <span class="text-xs text-gray-500 font-medium">TR-<?php echo str_pad($active_job['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="p-4">
                                <div class="mb-4">
                                    <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Cargo</p>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($active_job['produce_name']); ?> <span class="text-gray-500 text-sm">(<?php echo floatval($active_job['total_amount']); ?>)</span></p>
                                </div>
                                <div class="mb-5 bg-gray-50 p-3 rounded-lg border border-gray-100 text-sm">
                                    <div class="flex items-start gap-2 mb-2"><i class="fa-solid fa-circle-dot text-blue-400 mt-1 text-[10px]"></i><p class="font-medium text-gray-800"><?php echo htmlspecialchars($active_job['pickup_town']); ?></p></div>
                                    <div class="flex items-start gap-2"><i class="fa-solid fa-location-dot text-red-400 mt-1 text-[10px]"></i><p class="font-medium text-gray-800"><?php echo htmlspecialchars($active_job['delivery_town']); ?></p></div>
                                </div>
                                <div class="flex items-center justify-between gap-3 mb-5 border-t border-gray-100 pt-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-green-50 flex items-center justify-center text-green-600 font-bold border border-green-100 text-xs">
                                            <?php echo strtoupper(substr($active_job['farmer_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="text-[9px] text-gray-400 uppercase font-bold">Farmer</p>
                                            <p class="font-medium text-gray-900 text-xs"><?php echo htmlspecialchars($active_job['farmer_name']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex gap-1.5">
                                        <a href="tel:<?php echo htmlspecialchars($active_job['farmer_phone']); ?>" class="w-7 h-7 rounded bg-gray-50 text-gray-500 flex items-center justify-center hover:bg-gray-100 transition" title="Call Farmer"><i class="fa-solid fa-phone text-[10px]"></i></a>
                                        <a href="messages.php?uid=<?php echo $active_job['farmer_id']; ?>" class="w-7 h-7 rounded bg-gray-50 text-gray-500 flex items-center justify-center hover:bg-gray-100 transition" title="Message Farmer"><i class="fa-regular fa-message text-[10px]"></i></a>
                                    </div>
                                </div>
                                <a href="job_details.php?id=<?php echo $active_job['id']; ?>" class="w-full block bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 font-medium py-2 rounded-lg transition text-sm text-center">Manage Delivery</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 text-xl mx-auto mb-3"><i class="fa-solid fa-truck"></i></div>
                            <p class="text-sm">You don't have an active delivery right now.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            const sidebar = document.querySelector('aside');
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('absolute'); sidebar.classList.toggle('z-50');
            sidebar.classList.toggle('h-full');
        });
    </script>
</body>
</html>