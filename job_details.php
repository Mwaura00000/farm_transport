<?php
session_start();
include "db_connect.php";

// Ensure only logged-in transporters can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'transporter') {
    header("Location: login.php");
    exit();
}

$transporter_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: transporter_dashboard.php");
    exit();
}
$request_id = intval($_GET['id']);

// --- HANDLE ACTIONS (Accept, Transit, Deliver) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    mysqli_begin_transaction($conn);
    try {
        if ($action === 'accept') {
            // Verify it's still pending so two drivers don't accept at the exact same time
            $check = $conn->query("SELECT status FROM transport_requests WHERE id=$request_id FOR UPDATE");
            if ($check->fetch_assoc()['status'] !== 'pending') {
                throw new Exception("Sorry, another driver just accepted this job.");
            }
            
            $sql = "UPDATE transport_requests SET status = 'accepted', transporter_id = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $transporter_id, $request_id);
            mysqli_stmt_execute($stmt);
            $success_msg = "Job accepted successfully! Please contact the farmer to confirm pickup.";
            
        } elseif ($action === 'in_transit') {
            $sql = "UPDATE transport_requests SET status = 'in_transit' WHERE id = ? AND transporter_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $request_id, $transporter_id);
            mysqli_stmt_execute($stmt);
            $success_msg = "Status updated to In Transit. Drive safely!";
            
        } elseif ($action === 'delivered') {
            $sql = "UPDATE transport_requests SET status = 'delivered' WHERE id = ? AND transporter_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $request_id, $transporter_id);
            mysqli_stmt_execute($stmt);
            $success_msg = "Job marked as Delivered! Great work.";
        }
        
        // Log the status change
        $new_status = $action === 'accept' ? 'accepted' : $action;
        $log_sql = "INSERT INTO status_logs (transport_request_id, new_status, changed_by) VALUES (?, ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_sql);
        if($log_stmt){
            mysqli_stmt_bind_param($log_stmt, "isi", $request_id, $new_status, $transporter_id);
            mysqli_stmt_execute($log_stmt);
        }
        
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
    }
}

// --- FETCH JOB DETAILS ---
$sql = "SELECT tr.*, 
               p.name AS produce_name, p.quantity, p.weight AS total_amount, p.description AS produce_desc,
               u.name AS farmer_name, u.phone AS farmer_phone
        FROM transport_requests tr 
        LEFT JOIN produce p ON tr.produce_id = p.id
        LEFT JOIN users u ON tr.farmer_id = u.id
        WHERE tr.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result->num_rows === 0) {
    die("Job not found.");
}

$job = $result->fetch_assoc();

// Check if someone else took it
$taken_by_other = (!empty($job['transporter_id']) && $job['transporter_id'] != $transporter_id);

// Parse produce description
$unit_type = 'kg';
$loading_labor = 'Not Specified';
$vehicle_req = 'Any';
if (!empty($job['produce_desc'])) {
    if (preg_match('/Unit:\s*([a-zA-Z]+)/', $job['produce_desc'], $matches)) $unit_type = $matches[1];
    if (preg_match('/Labor:\s*([a-zA-Z]+)/', $job['produce_desc'], $matches)) $loading_labor = ucfirst($matches[1]);
    if (preg_match('/Vehicle Req:\s*([a-zA-Z0-9_-]+)/', $job['produce_desc'], $matches)) $vehicle_req = ucfirst($matches[1]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; background-color: #f9fafb; } </style>
</head>
<body class="text-gray-800 pb-12">

    <nav class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex justify-between items-center sticky top-0 z-10 shadow-md">
        <div class="flex items-center gap-2 text-blue-500 text-xl font-bold">
            <i class="fa-solid fa-truck-fast"></i> AgriMove
        </div>
        <a href="transporter_dashboard.php" class="flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-sm font-medium text-gray-300 transition">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </nav>

    <main class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
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

        <?php if ($taken_by_other): ?>
            <div class="bg-white rounded-xl shadow-sm border border-red-100 p-12 text-center">
                <div class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Job No Longer Available</h2>
                <p class="text-gray-500 mb-6">Sorry! Another transporter has already accepted this request.</p>
                <a href="transporter_dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg transition inline-block">Find Another Job</a>
            </div>
        <?php else: ?>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        Job #TR-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?>
                        
                        <?php 
                        $status = strtolower($job['status']);
                        $bgClass = "bg-gray-100 text-gray-800";
                        if($status == 'pending') $bgClass = "bg-orange-100 text-orange-800";
                        if($status == 'accepted') $bgClass = "bg-blue-100 text-blue-800 animate-pulse";
                        if($status == 'in_transit') $bgClass = "bg-purple-100 text-purple-800";
                        if($status == 'delivered') $bgClass = "bg-green-100 text-green-800";
                        ?>
                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?php echo $bgClass; ?>">
                            <?php echo str_replace('_', ' ', $status); ?>
                        </span>
                    </h1>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 space-y-6">
                    
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-6 border-b border-gray-50 pb-2 flex items-center gap-2">
                            <i class="fa-solid fa-route text-blue-500"></i> Route Details
                        </h2>

                        <div class="relative pl-8 space-y-8 border-l-2 border-dashed border-gray-200 ml-3">
                            <div class="relative">
                                <div class="absolute -left-[43px] top-1 w-6 h-6 rounded-full bg-blue-100 border-2 border-white flex items-center justify-center text-blue-600 text-[10px] shadow-sm">
                                    <i class="fa-solid fa-circle-dot"></i>
                                </div>
                                <h3 class="font-bold text-gray-900 text-base mb-1">Pickup: <?php echo htmlspecialchars($job['pickup_town']); ?></h3>
                                <p class="text-gray-600 text-sm mb-3">County: <?php echo htmlspecialchars($job['pickup_county']); ?></p>
                                
                                <div class="bg-gray-50 rounded-lg p-3 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm border border-gray-100">
                                    <div>
                                        <span class="text-gray-500 block mb-1 text-xs uppercase">Village / Landmark</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($job['pickup_exact_address'] ?: 'Contact farmer upon arrival'); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 block mb-1 text-xs uppercase">Road Condition</span>
                                        <span class="font-medium capitalize"><?php echo htmlspecialchars($job['pickup_description'] ?: 'Not specified'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="relative">
                                <div class="absolute -left-[43px] top-1 w-6 h-6 rounded-full bg-red-100 border-2 border-white flex items-center justify-center text-red-500 text-[10px] shadow-sm">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <h3 class="font-bold text-gray-900 text-base mb-1">Delivery: <?php echo htmlspecialchars($job['delivery_town']); ?></h3>
                                <p class="text-gray-600 text-sm mb-3">County: <?php echo htmlspecialchars($job['delivery_county']); ?></p>
                                
                                <div class="bg-gray-50 rounded-lg p-3 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm border border-gray-100">
                                    <div class="sm:col-span-2">
                                        <span class="text-gray-500 block mb-1 text-xs uppercase">Exact Drop-off Point</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($job['delivery_exact_address']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-green-500"></div>
                        <h2 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-50 pb-2 flex items-center gap-2">
                            <i class="fa-solid fa-box-open text-green-600"></i> Cargo Logistics
                        </h2>
                        
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-y-6 gap-x-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Produce</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($job['produce_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Cargo Type</p>
                                <p class="text-sm font-medium text-gray-900 capitalize"><?php echo htmlspecialchars($job['cargo_type']); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Vehicle Req.</p>
                                <p class="text-sm font-medium text-gray-900 capitalize"><?php echo htmlspecialchars($vehicle_req); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Est. Distance</p>
                                <p class="text-sm font-bold text-blue-700"><?php echo floatval($job['distance']); ?> KM</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Total Weight/Amount</p>
                                <p class="text-sm font-bold text-gray-900"><?php echo floatval($job['total_amount']) . " " . htmlspecialchars($unit_type); ?></p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Loading Labor Provided By</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($loading_labor); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    
                    <div class="bg-white rounded-xl shadow-sm border-2 <?php echo ($status == 'pending') ? 'border-orange-200 bg-orange-50/30' : 'border-blue-200 bg-blue-50/30'; ?> p-6 sticky top-24">
                        
                        <h2 class="text-base font-bold text-gray-900 mb-4 text-center">Driver Controls</h2>
                        
                        <?php if ($status === 'pending'): ?>
                            <p class="text-sm text-gray-600 text-center mb-6">This job is open. Accept it now to secure the load and reveal the farmer's contact details.</p>
                            
                            <form method="POST" onsubmit="return confirm('Are you sure you want to commit to transporting this load?');">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-lg shadow-blue-500/30 flex items-center justify-center gap-2 text-lg">
                                    <i class="fa-solid fa-check"></i> Accept This Job
                                </button>
                            </form>
                            
                        <?php elseif ($status === 'accepted'): ?>
                            <p class="text-sm text-gray-600 text-center mb-6">You have accepted this job. Head to the pickup location.</p>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="in_transit">
                                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-lg shadow-purple-500/30 flex items-center justify-center gap-2 text-lg">
                                    <i class="fa-solid fa-truck-fast"></i> Start Transit
                                </button>
                            </form>
                            
                        <?php elseif ($status === 'in_transit'): ?>
                            <p class="text-sm text-gray-600 text-center mb-6">Cargo is en route. Mark as delivered once you reach the destination.</p>
                            
                            <form method="POST" onsubmit="return confirm('Confirm that the cargo has been successfully dropped off?');">
                                <input type="hidden" name="action" value="delivered">
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-lg shadow-green-500/30 flex items-center justify-center gap-2 text-lg">
                                    <i class="fa-solid fa-box-check"></i> Complete Delivery
                                </button>
                            </form>
                            
                        <?php elseif ($status === 'delivered'): ?>
                            <div class="text-center py-4">
                                <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-3">
                                    <i class="fa-solid fa-check-double"></i>
                                </div>
                                <h3 class="font-bold text-gray-900">Delivery Complete</h3>
                                <p class="text-sm text-gray-500 mt-1">This transport request is finished.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-address-book text-gray-400"></i> Farmer Details
                        </h2>
                        
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-bold text-lg border border-green-200">
                                <?php echo strtoupper(substr($job['farmer_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900"><?php echo htmlspecialchars($job['farmer_name']); ?></p>
                                <p class="text-sm text-gray-500">Registered Farmer</p>
                            </div>
                        </div>

                        <?php if ($status !== 'pending'): ?>
                            <a href="tel:<?php echo htmlspecialchars($job['farmer_phone']); ?>" class="w-full bg-green-50 hover:bg-green-100 text-green-700 font-medium py-2 px-4 rounded-lg transition border border-green-200 flex justify-center items-center gap-2 mb-3">
                                <i class="fa-solid fa-phone"></i> Call Farmer
                            </a>
                            <a href="messages.php?uid=<?php echo $job['farmer_id']; ?>" class="w-full bg-white hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-lg transition border border-gray-200 flex justify-center items-center gap-2">
                                <i class="fa-regular fa-message"></i> Send Message
                            </a>
                        <?php else: ?>
                            <div class="text-center p-3 bg-gray-50 rounded border border-dashed border-gray-200 mt-2">
                                <i class="fa-solid fa-lock text-gray-400 mb-1"></i>
                                <p class="text-xs text-gray-500 font-medium">Contact details are locked.</p>
                                <p class="text-[10px] text-gray-400">Accept the job to view phone number and message.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        <?php endif; ?>
    </main>

</body>
</html>