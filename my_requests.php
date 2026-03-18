<?php
session_start();
include "db_connect.php";

// Ensure only logged-in farmers can access this page
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer'){
    header("Location: login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Get user details for the top nav
$nameRes = $conn->query("SELECT name FROM users WHERE id='$farmer_id'");
$name = ($nameRes && $nameRes->num_rows > 0) ? $nameRes->fetch_assoc()['name'] : 'Farmer';

// Handle status filtering
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Build the SQL query dynamically based on the filter
$sql = "SELECT tr.*, p.name AS produce_name, p.quantity, p.weight AS total_amount, u.name AS transporter_name 
        FROM transport_requests tr 
        LEFT JOIN produce p ON tr.produce_id = p.id
        LEFT JOIN users u ON tr.transporter_id = u.id
        WHERE tr.farmer_id = ?";

if ($status_filter !== 'all') {
    $sql .= " AND tr.status = ?";
}
$sql .= " ORDER BY tr.request_date DESC, tr.id DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($status_filter !== 'all') {
    mysqli_stmt_bind_param($stmt, "is", $farmer_id, $status_filter);
} else {
    mysqli_stmt_bind_param($stmt, "i", $farmer_id);
}

mysqli_stmt_execute($stmt);
$requests = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - AgriMove</title>
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
            <a href="my_requests.php" class="flex items-center gap-3 px-4 py-3 bg-green-50 text-green-700 rounded-lg font-medium transition">
                <i class="fa-solid fa-list w-5"></i> My Requests
            </a>
            <a href="transporters.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition">
                <i class="fa-solid fa-users w-5"></i> Transporters
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
                <h1 class="text-xl font-semibold text-gray-800 hidden sm:block">My Requests</h1>
            </div>
            
            <div class="flex items-center gap-4">
                <button class="text-gray-400 hover:text-gray-600 relative">
                    <i class="fa-regular fa-bell text-xl"></i>
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
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Transport History</h2>
                    <p class="text-gray-500 text-sm mt-1">Track and manage all your past and active delivery requests.</p>
                </div>
                <a href="create_request.php" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 px-5 rounded-lg transition shadow-sm flex items-center gap-2 whitespace-nowrap">
                    <i class="fa-solid fa-plus"></i> New Request
                </a>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                
                <div class="border-b border-gray-100 px-6 pt-4 flex gap-6 overflow-x-auto whitespace-nowrap">
                    <?php
                        $tabs = [
                            'all' => 'All Requests',
                            'pending' => 'Pending',
                            'accepted' => 'Accepted',
                            'in_transit' => 'In Transit',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Cancelled'
                        ];
                        
                        foreach ($tabs as $key => $label) {
                            $isActive = ($status_filter === $key);
                            $borderClass = $isActive ? 'border-green-600 text-green-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium';
                            echo "<a href='?status={$key}' class='pb-4 border-b-2 transition text-sm {$borderClass}'>{$label}</a>";
                        }
                    ?>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="px-6 py-4 font-medium">Request ID</th>
                                <th class="px-6 py-4 font-medium">Produce Details</th>
                                <th class="px-6 py-4 font-medium">Route</th>
                                <th class="px-6 py-4 font-medium">Date requested</th>
                                <th class="px-6 py-4 font-medium">Status</th>
                                <th class="px-6 py-4 font-medium text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php if($requests->num_rows > 0): ?>
                                <?php while($row = $requests->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <span class="font-semibold text-gray-900">#TR-<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($row['produce_name'] ?: 'Unknown'); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['cargo_type']); ?> • <?php echo floatval($row['total_amount']); ?> units</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2 text-gray-800">
                                                <i class="fa-solid fa-circle-dot text-blue-500 text-[10px]"></i>
                                                <span><?php echo htmlspecialchars($row['pickup_town']); ?></span>
                                            </div>
                                            <div class="w-px h-3 bg-gray-300 ml-1"></div>
                                            <div class="flex items-center gap-2 text-gray-800">
                                                <i class="fa-solid fa-location-dot text-red-500 text-xs"></i>
                                                <span><?php echo htmlspecialchars($row['delivery_town']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <?php 
                                            // Handle cases where request_date might be null
                                            if(!empty($row['request_date'])) {
                                                echo date("M d, Y", strtotime($row['request_date'])) . "<br>";
                                                echo "<span class='text-xs text-gray-400'>" . date("g:i A", strtotime($row['request_date'])) . "</span>";
                                            } else {
                                                echo "N/A";
                                            }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php 
                                        $status = strtolower($row['status']);
                                        $bgClass = "bg-gray-100 text-gray-800 border-gray-200";
                                        
                                        if($status == 'pending') $bgClass = "bg-orange-50 text-orange-700 border-orange-200";
                                        if($status == 'accepted') $bgClass = "bg-blue-50 text-blue-700 border-blue-200";
                                        if($status == 'in_transit') $bgClass = "bg-purple-50 text-purple-700 border-purple-200";
                                        if($status == 'delivered') $bgClass = "bg-green-50 text-green-700 border-green-200";
                                        if($status == 'cancelled') $bgClass = "bg-red-50 text-red-700 border-red-200";
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold border <?php echo $bgClass; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="view_request.php?id=<?php echo $row['id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-green-600 transition shadow-sm">
                                            <i class="fa-solid fa-eye text-xs"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-16 text-center text-gray-500">
                                        <div class="mb-4 text-5xl text-gray-200"><i class="fa-solid fa-box-open"></i></div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-1">No requests found</h3>
                                        <p>You don't have any transport requests matching this status.</p>
                                        <?php if($status_filter !== 'all'): ?>
                                            <a href="?status=all" class="text-green-600 font-medium hover:underline text-sm mt-3 inline-block">View all requests</a>
                                        <?php else: ?>
                                            <a href="create_request.php" class="mt-4 inline-flex bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition shadow-sm">
                                                Create your first request
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

</body>
</html>