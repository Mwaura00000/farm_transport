<?php
session_start();
include "db_connect.php";

// Ensure only logged-in transporters can access this page
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'transporter'){
    header("Location: login.php");
    exit();
}

$transporter_id = $_SESSION['user_id'];

// Helper functions
function safeQuery($conn, $sql){
    $res = $conn->query($sql);
    if(!$res) die("SQL Error: ".$conn->error);
    return $res;
}

// Get Transporter Details
$nameRes = safeQuery($conn, "SELECT name FROM users WHERE id='$transporter_id'");
$nameRow = $nameRes->fetch_assoc();
$name = $nameRow['name'] ?? 'Driver';

// Dashboard Stats
$completedDeliveriesRes = safeQuery($conn, "SELECT COUNT(*) as total FROM transport_requests WHERE transporter_id='$transporter_id' AND status='delivered'");
$completedDeliveries = $completedDeliveriesRes->fetch_assoc()['total'] ?? 0;

$activeJobsRes = safeQuery($conn, "SELECT COUNT(*) as total FROM transport_requests WHERE transporter_id='$transporter_id' AND status IN ('accepted', 'in_transit')");
$activeJobsCount = $activeJobsRes->fetch_assoc()['total'] ?? 0;

$pendingMarketRes = safeQuery($conn, "SELECT COUNT(*) as total FROM transport_requests WHERE status='pending'");
$availableJobsCount = $pendingMarketRes->fetch_assoc()['total'] ?? 0;

// Fetch Available Jobs for the Market Feed (Limit 5)
// Ordering by tr.id DESC to get the newest jobs
$marketSql = "SELECT tr.*, p.name AS produce_name, p.weight AS total_amount, u.name AS farmer_name 
              FROM transport_requests tr 
              LEFT JOIN produce p ON tr.produce_id = p.id
              LEFT JOIN users u ON tr.farmer_id = u.id
              WHERE tr.status = 'pending' 
              ORDER BY tr.id DESC LIMIT 5";
$available_jobs = safeQuery($conn, $marketSql);

// Fetch Current Active Job (if they have one)
// Ordering by tr.id DESC for the active job as well
$activeJobSql = "SELECT tr.*, p.name AS produce_name, p.weight AS total_amount, u.name AS farmer_name, u.phone AS farmer_phone
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
    </style>
</head>
<body class="text-gray-800 flex h-screen overflow-hidden">

    <aside class="w-64 bg-gray-900 text-gray-300 border-r border-gray-800 flex flex-col hidden md:flex flex-shrink-0 z-20">
        <div class="h-16 flex items-center px-6 border-b border-gray-800 bg-gray-950">
            <div class="flex items-center gap-2 text-blue-500 text-xl font-bold">
                <i class="fa-solid fa-truck-fast"></i> AgriMove
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-4">Menu</div>
            <a href="transporter_dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600 text-white rounded-lg font-medium transition shadow-lg shadow-blue-500/20">
                <i class="fa-solid fa-gauge w-5"></i> Dashboard
            </a>
            <a href="find_jobs.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-lg font-medium transition">
                <i class="fa-solid fa-magnifying-glass-location w-5"></i> Find Jobs
                <?php if($availableJobsCount > 0): ?>
                    <span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $availableJobsCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="my_deliveries.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-lg font-medium transition">
                <i class="fa-solid fa-route w-5"></i> My Deliveries
            </a>
            <a href="messages.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-lg font-medium transition">
                <i class="fa-regular fa-message w-5"></i> Messages
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-lg font-medium transition">
                <i class="fa-solid fa-user-gear w-5"></i> Settings
            </a>
        </nav>

        <div class="p-4 border-t border-gray-800 bg-gray-950">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-500/10 rounded-lg font-medium transition">
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
                <h1 class="text-xl font-semibold text-gray-800 hidden sm:block">Driver Overview</h1>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 bg-green-50 text-green-700 px-3 py-1.5 rounded-full text-sm font-medium border border-green-200 mr-2">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> Available
                </div>
                <div class="h-8 w-px bg-gray-200 mx-1"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($name); ?></p>
                        <p class="text-xs text-blue-600 font-medium">Transporter</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold border border-blue-200">
                        <?php echo strtoupper(substr($name, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Welcome to the road, <?php echo htmlspecialchars(explode(' ', trim($name))[0]); ?>! 🚚</h2>
                <p class="text-gray-500 text-sm mt-1">Check your active loads or find a new job below.</p>
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
                
                <div class="lg:col-span-2 space-y-6">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-lg font-bold text-gray-900">Live Load Board</h3>
                        <a href="find_jobs.php" class="text-sm font-medium text-blue-600 hover:text-blue-700">View All Jobs &rarr;</a>
                    </div>
                    
                    <?php if($available_jobs->num_rows > 0): ?>
                        <div class="space-y-4">
                            <?php while($job = $available_jobs->fetch_assoc()): ?>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-blue-300 transition group relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full bg-gray-200 group-hover:bg-blue-500 transition"></div>
                                
                                <div class="flex flex-col sm:flex-row justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="bg-gray-100 text-gray-600 text-[10px] uppercase font-bold px-2 py-1 rounded">TR-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                            <span class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($job['produce_name']); ?></span>
                                            <span class="text-xs text-gray-500">• <?php echo floatval($job['total_amount']); ?> Units</span>
                                        </div>
                                        
                                        <div class="flex items-center gap-3 mt-3">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fa-solid fa-circle-dot text-blue-500 text-[10px]"></i>
                                                <div class="w-px h-4 bg-gray-300 my-0.5"></div>
                                                <i class="fa-solid fa-location-dot text-red-500 text-[10px]"></i>
                                            </div>
                                            <div class="flex flex-col gap-2 text-sm text-gray-700">
                                                <p><span class="font-medium"><?php echo htmlspecialchars($job['pickup_town']); ?></span> <span class="text-gray-400 text-xs ml-1">(<?php echo htmlspecialchars($job['pickup_county']); ?>)</span></p>
                                                <p><span class="font-medium"><?php echo htmlspecialchars($job['delivery_town']); ?></span> <span class="text-gray-400 text-xs ml-1">(<?php echo htmlspecialchars($job['delivery_county']); ?>)</span></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex flex-row sm:flex-col items-center sm:items-end justify-between sm:justify-center border-t sm:border-t-0 sm:border-l border-gray-100 pt-4 sm:pt-0 sm:pl-4 min-w-[120px]">
                                        <div class="text-left sm:text-right mb-0 sm:mb-3">
                                            <p class="text-xs text-gray-500 uppercase">Distance</p>
                                            <p class="font-bold text-gray-900"><?php echo floatval($job['distance']); ?> KM</p>
                                        </div>
                                        <a href="job_details.php?id=<?php echo $job['id']; ?>" class="bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white border border-blue-200 font-medium py-1.5 px-4 rounded-lg transition text-sm text-center">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-500">
                            <i class="fa-solid fa-mug-hot text-4xl text-gray-300 mb-3"></i>
                            <h3 class="font-medium text-gray-900 mb-1">No pending jobs right now</h3>
                            <p class="text-sm">Grab a coffee. New transport requests from farmers will appear here automatically.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-6">Current Active Job</h3>
                    
                    <?php if($active_job): ?>
                        <div class="bg-white rounded-xl shadow-sm border-2 border-orange-200 overflow-hidden">
                            <div class="bg-orange-50 px-5 py-3 border-b border-orange-200 flex justify-between items-center">
                                <span class="text-xs font-bold uppercase text-orange-700 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span> 
                                    <?php echo str_replace('_', ' ', $active_job['status']); ?>
                                </span>
                                <span class="text-xs text-orange-600 font-medium">TR-<?php echo str_pad($active_job['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            
                            <div class="p-5">
                                <div class="mb-4">
                                    <p class="text-sm text-gray-500 uppercase tracking-wide text-[10px] font-bold mb-1">Cargo</p>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($active_job['produce_name']); ?> (<?php echo floatval($active_job['total_amount']); ?>)</p>
                                </div>
                                
                                <div class="mb-5 bg-gray-50 p-3 rounded-lg border border-gray-100 text-sm">
                                    <div class="flex items-start gap-2 mb-2">
                                        <i class="fa-solid fa-circle-dot text-blue-500 mt-1 text-[10px]"></i>
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($active_job['pickup_town']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($active_job['pickup_exact_address'] ?: 'Contact farmer for exact spot'); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <i class="fa-solid fa-location-dot text-red-500 mt-1 text-[10px]"></i>
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($active_job['delivery_town']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($active_job['delivery_exact_address']); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 mb-5">
                                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-bold border border-green-200">
                                        <?php echo strtoupper(substr($active_job['farmer_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase">Farmer</p>
                                        <p class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($active_job['farmer_name']); ?></p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <a href="tel:<?php echo htmlspecialchars($active_job['farmer_phone']); ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 rounded-lg transition text-xs flex justify-center items-center gap-2">
                                        <i class="fa-solid fa-phone"></i> Call
                                    </a>
                                    <a href="job_details.php?id=<?php echo $active_job['id']; ?>" class="bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 rounded-lg transition text-xs flex justify-center items-center gap-2 shadow-sm">
                                        Update Status <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center border-dashed border-2">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 text-2xl mx-auto mb-3">
                                <i class="fa-solid fa-truck"></i>
                            </div>
                            <h3 class="font-medium text-gray-900 mb-1">Ready for a load</h3>
                            <p class="text-xs text-gray-500 mb-4">You don't have an active delivery right now.</p>
                            <a href="find_jobs.php" class="inline-block bg-blue-50 text-blue-600 hover:bg-blue-100 font-medium py-2 px-4 rounded-lg transition text-sm">
                                Browse Load Board
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

</body>
</html>