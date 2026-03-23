<?php
session_start();
include "db_connect.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'transporter'){
    header("Location: login.php");
    exit();
}

$transporter_id = $_SESSION['user_id'];

// Fetch all jobs won by this driver
$query = "SELECT tr.*, p.name as produce_name, p.weight, u.name as farmer_name 
          FROM transport_requests tr 
          JOIN produce p ON tr.produce_id = p.id 
          JOIN users u ON tr.farmer_id = u.id 
          WHERE tr.transporter_id = '$transporter_id' ORDER BY tr.id DESC";
$my_jobs = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Deliveries - AgriMove</title>
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

    <div class="max-w-5xl mx-auto p-6 md:p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Delivery History</h1>
            <p class="text-gray-500 mt-1">Track your active and completed logistics runs.</p>
        </div>

        <div class="grid gap-4">
            <?php if($my_jobs->num_rows > 0): ?>
                <?php while($job = $my_jobs->fetch_assoc()): ?>
                    <?php 
                        $status = strtolower($job['status']);
                        $bgClass = "bg-gray-100 text-gray-800 border-l-gray-400";
                        if($status == 'accepted') $bgClass = "bg-blue-50 text-blue-800 border-l-blue-500";
                        if($status == 'in_transit') $bgClass = "bg-purple-50 text-purple-800 border-l-purple-500";
                        if($status == 'delivered') $bgClass = "bg-green-50 text-green-800 border-l-green-500";
                    ?>
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm border-l-4 <?php echo $bgClass; ?> flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="w-full md:w-auto flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-xs font-bold uppercase tracking-wider px-2 py-1 rounded <?php echo $bgClass; ?>">
                                    <?php echo str_replace('_', ' ', $status); ?>
                                </span>
                                <span class="text-xs text-gray-400 font-bold">TR-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <h2 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($job['produce_name']); ?></h2>
                            <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($job['pickup_town']); ?> to <?php echo htmlspecialchars($job['delivery_town']); ?></p>
                        </div>
                        <div>
                            <a href="job_details.php?id=<?php echo $job['id']; ?>" class="w-full md:w-auto bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-6 py-2 rounded-lg font-bold transition text-sm text-center block whitespace-nowrap">
                                View Job Sheet
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="bg-white p-12 text-center rounded-xl border-2 border-dashed border-gray-200">
                    <p class="text-gray-500">You haven't won any delivery jobs yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>