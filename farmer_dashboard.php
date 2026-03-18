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
$availableTransporters = getCount($conn,'transporter_profiles',"verified=1");

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
    <title>Dashboard - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        /* Custom scrollbar for a cleaner look */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
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
            <a href="farmer_dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-green-50 text-green-700 rounded-lg font-medium transition">
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
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
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
                <h1 class="text-xl font-semibold text-gray-800 hidden sm:block">Overview</h1>
            </div>
            
            <div class="flex items-center gap-4">
                <button class="text-gray-400 hover:text-gray-600 relative">
                    <i class="fa-regular fa-bell text-xl"></i>
                    <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>
                <div class="h-8 w-px bg-gray-200 mx-2"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($name); ?></p>
                        <p class="text-xs text-gray-500">Farmer Account</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-bold border border-green-200">
                        <?php echo strtoupper(substr($name, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Welcome back, <?php echo htmlspecialchars(explode(' ', trim($name))[0]); ?>! 👋</h2>
                    <p class="text-gray-500 text-sm mt-1">Here's what's happening with your farm transport today.</p>
                </div>
                <a href="create_request.php" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 px-5 rounded-lg transition shadow-sm flex items-center gap-2 whitespace-nowrap">
                    <i class="fa-solid fa-plus"></i> New Request
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 text-xl">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Total Requests</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalRequests; ?></p>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center text-orange-600 text-xl">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Pending</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $pendingRequests; ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-600 text-xl">
                        <i class="fa-solid fa-box-check"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Delivered</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $completedDeliveries; ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 text-xl">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Active Drivers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $availableTransporters; ?></p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white">
                        <h3 class="font-bold text-gray-800">Recent Transport Requests</h3>
                        <a href="my_requests.php" class="text-sm font-medium text-green-600 hover:text-green-700">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                    <th class="px-6 py-3 font-medium">Produce</th>
                                    <th class="px-6 py-3 font-medium">Route</th>
                                    <th class="px-6 py-3 font-medium">Driver</th>
                                    <th class="px-6 py-3 font-medium">Status</th>
                                    <th class="px-6 py-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if($requests->num_rows > 0): ?>
                                    <?php while($row = $requests->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4">
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($row['produce_name'] ?: 'Unknown'); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['cargo_type']); ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-gray-800"><?php echo htmlspecialchars($row['pickup_town']); ?> <i class="fa-solid fa-arrow-right text-gray-300 text-xs mx-1"></i> <?php echo htmlspecialchars($row['delivery_town']); ?></p>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">
                                            <?php echo htmlspecialchars($row['transporter_name'] ?: 'Unassigned'); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php 
                                            $status = strtolower($row['status']);
                                            $bgClass = "bg-gray-100 text-gray-800";
                                            if($status == 'pending') $bgClass = "bg-orange-100 text-orange-800 border border-orange-200";
                                            if($status == 'accepted') $bgClass = "bg-blue-100 text-blue-800 border border-blue-200";
                                            if($status == 'in_transit') $bgClass = "bg-purple-100 text-purple-800 border border-purple-200";
                                            if($status == 'delivered') $bgClass = "bg-green-100 text-green-800 border border-green-200";
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $bgClass; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="view_request.php?id=<?php echo $row['id']; ?>" class="text-gray-400 hover:text-green-600 transition p-2">
                                                <i class="fa-solid fa-chevron-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                            <div class="mb-3 text-4xl text-gray-300"><i class="fa-solid fa-seedling"></i></div>
                                            <p>No transport requests found.</p>
                                            <a href="create_request.php" class="text-green-600 font-medium hover:underline text-sm mt-1 inline-block">Create your first request</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-800 mb-6">Recent Activity</h3>
                    
                    <div class="space-y-6">
                        <?php if($activities && $activities->num_rows > 0): ?>
                            <?php while($act = $activities->fetch_assoc()): ?>
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center flex-shrink-0 text-gray-500 border border-gray-100">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-800">
                                        <span class="font-medium"><?php echo htmlspecialchars($act['produce_name'] ?: 'Order #'.$act['transport_request_id']); ?></span> status changed to <span class="font-medium text-gray-900"><?php echo htmlspecialchars($act['new_status']); ?></span>.
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1"><?php echo date("M d, g:i A", strtotime($act['changed_at'])); ?></p>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-6 text-gray-500 text-sm">
                                <p>No recent activity.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>