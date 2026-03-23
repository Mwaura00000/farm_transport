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

// --- SECURITY CHECK: HAS THE TRANSPORTER SETUP THEIR PROFILE? ---
$prof_sql = "SELECT plate_no, base_rate_per_km, is_verified FROM transporter_profiles WHERE user_id = ?";
$prof_stmt = mysqli_prepare($conn, $prof_sql);
mysqli_stmt_bind_param($prof_stmt, "i", $transporter_id);
mysqli_stmt_execute($prof_stmt);
$prof_res = mysqli_stmt_get_result($prof_stmt);

if ($prof_res->num_rows == 0) {
    header("Location: transporter_settings.php?msg=setup_required");
    exit();
}
$profile = $prof_res->fetch_assoc();

if (empty($profile['plate_no']) || floatval($profile['base_rate_per_km']) <= 0) {
    header("Location: transporter_settings.php?msg=setup_required");
    exit();
}
$base_rate = floatval($profile['base_rate_per_km']);


// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SUBMITTING A BID
    if (isset($_POST['action']) && $_POST['action'] === 'submit_bid') {
        // SECURITY ENFORCEMENT: Block unverified bidding
        if ($profile['is_verified'] != 1) {
            $error_msg = "Your account is not yet verified. You cannot submit bids until the Admin approves your documents.";
        } else {
            $bid_amount = floatval($_POST['bid_amount']);
            $notes = trim($_POST['notes']);
            
            if ($bid_amount <= 0) {
                $error_msg = "Please enter a valid bid amount.";
            } else {
                $check_bid = $conn->query("SELECT id FROM job_bids WHERE job_id = $request_id AND transporter_id = $transporter_id");
                if ($check_bid->num_rows > 0) {
                    $error_msg = "You have already submitted an offer for this job.";
                } else {
                    $insert_sql = "INSERT INTO job_bids (job_id, transporter_id, bid_amount, notes) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $insert_sql);
                    mysqli_stmt_bind_param($stmt, "iids", $request_id, $transporter_id, $bid_amount, $notes);
                    if (mysqli_stmt_execute($stmt)) {
                        $success_msg = "Your offer of Ksh " . number_format($bid_amount) . " has been sent to the farmer!";
                    } else {
                        $error_msg = "Error submitting bid. Please try again.";
                    }
                }
            }
        }
    }
    
    // 2. UPDATING TRANSIT STATUS TO "IN TRANSIT"
    elseif (isset($_POST['action']) && $_POST['action'] === 'in_transit') {
        $new_status = 'in_transit';
        $sql = "UPDATE transport_requests SET status = ? WHERE id = ? AND transporter_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $new_status, $request_id, $transporter_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Status updated! You are now in transit.";
        }
    }
    
    // 3. COMPLETE DELIVERY (THE OTP HANDSHAKE)
    elseif (isset($_POST['action']) && $_POST['action'] === 'delivered') {
        $entered_otp = trim($_POST['otp_code']);
        
        $otp_sql = "SELECT otp_code FROM transport_requests WHERE id = ? AND transporter_id = ?";
        $otp_stmt = mysqli_prepare($conn, $otp_sql);
        mysqli_stmt_bind_param($otp_stmt, "ii", $request_id, $transporter_id);
        mysqli_stmt_execute($otp_stmt);
        $otp_res = mysqli_stmt_get_result($otp_stmt);
        
        if ($otp_res && $otp_res->num_rows > 0) {
            $job_data = $otp_res->fetch_assoc();
            if ($entered_otp === $job_data['otp_code']) {
                $update_sql = "UPDATE transport_requests SET status = 'delivered' WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "i", $request_id);
                if (mysqli_stmt_execute($update_stmt)) {
                    $success_msg = "Delivery Confirmed! The handshake was successful.";
                }
            } else {
                $error_msg = "Incorrect PIN. Please ask the farmer for the correct 4-digit code.";
            }
        }
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
$job_status = strtolower($job['status']);

// --- CHECK FOR EXISTING BIDS ---
$my_bid = null;
$bid_sql = "SELECT * FROM job_bids WHERE job_id = ? AND transporter_id = ?";
$bid_stmt = mysqli_prepare($conn, $bid_sql);
mysqli_stmt_bind_param($bid_stmt, "ii", $request_id, $transporter_id);
mysqli_stmt_execute($bid_stmt);
$bid_res = mysqli_stmt_get_result($bid_stmt);
if ($bid_res->num_rows > 0) {
    $my_bid = $bid_res->fetch_assoc();
}

$distance = floatval($job['distance']);
$suggested_price = $distance * $base_rate;

$unit_type = 'kg';
$loading_labor = 'Not Specified';
$vehicle_req = 'Any';
if (!empty($job['produce_desc'])) {
    if (preg_match('/Unit:\s*([a-zA-Z]+)/', $job['produce_desc'], $matches)) $unit_type = $matches[1];
    if (preg_match('/Labor:\s*([a-zA-Z]+)/', $job['produce_desc'], $matches)) $loading_labor = ucfirst($matches[1]);
    if (preg_match('/Vehicle Req:\s*([a-zA-Z0-9_-]+)/', $job['produce_desc'], $matches)) $vehicle_req = ucfirst($matches[1]);
}

$is_my_job = ($job['transporter_id'] == $transporter_id);
$taken_by_other = (!empty($job['transporter_id']) && !$is_my_job);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details & Bidding - AgriMove</title>
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
                <p class="text-gray-500 mb-6">This request was awarded to another transporter.</p>
                <a href="find_jobs.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg transition inline-block">Browse Other Jobs</a>
            </div>
        <?php else: ?>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        Job #TR-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?>
                        <?php 
                        $bgClass = "bg-gray-100 text-gray-800";
                        if($job_status == 'pending') $bgClass = "bg-orange-100 text-orange-800";
                        if($job_status == 'accepted') $bgClass = "bg-blue-100 text-blue-800";
                        if($job_status == 'in_transit') $bgClass = "bg-purple-100 text-purple-800";
                        if($job_status == 'delivered') $bgClass = "bg-green-100 text-green-800";
                        ?>
                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?php echo $bgClass; ?>">
                            <?php echo str_replace('_', ' ', $job_status); ?>
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
                                        <span class="font-medium">
                                            <?php echo ($job_status !== 'pending' && $is_my_job) ? htmlspecialchars($job['pickup_exact_address']) : '<i class="fa-solid fa-lock text-gray-400"></i> Hidden until accepted'; ?>
                                        </span>
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
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Total Weight</p>
                                <p class="text-sm font-bold text-gray-900"><?php echo floatval($job['total_amount']) . " " . htmlspecialchars($unit_type); ?></p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Loading Labor</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($loading_labor); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    
                    <div class="bg-white rounded-xl shadow-sm border-2 <?php echo ($job_status == 'pending') ? 'border-blue-200 bg-blue-50/30' : 'border-purple-200 bg-purple-50/30'; ?> p-6 sticky top-24">
                        
                        <h2 class="text-base font-bold text-gray-900 mb-4 text-center">Bidding & Controls</h2>
                        
                        <?php if ($job_status === 'pending'): ?>
                            
                            <?php if ($profile['is_verified'] != 1): ?>
                                <div class="text-center p-6 bg-orange-50 border border-orange-200 rounded-xl">
                                    <div class="w-16 h-16 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-3">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                    <h3 class="font-bold text-gray-900 mb-1 text-sm">Verification Required</h3>
                                    <p class="text-[11px] text-gray-600 mb-4">You cannot bid on jobs until the Admin has approved your account documents.</p>
                                    <a href="transporter_settings.php" class="text-xs font-bold text-orange-600 hover:text-orange-700 underline">Check Status</a>
                                </div>

                            <?php elseif ($my_bid): ?>
                                <div class="text-center">
                                    <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-3">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </div>
                                    <h3 class="font-bold text-gray-900 mb-1">Offer Submitted</h3>
                                    <p class="text-2xl font-black text-blue-700 mb-2">Ksh <?php echo number_format($my_bid['bid_amount']); ?></p>
                                    
                                    <?php 
                                    $bidStatusClass = 'bg-orange-100 text-orange-700';
                                    if($my_bid['status'] == 'accepted') $bidStatusClass = 'bg-green-100 text-green-700';
                                    if($my_bid['status'] == 'rejected') $bidStatusClass = 'bg-red-100 text-red-700';
                                    ?>
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?php echo $bidStatusClass; ?>">
                                        Status: <?php echo htmlspecialchars($my_bid['status']); ?>
                                    </span>
                                    
                                    <p class="text-sm text-gray-500 mt-4">Waiting for the farmer to review and accept your offer.</p>
                                </div>
                            <?php else: ?>
                                <div class="mb-4 bg-white p-4 rounded-lg border border-blue-100 shadow-sm text-center">
                                    <p class="text-xs text-gray-500 uppercase font-bold mb-1">Smart Estimate</p>
                                    <p class="text-2xl font-black text-blue-600">Ksh <?php echo number_format($suggested_price); ?></p>
                                    <p class="text-[10px] text-gray-400 mt-1">Based on <?php echo $distance; ?> km × Ksh <?php echo $base_rate; ?>/km</p>
                                </div>

                                <form method="POST">
                                    <input type="hidden" name="action" value="submit_bid">
                                    
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Your Final Offer (Ksh)</label>
                                        <input type="number" name="bid_amount" value="<?php echo $suggested_price; ?>" class="w-full pl-3 pr-3 py-2 border border-gray-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-bold text-lg" required>
                                    </div>

                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Note to Farmer</label>
                                        <input type="text" name="notes" placeholder="e.g., I am nearby" class="w-full pl-3 pr-3 py-2 border border-gray-300 rounded-lg outline-none focus:border-blue-500 text-sm">
                                    </div>

                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-lg shadow-blue-500/30 flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-handshake"></i> Send Offer
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                        <?php elseif ($is_my_job): ?>
                            <?php if ($job_status === 'accepted'): ?>
                                <p class="text-sm text-gray-600 text-center mb-6">Your bid won! Contact the farmer and head to the pickup location.</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="in_transit">
                                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-lg shadow-purple-500/30 flex items-center justify-center gap-2 text-lg">
                                        <i class="fa-solid fa-truck-fast"></i> Start Transit
                                    </button>
                                </form>
                            
                            <?php elseif ($job_status === 'in_transit'): ?>
                                <div class="text-center mb-4">
                                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-xl mx-auto mb-2 border border-blue-100">
                                        <i class="fa-solid fa-handshake-angle"></i>
                                    </div>
                                    <h3 class="font-bold text-gray-900 mb-1">Delivery Handshake</h3>
                                    <p class="text-xs text-gray-500 mb-4">Ask the farmer for their secret 4-digit PIN to confirm the drop-off.</p>
                                </div>

                                <form method="POST" class="space-y-3">
                                    <input type="hidden" name="action" value="delivered">
                                    <div>
                                        <input type="text" name="otp_code" maxlength="4" placeholder="0000" required autocomplete="off"
                                            class="w-full text-center text-3xl tracking-[0.5em] font-black px-4 py-3 bg-white border-2 border-gray-200 rounded-xl outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition shadow-inner">
                                    </div>
                                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-lg shadow-green-500/30 flex items-center justify-center gap-2 text-base">
                                        <i class="fa-solid fa-box-check"></i> Verify & Complete
                                    </button>
                                </form>

                            <?php elseif ($job_status === 'delivered'): ?>
                                <div class="text-center py-4">
                                    <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-3">
                                        <i class="fa-solid fa-check-double"></i>
                                    </div>
                                    <h3 class="font-bold text-gray-900">Delivery Complete</h3>
                                </div>
                            <?php endif; ?>
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

                        <?php if ($job_status !== 'pending' && $is_my_job): ?>
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
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        <?php endif; ?>
    </main>

</body>
</html>