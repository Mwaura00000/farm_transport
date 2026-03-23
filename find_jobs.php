<?php
session_start();
include "db_connect.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'transporter'){
    header("Location: login.php");
    exit();
}

$transporter_id = $_SESSION['user_id'];

// 1. SECURITY CHECK: Get user details & verification status
$user_sql = "SELECT u.name, tp.is_verified FROM users u LEFT JOIN transporter_profiles tp ON u.id = tp.user_id WHERE u.id='$transporter_id'";
$user_res = $conn->query($user_sql);
$user_data = $user_res->fetch_assoc();
$name = $user_data['name'] ?? 'Driver';
$is_verified = (isset($user_data['is_verified']) && $user_data['is_verified'] == 1) ? true : false;

// 2. Fetch Unread Messages (for the notification bell/sidebar)
$unreadMsgRes = $conn->query("SELECT COUNT(*) as total FROM messages WHERE receiver_id='$transporter_id' AND is_read=0");
$unreadMessages = $unreadMsgRes->fetch_assoc()['total'] ?? 0;

// 3. Fetch all pending jobs
$query = "SELECT tr.*, p.name as produce_name, p.weight, u.name as farmer_name 
          FROM transport_requests tr 
          JOIN produce p ON tr.produce_id = p.id 
          JOIN users u ON tr.farmer_id = u.id 
          WHERE tr.status = 'pending' ORDER BY tr.id DESC";
$all_jobs = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Loads - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .locked-overlay { backdrop-filter: blur(5px); }
    </style>
</head>
<body class="text-slate-800 flex h-screen overflow-hidden">

    <aside class="w-64 bg-white border-r border-slate-200 flex flex-col hidden md:flex flex-shrink-0 z-20 shadow-sm">
        <div class="h-20 flex items-center px-6 border-b border-slate-100">
            <div class="flex items-center gap-3 text-slate-900 text-2xl font-extrabold tracking-tight">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center shadow-md shadow-blue-500/30">
                    <i class="fa-solid fa-truck-fast text-white text-sm"></i>
                </div>
                AgriMove
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-8 space-y-1.5 overflow-y-auto">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-4 px-4">Menu</div>
            <a href="transporter_dashboard.php" class="flex items-center gap-3 px-4 py-3.5 text-slate-500 hover:bg-slate-50 hover:text-slate-900 rounded-xl font-medium transition">
                <i class="fa-solid fa-gauge w-5"></i> Dashboard
            </a>
            
            <a href="find_jobs.php" class="flex items-center justify-between px-4 py-3.5 bg-blue-50 text-blue-700 rounded-xl font-bold transition border border-blue-100 shadow-sm">
                <div class="flex items-center gap-3"><i class="fa-solid fa-magnifying-glass-location w-5"></i> Find Jobs</div>
                <?php if($is_verified && $all_jobs->num_rows > 0): ?>
                    <span class="bg-blue-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?php echo $all_jobs->num_rows; ?></span>
                <?php endif; ?>
            </a>

            <a href="my_deliveries.php" class="flex items-center gap-3 px-4 py-3.5 text-slate-500 hover:bg-slate-50 hover:text-slate-900 rounded-xl font-medium transition">
                <i class="fa-solid fa-route w-5"></i> My Deliveries
            </a>
            <a href="messages.php" class="flex items-center justify-between px-4 py-3.5 text-slate-500 hover:bg-slate-50 hover:text-slate-900 rounded-xl font-medium transition">
                <div class="flex items-center gap-3"><i class="fa-regular fa-message w-5"></i> Messages</div>
                <?php if($unreadMessages > 0): ?>
                    <span class="bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full ring-2 ring-rose-500/20"><?php echo $unreadMessages; ?></span>
                <?php endif; ?>
            </a>
            <a href="transporter_settings.php" class="flex items-center gap-3 px-4 py-3.5 text-slate-500 hover:bg-slate-50 hover:text-slate-900 rounded-xl font-medium transition">
                <i class="fa-solid fa-user-gear w-5"></i> Settings
            </a>
        </nav>

        <div class="p-6 border-t border-slate-100">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-rose-500 hover:bg-rose-50 rounded-xl font-bold transition">
                <i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative bg-slate-50/50">
        
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200/60 flex items-center justify-between px-8 flex-shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden text-slate-400 hover:text-slate-600 text-xl focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight hidden sm:block">Live Load Board</h1>
            </div>
            
            <div class="flex items-center gap-6">
                <?php if($is_verified): ?>
                    <div class="hidden sm:flex items-center gap-2 text-emerald-600 text-xs font-bold px-2">
                        <i class="fa-solid fa-shield-check"></i> Verified
                    </div>
                <?php else: ?>
                    <div class="hidden sm:flex items-center gap-2 text-amber-600 text-xs font-bold px-2">
                        <i class="fa-solid fa-lock"></i> Unverified
                    </div>
                <?php endif; ?>
                
                <button class="text-slate-400 hover:text-slate-600 relative">
                    <i class="fa-regular fa-bell text-xl"></i>
                    <?php if($unreadMessages > 0): ?>
                        <span class="absolute top-0 right-0 w-2 h-2 bg-rose-500 rounded-full border border-white"></span>
                    <?php endif; ?>
                </button>
                <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($name); ?></p>
                        <p class="text-[10px] text-blue-600 font-black uppercase tracking-widest mt-0.5">Transporter</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-bold shadow-md shadow-blue-500/20">
                        <?php echo strtoupper(substr($name, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            <div class="max-w-5xl mx-auto relative">
                
                <div class="mb-10 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Available Loads</h2>
                        <p class="text-slate-500 text-sm mt-1.5 font-medium">Browse open transport requests from farmers across Kenya.</p>
                    </div>
                    <?php if($is_verified && $all_jobs->num_rows > 0): ?>
                        <div class="bg-blue-50 text-blue-600 px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest border border-blue-100 shadow-sm">
                            <?php echo $all_jobs->num_rows; ?> Active Requests
                        </div>
                    <?php endif; ?>
                </div>

                <?php if(!$is_verified): ?>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 p-16 text-center max-w-2xl mx-auto mt-12">
                        <div class="w-24 h-24 bg-slate-50 text-slate-300 rounded-3xl flex items-center justify-center mx-auto mb-6 text-4xl border border-slate-100 shadow-inner">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <h3 class="text-2xl font-extrabold text-slate-900 mb-2 tracking-tight">Load Board Locked</h3>
                        <p class="text-slate-500 font-medium mb-8">You must be a verified transporter to view and bid on active farm loads. Please check your settings page to complete your KYC verification.</p>
                        <a href="transporter_settings.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-8 rounded-xl transition shadow-lg shadow-blue-500/30 active:scale-95 uppercase tracking-wide text-sm">
                            Go to Verification
                        </a>
                    </div>
                
                <?php else: ?>
                    <div class="grid gap-6">
                        <?php if($all_jobs->num_rows > 0): ?>
                            <?php while($job = $all_jobs->fetch_assoc()): ?>
                                <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/60 shadow-sm hover:border-blue-300 hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col md:flex-row justify-between items-center gap-6 group">
                                    
                                    <div class="w-full md:w-auto flex-1">
                                        <div class="flex items-center gap-3 mb-3">
                                            <span class="text-[10px] font-black text-slate-500 bg-slate-100 px-2.5 py-1 rounded-md uppercase tracking-widest">TR-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                            <span class="text-[10px] font-black text-blue-600 bg-blue-50 border border-blue-100 px-2.5 py-1 rounded-md uppercase tracking-widest">New</span>
                                        </div>
                                        
                                        <h2 class="text-2xl font-extrabold text-slate-900 mb-4 tracking-tight"><?php echo htmlspecialchars($job['produce_name']); ?></h2>
                                        
                                        <div class="flex items-center gap-4 bg-slate-50 border border-slate-100 p-4 rounded-2xl w-fit group-hover:bg-blue-50/30 group-hover:border-blue-100 transition-colors">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fa-solid fa-circle-dot text-blue-500 text-[10px]"></i>
                                                <div class="w-0.5 h-6 bg-slate-200 my-1 rounded-full"></div>
                                                <i class="fa-solid fa-location-dot text-rose-500 text-[10px]"></i>
                                            </div>
                                            <div class="flex flex-col gap-3 text-sm text-slate-700 font-bold">
                                                <p><?php echo htmlspecialchars($job['pickup_town']); ?></p>
                                                <p><?php echo htmlspecialchars($job['delivery_town']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="w-full md:w-auto flex flex-row md:flex-col justify-between md:items-end border-t md:border-t-0 md:border-l border-slate-100 pt-5 md:pt-0 md:pl-8 min-w-[180px]">
                                        <div class="text-left md:text-right mb-0 md:mb-6">
                                            <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mb-1">Total Weight / Amount</p>
                                            <p class="font-black text-xl text-slate-900"><?php echo floatval($job['weight']); ?> <span class="text-sm font-bold text-slate-500">Units</span></p>
                                        </div>
                                        <a href="job_details.php?id=<?php echo $job['id']; ?>" class="bg-slate-900 text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-600 transition shadow-md shadow-slate-900/20 whitespace-nowrap active:scale-95 text-sm uppercase tracking-wide text-center w-full md:w-auto">
                                            View & Bid
                                        </a>
                                    </div>

                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="bg-white p-16 text-center rounded-3xl border border-dashed border-slate-200 shadow-sm">
                                <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-2xl flex items-center justify-center text-4xl mx-auto mb-5 shadow-inner">
                                    <i class="fa-solid fa-box-open"></i>
                                </div>
                                <h3 class="font-extrabold text-slate-900 mb-2 text-xl tracking-tight">No loads available right now</h3>
                                <p class="text-slate-500 text-sm font-medium">Check back later. New requests will appear here automatically.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            const sidebar = document.querySelector('aside');
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('absolute'); sidebar.classList.toggle('z-50');
            sidebar.classList.toggle('h-full');
        });
    </script>
</body>
</html>