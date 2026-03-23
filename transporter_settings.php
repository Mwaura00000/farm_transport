<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'transporter') {
    header("Location: login.php");
    exit();
}

$transporter_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

$upload_dir = 'uploads/kyc/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fetch Current Data First (to have defaults)
$user_sql = "SELECT u.name, u.email, u.phone, tp.* FROM users u LEFT JOIN transporter_profiles tp ON u.id = tp.user_id WHERE u.id = $transporter_id";
$current_user = $conn->query($user_sql)->fetch_assoc();

// NEW LOGIC: Lock the profile if it is Verified OR Pending Admin Review
$kyc_status = $current_user['kyc_status'] ?? 'unverified';
$is_locked = ($kyc_status === 'pending' || (isset($current_user['is_verified']) && $current_user['is_verified'] == 1));

// --- LOGIC: HANDLE REQUEST TO UNLOCK PROFILE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_unlock') {
    
    $old_plate = $current_user['plate_no'] ?? null;
    $old_type = $current_user['vehicle_type'] ?? null;

    // Setting to 'unverified' so the form fields open up again for editing
    $unlock_sql = "UPDATE transporter_profiles 
                   SET is_verified = 0, 
                       kyc_status = 'unverified', 
                       old_plate_no = ?, 
                       old_vehicle_type = ? 
                   WHERE user_id = ?";
                   
    $u_stmt = mysqli_prepare($conn, $unlock_sql);
    mysqli_stmt_bind_param($u_stmt, "ssi", $old_plate, $old_type, $transporter_id);
    
    if(mysqli_stmt_execute($u_stmt)) {
        header("Location: transporter_settings.php?msg=unlocked");
        exit();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'unlocked') {
    $success_msg = "Profile unlocked! Make your changes and re-submit for Admin approval.";
}

// --- LOGIC: HANDLE PROFILE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    
    $new_name = isset($_POST['name']) ? trim($_POST['name']) : $current_user['name'];
    $new_email = isset($_POST['email']) ? trim($_POST['email']) : $current_user['email'];
    $new_phone = isset($_POST['phone']) ? trim($_POST['phone']) : $current_user['phone'];
    $base_rate = isset($_POST['base_rate']) ? floatval($_POST['base_rate']) : $current_user['base_rate_per_km'];
    
    $license_no = isset($_POST['license_no']) ? trim($_POST['license_no']) : $current_user['license_no'];
    $plate_no = isset($_POST['plate_no']) ? trim(strtoupper($_POST['plate_no'])) : $current_user['plate_no'];
    $vehicle_type = isset($_POST['vehicle_type']) ? trim($_POST['vehicle_type']) : $current_user['vehicle_type'];

    $id_doc_path = $current_user['id_document'] ?? null;
    $dl_doc_path = $current_user['dl_document'] ?? null;
    $is_verified = $current_user['is_verified'] ?? 0;

    $uploaded_new_doc = false;
    
    if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
        $id_doc_path = $upload_dir . 'ID_' . $transporter_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['id_document']['tmp_name'], $id_doc_path);
        $uploaded_new_doc = true;
    }

    if (isset($_FILES['dl_document']) && $_FILES['dl_document']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['dl_document']['name'], PATHINFO_EXTENSION));
        $dl_doc_path = $upload_dir . 'DL_' . $transporter_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['dl_document']['tmp_name'], $dl_doc_path);
        $uploaded_new_doc = true;
    }

    // Always set to pending when they submit an unlocked form!
    $new_kyc_status = 'pending';

    mysqli_begin_transaction($conn);
    try {
        $stmt_u = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt_u->bind_param("sssi", $new_name, $new_email, $new_phone, $transporter_id);
        $stmt_u->execute();

        if ($is_locked) {
            // If they somehow forced a post while locked, only save base rate
            $stmt_p = $conn->prepare("UPDATE transporter_profiles SET base_rate_per_km = ? WHERE user_id = ?");
            $stmt_p->bind_param("di", $base_rate, $transporter_id);
        } else {
            // Normal update and send back to pending queue
            $stmt_p = $conn->prepare("UPDATE transporter_profiles SET license_no=?, plate_no=?, vehicle_type=?, base_rate_per_km=?, id_document=?, dl_document=?, kyc_status=?, is_verified=0 WHERE user_id=?");
            $stmt_p->bind_param("sssdsssi", $license_no, $plate_no, $vehicle_type, $base_rate, $id_doc_path, $dl_doc_path, $new_kyc_status, $transporter_id);
        }
        $stmt_p->execute();

        mysqli_commit($conn);
        $success_msg = "Documents submitted! Pending Admin Review.";
        $_SESSION['name'] = $new_name;
        
        // Refresh to trigger lock
        $user_sql = "SELECT u.name, u.email, u.phone, tp.* FROM users u LEFT JOIN transporter_profiles tp ON u.id = tp.user_id WHERE u.id = $transporter_id";
        $current_user = $conn->query($user_sql)->fetch_assoc();
        $kyc_status = $current_user['kyc_status'];
        $is_locked = true;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Update Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        .input-field { width: 100%; padding: 0.625rem 2.5rem; background-color: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; outline: none; transition: all 0.2s; }
        .input-field:focus { border-color: #3b82f6; ring: 2px; ring-color: #3b82f6; }
        .input-field:disabled { background-color: #f3f4f6; color: #6b7280; cursor: not-allowed; border-color: #e5e7eb; }
        .input-label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.375rem; }
    </style>
</head>
<body class="text-gray-800 flex h-screen overflow-hidden">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col hidden md:flex flex-shrink-0 z-20">
        <div class="h-16 flex items-center px-6 border-b border-gray-200">
            <div class="flex items-center gap-2 text-blue-600 text-xl font-bold"><i class="fa-solid fa-truck-fast"></i> AgriMove</div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="transporter_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition"><i class="fa-solid fa-house w-5"></i> Dashboard</a>
            <a href="<?php echo (isset($current_user['is_verified']) && $current_user['is_verified'] == 1) ? 'find_jobs.php' : '#'; ?>" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg font-medium transition"><i class="fa-solid fa-magnifying-glass-location w-5"></i> Find Jobs</a>
            <a href="transporter_settings.php" class="flex items-center gap-3 px-4 py-3 bg-blue-50 text-blue-700 rounded-lg font-medium transition"><i class="fa-solid fa-gear w-5"></i> Settings & KYC</a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 shadow-sm">
            <h1 class="text-xl font-semibold text-gray-800">Account Security & Settings</h1>
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold"><?php echo strtoupper(substr($current_user['name'] ?? 'D', 0, 1)); ?></div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            <div class="max-w-4xl mx-auto">

                <?php if($kyc_status === 'rejected'): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 shadow-sm">
                        <i class="fa-solid fa-circle-xmark text-red-600 text-2xl"></i>
                        <div>
                            <h3 class="font-bold text-red-800 text-sm mb-0.5">Verification Rejected</h3>
                            <p class="text-xs text-red-600"><span class="font-bold">Admin Note:</span> <?php echo htmlspecialchars($current_user['rejection_reason'] ?? 'Please review your details and re-submit clear documents.'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($is_locked): ?>
                    <div class="mb-6 p-4 <?php echo ($kyc_status === 'pending') ? 'bg-orange-50 border-orange-200' : 'bg-green-50 border-green-200'; ?> border rounded-xl flex justify-between items-center shadow-sm">
                        <div class="flex items-center gap-3">
                            <?php if($kyc_status === 'pending'): ?>
                                <i class="fa-solid fa-clock-rotate-left text-orange-600 text-xl"></i>
                                <div>
                                    <h3 class="font-bold text-orange-800">Pending Admin Review (Locked)</h3>
                                    <p class="text-xs text-orange-600">Your details are locked while under review. Need to change something?</p>
                                </div>
                            <?php else: ?>
                                <i class="fa-solid fa-circle-check text-green-600 text-xl"></i>
                                <div>
                                    <h3 class="font-bold text-green-800">Verified & Locked</h3>
                                    <p class="text-xs text-green-600">Truck details are secured. Click 'Request Edit' to unlock.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <form method="POST" onsubmit="return confirm('⚠️ WARNING: Unlocking will cancel any pending reviews or active verifications. Proceed?');">
                            <button type="submit" name="action" value="request_unlock" class="text-xs bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg font-bold hover:bg-gray-100 transition shadow-sm">Request Edit</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-center gap-3 shadow-sm">
                        <i class="fa-solid fa-lock-open text-blue-600 text-xl"></i>
                        <div>
                            <h3 class="font-bold text-blue-800">Profile Unlocked</h3>
                            <p class="text-xs text-blue-600">You can edit your details. Save and submit below to lock them for Admin review.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success_msg) echo "<div class='mb-6 p-4 bg-green-50 text-green-700 border border-green-200 rounded-lg text-sm font-medium shadow-sm'>$success_msg</div>"; ?>
                <?php if ($error_msg) echo "<div class='mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-lg text-sm font-medium shadow-sm'>$error_msg</div>"; ?>

                <form action="transporter_settings.php" method="POST" enctype="multipart/form-data" class="space-y-6" id="kycForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-bold text-gray-900 border-b border-gray-50 pb-3 mb-4">Logistics Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="input-label">Full Name</label>
                                <div class="relative"><i class="fa-solid fa-user absolute left-3 top-3.5 text-gray-400"></i>
                                <input type="text" name="name" class="input-field" value="<?php echo htmlspecialchars($current_user['name'] ?? ''); ?>" required></div>
                            </div>
                            <div>
                                <label class="input-label">Phone</label>
                                <div class="relative"><i class="fa-solid fa-phone absolute left-3 top-3.5 text-gray-400"></i>
                                <input type="tel" name="phone" class="input-field" pattern="[0-9]{10,12}" title="Enter a valid 10 to 12 digit phone number" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" required></div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="input-label">Email</label>
                                <div class="relative"><i class="fa-solid fa-envelope absolute left-3 top-3.5 text-gray-400"></i>
                                <input type="email" name="email" class="input-field" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>" required></div>
                            </div>
                            <div>
                                <label class="input-label">Vehicle Type</label>
                                <select name="vehicle_type" class="w-full p-2.5 bg-white border border-gray-300 rounded-lg outline-none text-sm" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                    <option value="pickup" <?php if(($current_user['vehicle_type'] ?? '') == 'pickup') echo 'selected'; ?>>Pickup (1 Ton)</option>
                                    <option value="canter" <?php if(($current_user['vehicle_type'] ?? '') == 'canter') echo 'selected'; ?>>Canter (3-5 Tons)</option>
                                    <option value="lorry" <?php if(($current_user['vehicle_type'] ?? '') == 'lorry') echo 'selected'; ?>>Lorry (10+ Tons)</option>
                                </select>
                            </div>
                            <div>
                                <label class="input-label">License Plate (e.g., KCA 123A)</label>
                                <div class="relative"><i class="fa-solid fa-barcode absolute left-3 top-3.5 text-gray-400"></i>
                                <input type="text" name="plate_no" class="input-field uppercase font-bold" 
                                       pattern="^K[A-Z]{2}\s?\d{3}[A-Z]$" 
                                       title="Format must be like KCA 123A or KCA123A"
                                       value="<?php echo htmlspecialchars($current_user['plate_no'] ?? ''); ?>" <?php echo $is_locked ? 'disabled' : ''; ?> required></div>
                            </div>
                            <div>
                                <label class="input-label text-blue-600">Base Rate (Ksh/KM) <span class="text-xs font-normal text-gray-400">(Always Editable)</span></label>
                                <div class="relative"><div class="absolute left-3 top-3 font-bold text-blue-500 text-sm">Ksh</div>
                                <input type="number" name="base_rate" class="input-field pl-12 font-bold text-blue-700" min="50" max="5000" value="<?php echo floatval($current_user['base_rate_per_km'] ?? 0); ?>" required></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 <?php echo $is_locked ? 'opacity-60' : ''; ?>">
                        <h3 class="text-lg font-bold text-gray-900 border-b border-gray-50 pb-3 mb-4">KYC Documents</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 border border-gray-100 rounded-lg bg-gray-50">
                                <label class="input-label">National ID</label>
                                <?php if(!$is_locked && empty($current_user['id_document'])): ?> <span class="text-xs text-red-500 block mb-2">* Required</span> <?php endif; ?>
                                <input type="file" name="id_document" class="text-xs" accept=".jpg,.png,.pdf" <?php echo $is_locked ? 'disabled' : ''; ?> <?php echo empty($current_user['id_document']) && !$is_locked ? 'required' : ''; ?>>
                            </div>
                            <div class="p-4 border border-gray-100 rounded-lg bg-gray-50">
                                <label class="input-label">Driving License / Logbook</label>
                                <?php if(!$is_locked && empty($current_user['dl_document'])): ?> <span class="text-xs text-red-500 block mb-2">* Required</span> <?php endif; ?>
                                <input type="file" name="dl_document" class="text-xs" accept=".jpg,.png,.pdf" <?php echo $is_locked ? 'disabled' : ''; ?> <?php echo empty($current_user['dl_document']) && !$is_locked ? 'required' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <?php if(!$is_locked): ?>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg transition tracking-wide uppercase text-sm">
                            <i class="fa-solid fa-lock mr-2"></i> Lock & Submit for Review
                        </button>
                    <?php else: ?>
                        <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-4 rounded-xl shadow-lg transition tracking-wide uppercase text-sm">
                            Save Base Rate Changes Only
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </div>
</body>
</html>