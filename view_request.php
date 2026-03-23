<?php
session_start();
include "db_connect.php";

// Ensure only logged-in farmers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: farmer_dashboard.php");
    exit();
}
$request_id = intval($_GET['id']);

// --- HANDLE ACCEPTING A BID ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_bid') {
    $bid_id = intval($_POST['bid_id']);
    $transporter_id = intval($_POST['transporter_id']);

    mysqli_begin_transaction($conn);
    try {
        // 1. Double check the job is still pending
        $check_sql = "SELECT status FROM transport_requests WHERE id = ? AND farmer_id = ? FOR UPDATE";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $request_id, $farmer_id);
        mysqli_stmt_execute($check_stmt);
        $job_status = mysqli_stmt_get_result($check_stmt)->fetch_assoc()['status'] ?? '';

        if ($job_status !== 'pending') {
            throw new Exception("This job has already been awarded or closed.");
        }

        // 2. Update the transport_requests table (Lock it to this driver)
        $update_job = "UPDATE transport_requests SET status = 'accepted', transporter_id = ? WHERE id = ?";
        $stmt_job = mysqli_prepare($conn, $update_job);
        mysqli_stmt_bind_param($stmt_job, "ii", $transporter_id, $request_id);
        mysqli_stmt_execute($stmt_job);

        // 3. Update the winning bid to 'accepted'
        $update_win = "UPDATE job_bids SET status = 'accepted' WHERE id = ?";
        $stmt_win = mysqli_prepare($conn, $update_win);
        mysqli_stmt_bind_param($stmt_win, "i", $bid_id);
        mysqli_stmt_execute($stmt_win);

        // 4. Reject all other bids for this job
        $update_lose = "UPDATE job_bids SET status = 'rejected' WHERE job_id = ? AND id != ?";
        $stmt_lose = mysqli_prepare($conn, $update_lose);
        mysqli_stmt_bind_param($stmt_lose, "ii", $request_id, $bid_id);
        mysqli_stmt_execute($stmt_lose);

        mysqli_commit($conn);
        $success_msg = "Bid accepted! The transporter has been notified. You can now contact them.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
    }
}

// --- FETCH JOB DETAILS ---
$sql = "SELECT tr.*, p.name AS produce_name, p.quantity, p.weight AS total_amount, p.description AS produce_desc
        FROM transport_requests tr 
        LEFT JOIN produce p ON tr.produce_id = p.id
        WHERE tr.id = ? AND tr.farmer_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $request_id, $farmer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result->num_rows === 0) {
    die("Job not found or access denied.");
}
$job = $result->fetch_assoc();
$is_pending = ($job['status'] === 'pending');

// --- FETCH INCOMING BIDS (FIXED SQL QUERY) ---
// We join with the users table for the name, and transporter_profiles for the vehicle details
$bids_sql = "SELECT jb.*, u.name AS driver_name, u.phone AS driver_phone, 
                    tp.vehicle_type, tp.plate_no, tp.is_verified 
             FROM job_bids jb
             JOIN users u ON jb.transporter_id = u.id
             JOIN transporter_profiles tp ON jb.transporter_id = tp.user_id
             WHERE jb.job_id = ? 
             ORDER BY jb.bid_amount ASC"; // Order by lowest price first

$bids_stmt = mysqli_prepare($conn, $bids_sql);
mysqli_stmt_bind_param($bids_stmt, "i", $request_id);
mysqli_stmt_execute($bids_stmt);
$bids_result = mysqli_stmt_get_result($bids_stmt);

// Parse produce description for unit type
$unit_type = 'kg';
if (!empty($job['produce_desc']) && preg_match('/Unit:\s*([a-zA-Z]+)/', $job['produce_desc'], $matches)) {
    $unit_type = $matches[1];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Offers - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; background-color: #f9fafb; } </style>
</head>
<body class="text-gray-800 pb-12">

    <nav class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center sticky top-0 z-10 shadow-sm">
        <div class="flex items-center gap-2 text-green-600 text-xl font-bold">
            <i class="fa-solid fa-truck-fast"></i> AgriMove
        </div>
        <a href="farmer_dashboard.php" class="flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium text-gray-700 transition">
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

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8 flex flex-col md:flex-row justify-between md:items-center gap-4 border-l-4 <?php echo $is_pending ? 'border-l-orange-500' : 'border-l-blue-500'; ?>">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <span class="bg-gray-100 text-gray-600 text-xs uppercase font-bold px-2 py-1 rounded">TR-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?></span>
                    <?php 
                        $status = strtolower($job['status']);
                        $bgClass = "bg-gray-100 text-gray-800";
                        if($status == 'pending') $bgClass = "bg-orange-100 text-orange-800";
                        if($status == 'accepted') $bgClass = "bg-blue-100 text-blue-800";
                        if($status == 'in_transit') $bgClass = "bg-purple-100 text-purple-800";
                        if($status == 'delivered') $bgClass = "bg-green-100 text-green-800";
                    ?>
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?php echo $bgClass; ?>">
                        <?php echo str_replace('_', ' ', $status); ?>
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($job['produce_name']); ?> (<?php echo floatval($job['total_amount']) . " " . $unit_type; ?>)</h1>
                <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                    <i class="fa-solid fa-route"></i> <?php echo htmlspecialchars($job['pickup_town']); ?> &rarr; <?php echo htmlspecialchars($job['delivery_town']); ?> (<?php echo floatval($job['distance']); ?> KM)
                </p>
            </div>
            <div class="text-left md:text-right">
                <p class="text-xs text-gray-500 uppercase font-bold">Total Offers Received</p>
                <p class="text-3xl font-black text-gray-900"><?php echo $bids_result->num_rows; ?></p>
            </div>
        </div>

        <h2 class="text-xl font-bold text-gray-900 mb-4">Transporter Offers</h2>

        <?php if ($bids_result->num_rows === 0): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 border-dashed p-12 text-center text-gray-500">
                <div class="w-16 h-16 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center text-3xl mx-auto mb-3">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <h3 class="font-bold text-gray-900 mb-1 text-lg">Waiting for Offers</h3>
                <p class="text-sm max-w-md mx-auto">Your request is live on the Load Board. Transporters will review your cargo details and submit their price bids here shortly.</p>
            </div>
        <?php else: ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php while($bid = $bids_result->fetch_assoc()): ?>
                    
                    <?php 
                    // Determine if this bid is the accepted one, rejected, or pending
                    $is_winner = ($bid['status'] === 'accepted');
                    $is_rejected = ($bid['status'] === 'rejected');
                    
                    $cardClass = "bg-white border-gray-200 hover:border-green-300";
                    if ($is_winner) $cardClass = "bg-blue-50 border-blue-400 ring-2 ring-blue-400 shadow-md";
                    if ($is_rejected) $cardClass = "bg-gray-50 border-gray-200 opacity-60";
                    ?>

                    <div class="rounded-xl shadow-sm border <?php echo $cardClass; ?> p-6 transition relative overflow-hidden flex flex-col justify-between">
                        
                        <?php if($is_winner): ?>
                            <div class="absolute top-0 right-0 bg-blue-500 text-white text-[10px] font-bold uppercase tracking-wider px-3 py-1 rounded-bl-lg">
                                Awarded Driver
                            </div>
                        <?php endif; ?>

                        <div>
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-bold text-lg border border-gray-300">
                                        <?php echo strtoupper(substr($bid['driver_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 flex items-center gap-1">
                                            <?php echo htmlspecialchars($bid['driver_name']); ?>
                                            <?php if($bid['is_verified']): ?>
                                                <i class="fa-solid fa-circle-check text-green-500 text-sm" title="Verified Transporter"></i>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($bid['vehicle_type']); ?> • <?php echo htmlspecialchars($bid['plate_no']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500 uppercase font-bold mb-0.5">Price Offer</p>
                                    <p class="text-xl font-black text-green-600">Ksh <?php echo number_format($bid['bid_amount']); ?></p>
                                </div>
                            </div>

                            <?php if (!empty($bid['notes'])): ?>
                                <div class="bg-gray-50/50 rounded p-3 mb-4 border border-gray-100 text-sm italic text-gray-600">
                                    <i class="fa-solid fa-quote-left text-gray-300 mr-1"></i> <?php echo htmlspecialchars($bid['notes']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4 pt-4 border-t <?php echo $is_winner ? 'border-blue-200' : 'border-gray-100'; ?>">
                            
                            <?php if ($is_pending): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to award the job to <?php echo htmlspecialchars($bid['driver_name']); ?> for Ksh <?php echo number_format($bid['bid_amount']); ?>?');">
                                    <input type="hidden" name="action" value="accept_bid">
                                    <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                    <input type="hidden" name="transporter_id" value="<?php echo $bid['transporter_id']; ?>">
                                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-4 rounded-lg transition shadow-sm flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-handshake"></i> Accept Offer
                                    </button>
                                </form>

                            <?php elseif ($is_winner): ?>
                                <div class="grid grid-cols-2 gap-3">
                                    <a href="tel:<?php echo htmlspecialchars($bid['driver_phone']); ?>" class="bg-white hover:bg-gray-50 border border-blue-200 text-blue-700 font-medium py-2 rounded-lg transition text-sm flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-phone"></i> Call
                                    </a>
                                    <a href="messages.php?uid=<?php echo $bid['transporter_id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition text-sm flex items-center justify-center gap-2 shadow-sm">
                                        <i class="fa-regular fa-message"></i> Message
                                    </a>
                                </div>

                            <?php else: ?>
                                <div class="text-center py-2 text-gray-400 text-sm font-medium">
                                    <i class="fa-solid fa-xmark"></i> Offer Declined
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>