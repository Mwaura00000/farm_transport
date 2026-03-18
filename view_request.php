<?php
session_start();
include "db_connect.php";

// Ensure only logged-in farmers can access this page
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer'){
    header("Location: login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Get the request ID from the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my_requests.php");
    exit();
}

$request_id = intval($_GET['id']);

// --- NEW: HANDLE CANCEL ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    mysqli_begin_transaction($conn);
    try {
        // 1. Update status to cancelled
        $cancel_sql = "UPDATE transport_requests SET status = 'cancelled' WHERE id = ? AND farmer_id = ? AND status = 'pending'";
        $stmt_cancel = mysqli_prepare($conn, $cancel_sql);
        mysqli_stmt_bind_param($stmt_cancel, "ii", $request_id, $farmer_id);
        mysqli_stmt_execute($stmt_cancel);
        
        // 2. Add to status_logs for the activity feed
        $log_sql = "INSERT INTO status_logs (transport_request_id, old_status, new_status, changed_by) VALUES (?, 'pending', 'cancelled', ?)";
        $stmt_log = mysqli_prepare($conn, $log_sql);
        mysqli_stmt_bind_param($stmt_log, "ii", $request_id, $farmer_id);
        mysqli_stmt_execute($stmt_log);
        
        mysqli_commit($conn);
        $success_msg = "Transport request has been cancelled successfully.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Could not cancel request: " . $e->getMessage();
    }
}

// Fetch the full details of this specific request
$sql = "SELECT tr.*, 
               p.name AS produce_name, p.quantity, p.weight AS total_amount, p.description AS produce_desc, p.created_at,
               u.name AS transporter_name, u.phone AS transporter_phone
        FROM transport_requests tr 
        LEFT JOIN produce p ON tr.produce_id = p.id
        LEFT JOIN users u ON tr.transporter_id = u.id
        WHERE tr.id = ? AND tr.farmer_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $request_id, $farmer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result->num_rows === 0) {
    $error_found = true;
} else {
    $error_found = false;
    $request = $result->fetch_assoc();
    
    $unit_type = 'units';
    $loading_labor = 'N/A';
    if (!empty($request['produce_desc'])) {
        if (preg_match('/Unit:\s*([a-zA-Z]+)/', $request['produce_desc'], $matches)) {
            $unit_type = $matches[1];
        }
        if (preg_match('/Labor:\s*([a-zA-Z]+)/', $request['produce_desc'], $matches)) {
            $loading_labor = ucfirst($matches[1]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        .detail-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; color: #6b7280; margin-bottom: 0.25rem; }
        .detail-value { font-size: 0.95rem; font-weight: 500; color: #111827; }
    </style>
</head>
<body class="text-gray-800">

    <nav class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center sticky top-0 z-10 shadow-sm">
        <div class="flex items-center gap-2 text-green-600 text-xl font-bold">
            <i class="fa-solid fa-truck-fast"></i>
            AgriMove
        </div>
        <a href="my_requests.php" class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition text-gray-700 decoration-none">
            <i class="fa-solid fa-arrow-left"></i> Back to Requests
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

        <?php if ($error_found): ?>
            <div class="bg-white rounded-xl shadow-sm border border-red-100 p-12 text-center">
                <div class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Request Not Found</h2>
                <p class="text-gray-500 mb-6">The transport request you are looking for does not exist or you do not have permission to view it.</p>
                <a href="my_requests.php" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-6 rounded-lg transition inline-block">Return to My Requests</a>
            </div>
        <?php else: ?>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        Request #TR-<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?>
                        
                        <?php 
                        $status = strtolower($request['status']);
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
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Requested on <?php echo date("F j, Y, g:i a", strtotime($request['created_at'])); ?>
                    </p>
                </div>
                
                <?php if($status == 'pending'): ?>
                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <a href="edit_request.php?id=<?php echo $request['id']; ?>" class="flex-1 md:flex-none text-center bg-white text-gray-700 hover:bg-gray-50 border border-gray-300 font-medium py-2 px-4 rounded-lg transition shadow-sm text-sm flex items-center justify-center gap-2">
                            <i class="fa-solid fa-pen"></i> Edit
                        </a>
                        
                        <form method="POST" class="flex-1 md:flex-none m-0" onsubmit="return confirm('Are you sure you want to cancel this transport request? Drivers will no longer be able to accept it.');">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="w-full bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 font-medium py-2 px-4 rounded-lg transition shadow-sm text-sm flex items-center justify-center gap-2">
                                <i class="fa-solid fa-xmark"></i> Cancel Request
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-green-500"></div>
                        <h2 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-50 pb-2 flex items-center gap-2">
                            <i class="fa-solid fa-seedling text-green-600"></i> Produce Details
                        </h2>
                        
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-y-6 gap-x-4">
                            <div>
                                <p class="detail-label">Produce Type</p>
                                <p class="detail-value"><?php echo htmlspecialchars($request['produce_name']); ?></p>
                            </div>
                            <div>
                                <p class="detail-label">Cargo Type</p>
                                <p class="detail-value capitalize"><?php echo htmlspecialchars($request['cargo_type']); ?></p>
                            </div>
                            <div>
                                <p class="detail-label">Quantity</p>
                                <p class="detail-value">
                                    <?php echo floatval($request['quantity']); ?> 
                                    <?php echo ($request['cargo_type'] == 'bulk') ? 'Bulk Load' : ''; ?>
                                </p>
                            </div>
                            <div>
                                <p class="detail-label">Total Amount</p>
                                <p class="detail-value font-bold text-green-700">
                                    <?php echo floatval($request['total_amount']) . " " . htmlspecialchars($unit_type); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-6 border-b border-gray-50 pb-2 flex items-center gap-2">
                            <i class="fa-solid fa-route text-blue-500"></i> Route Information
                        </h2>

                        <div class="relative pl-8 space-y-8 border-l-2 border-dashed border-gray-200 ml-3">
                            <div class="relative">
                                <div class="absolute -left-[43px] top-1 w-6 h-6 rounded-full bg-blue-100 border-2 border-white flex items-center justify-center text-blue-600 text-[10px] shadow-sm">
                                    <i class="fa-solid fa-circle-dot"></i>
                                </div>
                                <h3 class="font-bold text-gray-900 text-base mb-1">Pickup Location</h3>
                                <p class="text-gray-600 text-sm mb-3">
                                    <?php echo htmlspecialchars($request['pickup_town']); ?>, <?php echo htmlspecialchars($request['pickup_county']); ?> County
                                </p>
                                
                                <div class="bg-gray-50 rounded-lg p-3 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm border border-gray-100">
                                    <div>
                                        <span class="text-gray-500 block mb-1 text-xs uppercase">Village / Exact Spot</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($request['pickup_exact_address'] ?: 'Not provided'); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 block mb-1 text-xs uppercase">Road Condition</span>
                                        <span class="font-medium capitalize"><?php echo htmlspecialchars($request['pickup_description'] ?: 'Not specified'); ?></span>
                                    </div>
                                    <?php if(!empty($request['pickup_location'])): ?>
                                    <div class="sm:col-span-2 mt-1">
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($request['pickup_location']); ?>" target="_blank" class="text-blue-600 hover:underline text-xs flex items-center gap-1">
                                            <i class="fa-solid fa-map-location-dot"></i> View Pickup Pin on Maps
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="relative">
                                <div class="absolute -left-[43px] top-1 w-6 h-6 rounded-full bg-red-100 border-2 border-white flex items-center justify-center text-red-500 text-[10px] shadow-sm">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <h3 class="font-bold text-gray-900 text-base mb-1">Delivery Destination</h3>
                                <p class="text-gray-600 text-sm mb-3">
                                    <?php echo htmlspecialchars($request['delivery_town']); ?>, <?php echo htmlspecialchars($request['delivery_county']); ?> County
                                </p>
                                
                                <div class="bg-gray-50 rounded-lg p-3 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm border border-gray-100">
                                    <div class="sm:col-span-2">
                                        <span class="text-gray-500 block mb-1 text-xs uppercase">Delivery Address / Market</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($request['delivery_exact_address']); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 block mb-1 text-xs uppercase">Receiver Name</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($request['emergency_contact_name'] ?: 'N/A'); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 block mb-1 text-xs uppercase">Receiver Phone</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($request['emergency_contact_phone'] ?: 'N/A'); ?></span>
                                    </div>
                                    <?php if(!empty($request['destination_location'])): ?>
                                    <div class="sm:col-span-2 mt-1">
                                        <a href="<?php echo (strpos($request['destination_location'], 'http') === 0) ? $request['destination_location'] : 'https://www.google.com/maps/search/?api=1&query=' . urlencode($request['destination_location']); ?>" target="_blank" class="text-red-600 hover:underline text-xs flex items-center gap-1">
                                            <i class="fa-solid fa-map-location-dot"></i> View Delivery Pin on Maps
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-id-badge text-gray-400"></i> Assigned Transporter
                        </h2>
                        
                        <?php if(!empty($request['transporter_id'])): ?>
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-lg border border-blue-200">
                                    <?php echo strtoupper(substr($request['transporter_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($request['transporter_name']); ?></p>
                                    <p class="text-sm text-gray-500">Verified Driver <i class="fa-solid fa-circle-check text-blue-500 ml-1"></i></p>
                                </div>
                            </div>
                            <a href="tel:<?php echo htmlspecialchars($request['transporter_phone']); ?>" class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-2 px-4 rounded-lg transition border border-blue-200 flex justify-center items-center gap-2">
                                <i class="fa-solid fa-phone"></i> Call Driver
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                                <div class="text-gray-300 text-3xl mb-2"><i class="fa-solid fa-clock-rotate-left"></i></div>
                                <p class="text-sm text-gray-500 font-medium">Waiting for a driver</p>
                                <p class="text-xs text-gray-400 mt-1">Transporters in the area have been notified.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-clipboard-list text-gray-400"></i> Logistics Info
                        </h2>
                        
                        <ul class="space-y-4 text-sm">
                            <li class="flex justify-between items-center border-b border-gray-50 pb-2">
                                <span class="text-gray-500">Vehicle Requested</span>
                                <span class="font-medium text-gray-900 capitalize"><?php echo htmlspecialchars(explode(' |', $request['produce_desc'] ?? 'Any')[0]); ?></span>
                            </li>
                            <li class="flex justify-between items-center border-b border-gray-50 pb-2">
                                <span class="text-gray-500">Est. Distance</span>
                                <span class="font-medium text-gray-900"><?php echo floatval($request['distance']); ?> KM</span>
                            </li>
                            <li class="flex justify-between items-center border-b border-gray-50 pb-2">
                                <span class="text-gray-500">Loading Labor</span>
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($loading_labor); ?></span>
                            </li>
                            <li class="flex flex-col gap-1 pt-1">
                                <span class="text-gray-500">Preferred Pickup Time</span>
                                <span class="font-medium text-gray-900">
                                    <?php 
                                    if(!empty($request['request_date'])) {
                                        echo date("D, M j, Y @ g:i A", strtotime($request['request_date']));
                                    } else {
                                        echo "As soon as possible";
                                    }
                                    ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </main>

</body>
</html>