<?php
session_start();
include "db_connect.php";

// STRICT SECURITY: Only Admin can access this page
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'admin'){
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['name'];

// FETCH ALL DELIVERIES WITH DETAILS
$deliveries_sql = "
    SELECT tr.*, 
           p.name AS produce_name, p.weight AS total_weight,
           fu.name AS farmer_name, fu.phone AS farmer_phone,
           tu.name AS transporter_name, tu.phone AS transporter_phone
    FROM transport_requests tr
    LEFT JOIN produce p ON tr.produce_id = p.id
    LEFT JOIN users fu ON tr.farmer_id = fu.id
    LEFT JOIN users tu ON tr.transporter_id = tu.id
    ORDER BY tr.id DESC
";
$deliveries_res = $conn->query($deliveries_sql);

if (!$deliveries_res) {
    die("<div style='padding:20px; background:red; color:white; font-weight:bold;'>Database Error: " . $conn->error . "</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Deliveries - AgriMove Admin</title>
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
            <a href="manage_users.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg font-medium transition">
                <i class="fa-solid fa-users w-5"></i> Manage Users
            </a>
            <a href="all_deliveries.php" class="flex items-center justify-between px-4 py-3 bg-blue-600 text-white rounded-lg font-medium shadow-lg shadow-blue-500/20">
                <div class="flex items-center gap-3"><i class="fa-solid fa-map-location-dot w-5"></i> All Deliveries</div>
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
                <h1 class="text-xl font-bold text-gray-800 hidden sm:block">Logistics Master Log</h1>
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
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4 bg-gradient-to-r from-white to-blue-50/30">
                        <h2 class="font-bold text-gray-900 text-lg flex items-center gap-2">
                            <i class="fa-solid fa-route text-blue-500"></i> Platform Delivery History
                        </h2>
                        <div class="relative w-full sm:w-72">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                            <input type="text" id="searchInput" placeholder="Search town, cargo, or status..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                        </div>
                    </div>

                    <?php if ($deliveries_res && $deliveries_res->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 border-b border-gray-200">
                                        <th class="px-6 py-4 font-bold">Job ID & Cargo</th>
                                        <th class="px-6 py-4 font-bold">Route</th>
                                        <th class="px-6 py-4 font-bold">Participants</th>
                                        <th class="px-6 py-4 font-bold">Status</th>
                                        <th class="px-6 py-4 font-bold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php while($job = $deliveries_res->fetch_assoc()): ?>
                                        <?php 
                                            $status = strtolower($job['status']);
                                            $status_color = 'gray';
                                            if($status == 'pending') $status_color = 'blue';
                                            if($status == 'accepted') $status_color = 'orange';
                                            if($status == 'in_transit') $status_color = 'purple';
                                            if($status == 'delivered') $status_color = 'green';
                                            
                                            // BULLETPROOF FIX: Check if created_at actually exists before trying to format it!
                                            $js_date = (isset($job['created_at']) && !empty($job['created_at'])) 
                                                       ? date('M d, Y h:i A', strtotime($job['created_at'])) 
                                                       : 'Date not recorded';

                                            // Escape data for the JavaScript function
                                            $js_id = str_pad($job['id'], 4, '0', STR_PAD_LEFT);
                                            $js_cargo = addslashes($job['produce_name'] ?? 'Produce');
                                            $js_weight = floatval($job['total_weight'] ?? 0);
                                            $js_pickup_town = addslashes($job['pickup_town']);
                                            $js_pickup_addr = addslashes($job['pickup_exact_address'] ?? 'Not specified');
                                            $js_drop_town = addslashes($job['delivery_town']);
                                            $js_drop_addr = addslashes($job['delivery_exact_address'] ?? 'Not specified');
                                            $js_dist = floatval($job['distance'] ?? 0);
                                            $js_status = addslashes(str_replace('_', ' ', $status));
                                            $js_status_color = $status_color;
                                            $js_farmer = addslashes($job['farmer_name'] ?? 'Unknown');
                                            $js_farmer_phone = addslashes($job['farmer_phone'] ?? 'N/A');
                                            $js_driver = addslashes($job['transporter_name'] ?? 'Unassigned');
                                            $js_driver_phone = addslashes($job['transporter_phone'] ?? 'N/A');
                                        ?>
                                        <tr class="hover:bg-slate-50/50 transition job-row">
                                            
                                            <td class="px-6 py-4">
                                                <p class="font-bold text-gray-900 text-sm job-cargo">
                                                    <?php echo htmlspecialchars($job['produce_name'] ?? 'Produce'); ?> 
                                                    <span class="text-gray-500 text-xs font-normal ml-1">(<?php echo floatval($job['total_weight'] ?? 0); ?> kg)</span>
                                                </p>
                                                <p class="text-xs text-blue-600 font-bold mt-1">TR-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?></p>
                                            </td>
                                            
                                            <td class="px-6 py-4 job-route">
                                                <div class="flex items-center gap-2 text-sm text-gray-700">
                                                    <div class="flex flex-col items-center">
                                                        <i class="fa-solid fa-circle-dot text-blue-500 text-[8px]"></i>
                                                        <div class="w-px h-3 bg-gray-300 my-0.5"></div>
                                                        <i class="fa-solid fa-location-dot text-red-500 text-[8px]"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium"><?php echo htmlspecialchars($job['pickup_town']); ?></p>
                                                        <p class="font-medium"><?php echo htmlspecialchars($job['delivery_town']); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td class="px-6 py-4 job-participants">
                                                <p class="text-xs text-gray-700 mb-1">
                                                    <span class="font-bold text-gray-400 uppercase tracking-wide mr-1 border-b border-gray-200 pb-0.5">Farmer:</span> 
                                                    <?php echo htmlspecialchars($job['farmer_name'] ?? 'Unknown'); ?>
                                                </p>
                                                <p class="text-xs text-gray-700">
                                                    <span class="font-bold text-gray-400 uppercase tracking-wide mr-1 border-b border-gray-200 pb-0.5">Driver:</span> 
                                                    <?php echo $job['transporter_name'] ? htmlspecialchars($job['transporter_name']) : '<span class="italic text-orange-500">Awaiting assignment</span>'; ?>
                                                </p>
                                            </td>

                                            <td class="px-6 py-4 job-status">
                                                <span class="text-[10px] font-bold uppercase text-<?php echo $status_color; ?>-700 bg-<?php echo $status_color; ?>-100 border border-<?php echo $status_color; ?>-200 px-2.5 py-1 rounded tracking-wide">
                                                    <?php echo str_replace('_', ' ', $status); ?>
                                                </span>
                                            </td>

                                            <td class="px-6 py-4 text-right">
                                                <button onclick="openDeliveryModal('<?php echo $js_id; ?>', '<?php echo $js_cargo; ?>', '<?php echo $js_weight; ?>', '<?php echo $js_pickup_town; ?>', '<?php echo $js_pickup_addr; ?>', '<?php echo $js_drop_town; ?>', '<?php echo $js_drop_addr; ?>', '<?php echo $js_dist; ?>', '<?php echo $js_status; ?>', '<?php echo $js_status_color; ?>', '<?php echo $js_farmer; ?>', '<?php echo $js_farmer_phone; ?>', '<?php echo $js_driver; ?>', '<?php echo $js_driver_phone; ?>', '<?php echo addslashes($js_date); ?>')" 
                                                        class="text-gray-400 hover:text-blue-600 transition p-2 bg-gray-50 hover:bg-blue-50 rounded-lg border border-transparent hover:border-blue-200" title="View Full Details">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-16 text-center text-gray-500">
                            <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                                <i class="fa-solid fa-truck-ramp-box"></i>
                            </div>
                            <h3 class="font-bold text-gray-900 mb-1 text-lg">No Deliveries Yet</h3>
                            <p class="text-sm">When farmers start posting transport requests, they will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <div id="deliveryModal" class="fixed inset-0 bg-slate-900/60 z-[100] hidden items-center justify-center p-4 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden transform scale-95 transition-transform" id="deliveryModalContent">
            
            <div class="px-6 py-4 border-b border-gray-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="font-black text-gray-900 text-lg tracking-tight">Job <span id="mod_id" class="text-blue-600">TR-0000</span></h3>
                    <p id="mod_date" class="text-xs text-gray-500 mt-0.5">Posted: Date</p>
                </div>
                <div class="flex items-center gap-4">
                    <span id="mod_status" class="text-xs font-bold uppercase px-3 py-1.5 rounded border tracking-wide">STATUS</span>
                    <button onclick="closeDeliveryModal()" class="text-gray-400 hover:text-gray-700 transition bg-white w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center shadow-sm">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div class="mb-6 pb-6 border-b border-gray-100">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Cargo Information</p>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl border border-green-100">
                            <i class="fa-solid fa-wheat-awn"></i>
                        </div>
                        <div>
                            <p id="mod_cargo" class="font-bold text-gray-900 text-lg">Produce Name</p>
                            <p class="text-sm text-gray-600">Total Weight: <span id="mod_weight" class="font-bold text-gray-900">0</span> kg</p>
                        </div>
                    </div>
                </div>

                <div class="mb-6 pb-6 border-b border-gray-100">
                    <div class="flex justify-between items-end mb-4">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Route Details</p>
                        <p class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded">Distance: <span id="mod_dist">0</span> km</p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                            <p class="text-[10px] uppercase font-bold text-gray-500 mb-1"><i class="fa-solid fa-circle-dot text-blue-500 mr-1"></i> Pick Up</p>
                            <p id="mod_pickup_town" class="font-bold text-gray-900 text-lg mb-1">Town</p>
                            <p id="mod_pickup_addr" class="text-xs text-gray-500 leading-relaxed">Exact Address</p>
                        </div>
                        
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1 h-full bg-red-500"></div>
                            <p class="text-[10px] uppercase font-bold text-gray-500 mb-1"><i class="fa-solid fa-location-dot text-red-500 mr-1"></i> Drop Off</p>
                            <p id="mod_drop_town" class="font-bold text-gray-900 text-lg mb-1">Town</p>
                            <p id="mod_drop_addr" class="text-xs text-gray-500 leading-relaxed">Exact Address</p>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Participants</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        
                        <div class="flex items-center justify-between bg-white border border-gray-200 p-3 rounded-xl shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center font-bold">
                                    <i class="fa-solid fa-tractor text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Farmer</p>
                                    <p id="mod_farmer" class="font-bold text-gray-900 text-sm">Name</p>
                                </div>
                            </div>
                            <a id="mod_farmer_tel" href="#" class="w-8 h-8 rounded-full bg-gray-50 text-gray-600 hover:bg-green-50 hover:text-green-600 border border-gray-200 flex items-center justify-center transition">
                                <i class="fa-solid fa-phone text-xs"></i>
                            </a>
                        </div>

                        <div class="flex items-center justify-between bg-white border border-gray-200 p-3 rounded-xl shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center font-bold">
                                    <i class="fa-solid fa-truck text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Driver</p>
                                    <p id="mod_driver" class="font-bold text-gray-900 text-sm">Name</p>
                                </div>
                            </div>
                            <a id="mod_driver_tel" href="#" class="w-8 h-8 rounded-full bg-gray-50 text-gray-600 hover:bg-purple-50 hover:text-purple-600 border border-gray-200 flex items-center justify-center transition">
                                <i class="fa-solid fa-phone text-xs"></i>
                            </a>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // --- LIVE SEARCH ---
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.job-row');

            rows.forEach(row => {
                let cargo = row.querySelector('.job-cargo').innerText.toLowerCase();
                let route = row.querySelector('.job-route').innerText.toLowerCase();
                let participants = row.querySelector('.job-participants').innerText.toLowerCase();
                let status = row.querySelector('.job-status').innerText.toLowerCase();
                
                if(cargo.includes(filter) || route.includes(filter) || participants.includes(filter) || status.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // --- MODAL CONTROLS ---
        const modal = document.getElementById('deliveryModal');
        const modalContent = document.getElementById('deliveryModalContent');

        function openDeliveryModal(id, cargo, weight, pTown, pAddr, dTown, dAddr, dist, status, sColor, farmer, fPhone, driver, dPhone, date) {
            
            // Populate basic info
            document.getElementById('mod_id').innerText = 'TR-' + id;
            document.getElementById('mod_date').innerText = 'Posted: ' + date;
            document.getElementById('mod_cargo').innerText = cargo;
            document.getElementById('mod_weight').innerText = weight;
            document.getElementById('mod_dist').innerText = dist;
            
            // Populate addresses
            document.getElementById('mod_pickup_town').innerText = pTown;
            document.getElementById('mod_pickup_addr').innerText = pAddr || 'No specific address provided';
            document.getElementById('mod_drop_town').innerText = dTown;
            document.getElementById('mod_drop_addr').innerText = dAddr || 'No specific address provided';
            
            // Populate contacts
            document.getElementById('mod_farmer').innerText = farmer;
            document.getElementById('mod_farmer_tel').href = 'tel:' + fPhone;
            
            document.getElementById('mod_driver').innerText = driver;
            if (driver === 'Unassigned') {
                document.getElementById('mod_driver_tel').style.display = 'none';
            } else {
                document.getElementById('mod_driver_tel').style.display = 'flex';
                document.getElementById('mod_driver_tel').href = 'tel:' + dPhone;
            }

            // Style and set the status badge
            let badge = document.getElementById('mod_status');
            badge.innerText = status;
            badge.className = `text-xs font-bold uppercase px-3 py-1.5 rounded tracking-wide text-${sColor}-700 bg-${sColor}-100 border border-${sColor}-200`;
            
            // Show modal with animation
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 10);
        }

        function closeDeliveryModal() {
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 200);
        }

        // --- MOBILE SIDEBAR ---
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