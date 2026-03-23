<?php
session_start();
include "db_connect.php"; // Updated to match your actual db connection file name

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Helper functions
function safeQuery($conn, $sql){
    $res = $conn->query($sql);
    if(!$res) die("SQL Error: ".$conn->error);
    return $res;
}

function getCount($conn, $table, $where='1=1'){
    $res = safeQuery($conn,"SELECT COUNT(*) as total FROM $table WHERE $where");
    return $res->fetch_assoc()['total'] ?? 0;
}

// Dashboard Data
$nameRes = safeQuery($conn,"SELECT name FROM users WHERE id='$user_id'");
$nameRow = $nameRes->fetch_assoc();
$name = $nameRow['name'] ?? 'Farmer';

$totalRequests = getCount($conn,'transport_requests',"farmer_id='$user_id'");
$pendingRequests = getCount($conn,'transport_requests',"farmer_id='$user_id' AND status='pending'");
$completedDeliveries = getCount($conn,'transport_requests',"farmer_id='$user_id' AND status='delivered'");

// BUG FIX: Changed 'verified=1' to 'is_verified=1'
$availableTransporters = getCount($conn,'transporter_profiles',"is_verified=1");

// FIXED SQL: Now joining the `produce` table to get the actual crop name!
$requestsSql = "SELECT tr.*, p.name AS produce_name, u.name AS transporter_name 
                FROM transport_requests tr 
                LEFT JOIN produce p ON tr.produce_id = p.id
                LEFT JOIN users u ON tr.transporter_id = u.id
                WHERE tr.farmer_id='$user_id'
                ORDER BY tr.request_date DESC LIMIT 5";
$requests = safeQuery($conn,$requestsSql);

// FIXED SQL: Joining produce table here as well for activity logs
$activitySql = "SELECT sl.*, p.name AS produce_name 
                FROM status_logs sl
                LEFT JOIN transport_requests tr ON sl.transport_request_id = tr.id 
                LEFT JOIN produce p ON tr.produce_id = p.id
                WHERE tr.farmer_id='$user_id'
                ORDER BY sl.changed_at DESC LIMIT 5";
$activities = safeQuery($conn,$activitySql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-slate-800 flex h-screen overflow-hidden bg-slate-50/50">

    <aside class="w-64 bg-white border-r border-slate-200/60 flex flex-col hidden md:flex flex-shrink-0 z-20 shadow-sm">
        <div class="h-20 flex items-center px-6 border-b border-slate-100">
            <div class="flex items-center gap-3 text-emerald-600 text-2xl font-extrabold tracking-tight">
                <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-leaf text-emerald-600 text-sm"></i>
                </div>
                AgriMove
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-8 space-y-1.5 overflow-y-auto">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-4 px-4">Menu</div>
            
            <a href="farmer_dashboard.php" class="flex items-center gap-3 px-4 py-3.5 bg-emerald-50 text-emerald-700 rounded-xl font-bold transition border border-emerald-100 shadow-sm">
                <i class="fa-solid fa-house w-5"></i> Dashboard
            </a>
            <a href="create_request.php" class="flex items-center gap-3 px-4 py-3.5 text-slate-500 hover:bg-slate-50 hover:text-slate-900 rounded-xl font-medium transition">
                <i class="fa-solid fa-plus w-5"></i> Create Request
            </a>
            <a href="my_requests.php" class="flex items-center gap-3 px-4 py-3.5 text-slate-500 hover:bg-slate-50 hover:text-slate-900 rounded-xl font-medium transition">
                <i class="fa-solid fa-list w-5"></i> My Requests
            </a>
            
            <a href="messages.php" class="flex items-center justify-between px-4 py-3.5 text-slate-500 hover:bg-slate-50 hover:text-slate-900 rounded-xl font-medium transition">
                <div class="flex items-center gap-3"><i class="fa-regular fa-message w-5"></i> Messages</div>
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3.5 text-slate-500 hover:bg-slate-50 hover:text-slate-900 rounded-xl font-medium transition">
                <i class="fa-solid fa-gear w-5"></i> Settings
            </a>
        </nav>

        <div class="p-6 border-t border-slate-100">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-rose-500 hover:bg-rose-50 rounded-xl font-bold transition">
                <i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200/60 flex items-center justify-between px-8 flex-shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-4">
                <button class="md:hidden text-slate-400 hover:text-slate-600 text-xl">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight hidden sm:block">Farm Overview</h1>
            </div>
            
            <div class="flex items-center gap-6">
                <button class="text-slate-400 hover:text-slate-600 relative transition-colors">
                    <i class="fa-regular fa-bell text-xl"></i>
                    <span class="absolute top-0 right-0 w-2 h-2 bg-rose-500 rounded-full border border-white"></span>
                </button>
                <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($name); ?></p>
                        <p class="text-[10px] text-emerald-600 font-black uppercase tracking-widest mt-0.5">Farmer</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold border border-emerald-200 shadow-sm">
                        <?php echo strtoupper(substr($name, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-10 gap-4">
                <div>
                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Welcome back, <?php echo htmlspecialchars(explode(' ', trim($name))[0]); ?>! 👋</h2>
                    <p class="text-slate-500 text-sm mt-1.5 font-medium">Here's what's happening with your farm transport today.</p>
                </div>
                <a href="create_request.php" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-emerald-500/30 flex items-center gap-2 whitespace-nowrap active:scale-95 hover:-translate-y-0.5">
                    <i class="fa-solid fa-plus"></i> New Request
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 p-6 flex justify-between items-start group hover:shadow-md hover:border-blue-200 transition-all duration-300">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Requests</p>
                        <p class="text-4xl font-black text-slate-800"><?php echo $totalRequests; ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-500 text-xl group-hover:scale-110 transition-transform duration-300">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 p-6 flex justify-between items-start group hover:shadow-md hover:border-amber-200 transition-all duration-300">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Pending</p>
                        <p class="text-4xl font-black text-slate-800"><?php echo $pendingRequests; ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500 text-xl group-hover:scale-110 transition-transform duration-300">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 p-6 flex justify-between items-start group hover:shadow-md hover:border-emerald-200 transition-all duration-300">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Delivered</p>
                        <p class="text-4xl font-black text-slate-800"><?php echo $completedDeliveries; ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-500 text-xl group-hover:scale-110 transition-transform duration-300">
                        <i class="fa-solid fa-box-check"></i>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 p-6 flex justify-between items-start group hover:shadow-md hover:border-purple-200 transition-all duration-300">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Active Drivers</p>
                        <p class="text-4xl font-black text-slate-800"><?php echo $availableTransporters; ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-purple-50 flex items-center justify-center text-purple-500 text-xl group-hover:scale-110 transition-transform duration-300">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-200/60 overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-white/50">
                        <h3 class="font-extrabold text-slate-900 text-lg tracking-tight">Recent Transport Requests</h3>
                        <a href="my_requests.php" class="text-sm font-bold text-emerald-600 hover:text-emerald-700 transition-colors">View All &rarr;</a>
                    </div>
                    <div class="overflow-x-auto p-4">
                        <table class="w-full text-left border-separate border-spacing-y-2">
                            <thead>
                                <tr class="text-[10px] uppercase font-black tracking-widest text-slate-400">
                                    <th class="px-6 py-2">Produce</th>
                                    <th class="px-6 py-2">Route</th>
                                    <th class="px-6 py-2">Driver</th>
                                    <th class="px-6 py-2">Status & PIN</th>
                                    <th class="px-6 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if($requests->num_rows > 0): ?>
                                    <?php while($row = $requests->fetch_assoc()): ?>
                                    <tr class="group hover:bg-slate-50 transition-all">
                                        <td class="px-6 py-4 bg-white rounded-l-2xl border-y border-l border-slate-100 group-hover:border-emerald-100">
                                            <p class="font-extrabold text-slate-900"><?php echo htmlspecialchars($row['produce_name'] ?: 'Unknown'); ?></p>
                                            <p class="text-xs text-slate-500 font-medium mt-0.5 capitalize"><?php echo htmlspecialchars($row['cargo_type']); ?></p>
                                        </td>
                                        <td class="px-6 py-4 bg-white border-y border-slate-100 group-hover:border-emerald-100">
                                            <div class="flex items-center gap-2 text-slate-700 font-medium">
                                                <span><?php echo htmlspecialchars($row['pickup_town']); ?></span>
                                                <i class="fa-solid fa-arrow-right text-[10px] text-slate-300"></i>
                                                <span><?php echo htmlspecialchars($row['delivery_town']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 bg-white border-y border-slate-100 group-hover:border-emerald-100 text-slate-600 font-medium">
                                            <?php echo htmlspecialchars($row['transporter_name'] ?: 'Unassigned'); ?>
                                        </td>
                                        <td class="px-6 py-4 bg-white border-y border-slate-100 group-hover:border-emerald-100">
                                            <?php 
                                            $status = strtolower($row['status']);
                                            $bgClass = "bg-slate-100 text-slate-600";
                                            if($status == 'pending') $bgClass = "bg-amber-50 text-amber-600 border-amber-200/50";
                                            if($status == 'accepted') $bgClass = "bg-blue-50 text-blue-600 border-blue-200/50";
                                            if($status == 'in_transit') $bgClass = "bg-purple-50 text-purple-600 border-purple-200/50";
                                            if($status == 'delivered') $bgClass = "bg-emerald-50 text-emerald-600 border-emerald-200/50";
                                            ?>
                                            <div class="flex flex-col items-start gap-2">
                                                <span class="px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-widest border <?php echo $bgClass; ?>">
                                                    <?php echo str_replace('_', ' ', $status); ?>
                                                </span>
                                                
                                                <?php if($status === 'in_transit' && !empty($row['otp_code'])): ?>
                                                    <div class="bg-slate-900 text-white text-[10px] px-3 py-1.5 rounded-lg flex items-center gap-2 shadow-md">
                                                        <i class="fa-solid fa-key text-amber-400"></i>
                                                        <span class="uppercase tracking-widest font-bold text-slate-400">PIN:</span>
                                                        <span class="text-sm font-black tracking-widest text-emerald-400"><?php echo htmlspecialchars($row['otp_code']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 bg-white rounded-r-2xl border-y border-r border-slate-100 group-hover:border-emerald-100 text-right">
                                            <a href="view_request.php?id=<?php echo $row['id']; ?>" class="w-8 h-8 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center hover:bg-emerald-500 hover:text-white transition-colors ml-auto shadow-sm">
                                                <i class="fa-solid fa-chevron-right text-xs"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-16 text-center text-slate-500 bg-white rounded-2xl border border-slate-100 border-dashed">
                                            <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 text-3xl mx-auto mb-4"><i class="fa-solid fa-seedling"></i></div>
                                            <h3 class="font-bold text-slate-900 text-lg mb-1">No transport requests found</h3>
                                            <p class="text-sm mb-4">Post your produce to find a transporter.</p>
                                            <a href="create_request.php" class="bg-emerald-100 text-emerald-700 font-bold px-6 py-2.5 rounded-xl hover:bg-emerald-200 transition text-sm">Create Request</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 p-8 h-fit">
                    <h3 class="font-extrabold text-slate-900 text-lg tracking-tight mb-6">Recent Activity</h3>
                    
                    <div class="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-200 before:to-transparent">
                        <?php if($activities && $activities->num_rows > 0): ?>
                            <?php while($act = $activities->fetch_assoc()): ?>
                            <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-slate-100 text-slate-400 group-hover:bg-emerald-100 group-hover:text-emerald-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10 transition-colors">
                                    <i class="fa-solid fa-clock-rotate-left text-[10px]"></i>
                                </div>
                                <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-2xl border border-slate-100 bg-white shadow-sm group-hover:border-emerald-200 transition-colors">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-black text-slate-900 text-sm"><?php echo htmlspecialchars($act['produce_name'] ?: 'Order #'.$act['transport_request_id']); ?></span>
                                    </div>
                                    <p class="text-xs text-slate-500 font-medium">Status updated to <span class="font-bold text-emerald-600 uppercase tracking-wide px-1"><?php echo htmlspecialchars($act['new_status']); ?></span></p>
                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-2"><?php echo date("M d, g:i A", strtotime($act['changed_at'])); ?></p>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-slate-400 text-sm font-medium z-10 relative bg-white">
                                <p>No recent activity on your account.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>