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

// 1. GET THE REQUEST ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my_requests.php");
    exit();
}
$request_id = intval($_GET['id']);

// 2. HANDLE FORM SUBMISSION (UPDATE)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $produce_type = trim($_POST['produce_type'] ?? '');
    $cargo_type = trim($_POST['cargo_type'] ?? '');
    $unit_type = trim($_POST['unit_type'] ?? 'kg');
    
    if ($cargo_type === 'bulk' || $cargo_type === 'livestock') {
        $quantity = ($cargo_type === 'livestock') ? intval($_POST['quantity'] ?? 0) : 1;
        $total_amount = ($cargo_type === 'livestock') ? $quantity : floatval($_POST['total_bulk_amount'] ?? 0);
    } else {
        $quantity = intval($_POST['quantity'] ?? 0);
        $amount_per_unit = floatval($_POST['amount_per_unit'] ?? 0);
        $total_amount = $quantity * $amount_per_unit;
    }
    
    $loading_labor = trim($_POST['loading_labor'] ?? '');
    $pickup_county = trim($_POST['pickup_county'] ?? '');
    $pickup_town = trim($_POST['pickup_town'] ?? '');
    $pickup_village = trim($_POST['pickup_village'] ?? '');
    $pickup_pin = trim($_POST['pickup_pin'] ?? ''); 
    $road_condition = trim($_POST['road_condition'] ?? '');
    
    $delivery_county = trim($_POST['delivery_county'] ?? '');
    $delivery_town = trim($_POST['delivery_town'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $delivery_pin = trim($_POST['delivery_pin'] ?? ''); 
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    
    $vehicle_required = trim($_POST['vehicle_required'] ?? '');
    $approx_distance = floatval($_POST['approx_distance'] ?? 0);
    $preferred_datetime = !empty($_POST['preferred_datetime']) ? $_POST['preferred_datetime'] : NULL;

    if (empty($produce_type) || empty($pickup_county) || empty($delivery_county) || empty($delivery_address)) {
        $error_msg = "Please fill in all the required fields marked with an asterisk (*).";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // First, get the produce_id linked to this request
            $check_sql = "SELECT produce_id FROM transport_requests WHERE id = ? AND farmer_id = ? AND status = 'pending'";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $request_id, $farmer_id);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);
            
            if ($check_res->num_rows === 0) {
                throw new Exception("Request cannot be edited. It may have already been accepted or cancelled.");
            }
            $produce_id = $check_res->fetch_assoc()['produce_id'];
            mysqli_stmt_close($check_stmt);

            // STEP 1: Update `produce` table
            $produce_desc = "Vehicle Req: $vehicle_required | Labor: $loading_labor | Unit: $unit_type";
            $sql_produce = "UPDATE produce SET name=?, quantity=?, weight=?, description=? WHERE id=?";
            $stmt1 = mysqli_prepare($conn, $sql_produce);
            mysqli_stmt_bind_param($stmt1, "sddsi", $produce_type, $quantity, $total_amount, $produce_desc, $produce_id);
            mysqli_stmt_execute($stmt1);
            mysqli_stmt_close($stmt1);

            // STEP 2: Update `transport_requests` table
            $sql_transport = "UPDATE transport_requests SET 
                cargo_type=?, pickup_location=?, pickup_county=?, pickup_town=?, pickup_exact_address=?, pickup_description=?, 
                destination_location=?, delivery_county=?, delivery_town=?, delivery_exact_address=?, emergency_contact_name=?, emergency_contact_phone=?, 
                distance=?, request_date=? 
                WHERE id=? AND farmer_id=?";
            
            $stmt2 = mysqli_prepare($conn, $sql_transport);
            mysqli_stmt_bind_param($stmt2, "ssssssssssssdsii", 
                $cargo_type, $pickup_pin, $pickup_county, $pickup_town, $pickup_village, $road_condition,
                $delivery_pin, $delivery_county, $delivery_town, $delivery_address, $contact_name, $contact_phone, 
                $approx_distance, $preferred_datetime, $request_id, $farmer_id
            );
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            mysqli_commit($conn);
            $success_msg = "Transport request updated successfully!";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = $e->getMessage();
        }
    }
}

// 3. FETCH CURRENT DATA TO PRE-FILL FORM
$sql = "SELECT tr.*, p.name AS produce_name, p.quantity, p.weight AS total_amount, p.description AS produce_desc 
        FROM transport_requests tr 
        LEFT JOIN produce p ON tr.produce_id = p.id
        WHERE tr.id = ? AND tr.farmer_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $request_id, $farmer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result->num_rows === 0) {
    header("Location: my_requests.php"); // Redirect if it doesn't exist or isn't theirs
    exit();
}

$request = $result->fetch_assoc();

// Check if it's still editable
if ($request['status'] !== 'pending') {
    $error_msg = "This request is marked as '" . ucfirst($request['status']) . "' and can no longer be edited.";
    $is_editable = false;
} else {
    $is_editable = true;
}

// Unpack the description string to get Unit, Labor, and Vehicle
$unit_type = 'kg';
$loading_labor = '';
$vehicle_required = '';
if (!empty($request['produce_desc'])) {
    if (preg_match('/Unit:\s*([a-zA-Z]+)/', $request['produce_desc'], $matches)) $unit_type = $matches[1];
    if (preg_match('/Labor:\s*([a-zA-Z]+)/', $request['produce_desc'], $matches)) $loading_labor = strtolower($matches[1]);
    if (preg_match('/Vehicle Req:\s*([a-zA-Z0-9_-]+)/', $request['produce_desc'], $matches)) $vehicle_required = strtolower($matches[1]);
}

// Calculate amount per unit for the UI
$amount_per_unit = ($request['quantity'] > 0) ? ($request['total_amount'] / $request['quantity']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Request - AgriMove</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        .input-field { width: 100%; padding: 0.625rem; background-color: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 0.5rem; outline: none; transition: border-color 0.2s; }
        .input-field:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
        .input-label { display: block; font-size: 0.875rem; font-weight: 500; color: #111827; margin-bottom: 0.375rem; }
    </style>
</head>
<body class="text-gray-800">

    <nav class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center sticky top-0 z-10 shadow-sm">
        <div class="flex items-center gap-2 text-green-600 text-xl font-bold">
            <i class="fa-solid fa-truck-fast"></i> AgriMove
        </div>
        <a href="view_request.php?id=<?php echo $request_id; ?>" class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition text-gray-700 decoration-none">
            <i class="fa-solid fa-arrow-left"></i> Back to Details
        </a>
    </nav>

    <main class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
            
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Edit Transport Request</h1>
                <p class="text-gray-500">Update the details of Request #TR-<?php echo str_pad($request_id, 4, '0', STR_PAD_LEFT); ?></p>
            </div>

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

            <?php if ($is_editable): ?>
            <form action="edit_request.php?id=<?php echo $request_id; ?>" method="POST" class="space-y-8">
                <input type="hidden" name="unit_type" id="unitType" value="<?php echo htmlspecialchars($unit_type); ?>">

                <datalist id="kenyaCounties">
                    <option value="Baringo"><option value="Bomet"><option value="Bungoma"><option value="Busia"><option value="Elgeyo Marakwet"><option value="Embu"><option value="Garissa"><option value="Homa Bay"><option value="Isiolo"><option value="Kajiado"><option value="Kakamega"><option value="Kericho"><option value="Kiambu"><option value="Kilifi"><option value="Kirinyaga"><option value="Kisii"><option value="Kisumu"><option value="Kitui"><option value="Kwale"><option value="Laikipia"><option value="Lamu"><option value="Machakos"><option value="Makueni"><option value="Mandera"><option value="Marsabit"><option value="Meru"><option value="Migori"><option value="Mombasa"><option value="Murang'a"><option value="Nairobi"><option value="Nakuru"><option value="Nandi"><option value="Narok"><option value="Nyamira"><option value="Nyandarua"><option value="Nyeri"><option value="Samburu"><option value="Siaya"><option value="Taita Taveta"><option value="Tana River"><option value="Tharaka Nithi"><option value="Trans Nzoia"><option value="Turkana"><option value="Uasin Gishu"><option value="Vihiga"><option value="Wajir"><option value="West Pokot">
                </datalist>
                <datalist id="pickupTownsList"></datalist>
                <datalist id="deliveryTownsList"></datalist>
                <datalist id="marketSuggestions">
                    <option value="Wakulima Market"><option value="Kongowea Market"><option value="Muthurwa Market"><option value="Kibuye Market"><option value="Marikiti Market"><option value="Githurai Market"><option value="Karatina Market">
                </datalist>

                <section>
                    <h2 class="text-lg font-semibold flex items-center gap-2 mb-4 text-gray-800">
                        <i class="fa-solid fa-box-open text-green-600"></i> Produce Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="input-label">Produce Type <span class="text-red-500">*</span></label>
                            <input list="produceSuggestions" name="produce_type" class="input-field" value="<?php echo htmlspecialchars($request['produce_name']); ?>" required>
                            <datalist id="produceSuggestions"><option value="Maize"><option value="Potatoes"><option value="Cabbage"><option value="Fresh Milk"><option value="Live Chickens"><option value="Cows / Cattle"><option value="Tomatoes"><option value="Bananas"></datalist>
                        </div>
                        <div>
                            <label class="input-label">Cargo Type <span class="text-red-500">*</span></label>
                            <select name="cargo_type" id="cargoType" class="input-field text-gray-700" onchange="updateCargoUI()" required>
                                <option value="sacks" <?php if($request['cargo_type'] == 'sacks') echo 'selected'; ?>>Sacks/Bags (Kg)</option>
                                <option value="crates" <?php if($request['cargo_type'] == 'crates') echo 'selected'; ?>>Crates/Boxes (Kg)</option>
                                <option value="liquid" <?php if($request['cargo_type'] == 'liquid') echo 'selected'; ?>>Containers/Cans (Liters)</option>
                                <option value="livestock" <?php if($request['cargo_type'] == 'livestock') echo 'selected'; ?>>Livestock (Heads)</option>
                                <option value="bulk" <?php if($request['cargo_type'] == 'bulk') echo 'selected'; ?>>Bulk/Loose (Kg)</option>
                            </select>
                        </div>
                    </div>

                    <div id="quantityDiv" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label id="unitLabel" class="input-label">Number of Units <span class="text-red-500">*</span></label>
                            <input type="number" name="quantity" id="calcQuantity" class="input-field" value="<?php echo $request['quantity']; ?>" oninput="calculateAmount()">
                        </div>
                        <div id="perUnitContainer">
                            <label id="amountLabel" class="input-label">Amount per Unit <span class="text-red-500">*</span></label>
                            <input type="number" step="0.1" name="amount_per_unit" id="calcAmountUnit" class="input-field" value="<?php echo $amount_per_unit; ?>" oninput="calculateAmount()">
                        </div>
                    </div>

                    <div id="bulkDiv" class="mt-6" style="display: none;">
                        <label id="bulkLabel" class="input-label">Total Estimated Weight (kg) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.1" name="total_bulk_amount" id="bulkAmount" class="input-field" value="<?php echo $request['total_amount']; ?>" oninput="calculateAmount()">
                    </div>

                    <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-calculator text-green-600"></i>
                            <span class="text-gray-600">Total Amount:</span>
                            <span class="font-bold text-lg text-gray-900" id="totalAmountDisplay">
                                <?php echo floatval($request['total_amount']) . ' ' . htmlspecialchars($unit_type); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label class="input-label">Who Provides Loading Labor? <span class="text-red-500">*</span></label>
                        <select name="loading_labor" class="input-field text-gray-700 mb-1" required>
                            <option value="farmer" <?php if($loading_labor == 'farmer') echo 'selected'; ?>>Farmer (I will provide)</option>
                            <option value="driver" <?php if($loading_labor == 'driver') echo 'selected'; ?>>Driver/Transporter</option>
                        </select>
                    </div>
                </section>

                <hr class="border-gray-100">

                <section>
                    <h2 class="text-lg font-semibold flex items-center gap-2 mb-4 text-gray-800">
                        <i class="fa-solid fa-location-dot text-green-600"></i> Pickup Location
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="input-label">County <span class="text-red-500">*</span></label>
                            <input list="kenyaCounties" name="pickup_county" id="pickupCounty" class="input-field" value="<?php echo htmlspecialchars($request['pickup_county']); ?>" onchange="updateTowns('pickupCounty', 'pickupTownsList')" required>
                        </div>
                        <div>
                            <label class="input-label">Town <span class="text-red-500">*</span></label>
                            <input list="pickupTownsList" name="pickup_town" id="pickupTownInput" class="input-field" value="<?php echo htmlspecialchars($request['pickup_town']); ?>" required>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <label class="input-label">Village / Landmark</label>
                            <input type="text" name="pickup_village" class="input-field" value="<?php echo htmlspecialchars($request['pickup_exact_address']); ?>">
                        </div>
                        <div>
                            <div class="flex justify-between items-end mb-1">
                                <label class="input-label mb-0 flex items-center gap-1">GPS Coordinates / Maps Pin</label>
                                <button type="button" onclick="getFarmerLocation()" class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-3 py-1 rounded flex items-center gap-1 transition">
                                    <i class="fa-solid fa-location-crosshairs"></i> Get My Location
                                </button>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-map-pin text-gray-400"></i>
                                </div>
                                <input type="text" name="pickup_pin" id="pickupPin" class="input-field pl-10" value="<?php echo htmlspecialchars($request['pickup_location']); ?>">
                            </div>
                        </div>
                        <div>
                            <label class="input-label">Road Condition</label>
                            <select name="road_condition" class="input-field text-gray-700">
                                <option value="tarmac" <?php if($request['pickup_description'] == 'tarmac') echo 'selected'; ?>>Tarmac (Good)</option>
                                <option value="murram" <?php if($request['pickup_description'] == 'murram') echo 'selected'; ?>>Murram (Fair)</option>
                                <option value="rough" <?php if($request['pickup_description'] == 'rough') echo 'selected'; ?>>Rough/Muddy (Requires 4x4)</option>
                            </select>
                        </div>
                    </div>
                </section>

                <hr class="border-gray-100">

                <section>
                    <h2 class="text-lg font-semibold flex items-center gap-2 mb-4 text-gray-800">
                        <i class="fa-solid fa-map-location-dot text-green-600"></i> Delivery Location
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="input-label">County <span class="text-red-500">*</span></label>
                            <input list="kenyaCounties" name="delivery_county" id="deliveryCounty" class="input-field" value="<?php echo htmlspecialchars($request['delivery_county']); ?>" onchange="updateTowns('deliveryCounty', 'deliveryTownsList')" required>
                        </div>
                        <div>
                            <label class="input-label">Town <span class="text-red-500">*</span></label>
                            <input list="deliveryTownsList" name="delivery_town" id="deliveryTownInput" class="input-field" value="<?php echo htmlspecialchars($request['delivery_town']); ?>" required>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <label class="input-label">Delivery Address / Market <span class="text-red-500">*</span></label>
                            <input list="marketSuggestions" name="delivery_address" class="input-field" value="<?php echo htmlspecialchars($request['delivery_exact_address']); ?>" required>
                        </div>
                        <div>
                            <label class="input-label">Delivery GPS Coordinates / Maps Link</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-map-location text-gray-400"></i>
                                </div>
                                <input type="text" name="delivery_pin" class="input-field pl-10" value="<?php echo htmlspecialchars($request['destination_location']); ?>">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="input-label">Contact Name</label>
                                <input type="text" name="contact_name" class="input-field" value="<?php echo htmlspecialchars($request['emergency_contact_name']); ?>">
                            </div>
                            <div>
                                <label class="input-label">Contact Phone</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-phone text-gray-400"></i>
                                    </div>
                                    <input type="tel" name="contact_phone" class="input-field pl-10" value="<?php echo htmlspecialchars($request['emergency_contact_phone']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <hr class="border-gray-100">

                <section>
                    <h2 class="text-lg font-semibold flex items-center gap-2 mb-4 text-gray-800">
                        <i class="fa-solid fa-truck text-green-600"></i> Transport Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="input-label">Vehicle Required</label>
                            <select name="vehicle_required" class="input-field text-gray-700" id="vehicleSelect">
                                <option value="pickup" <?php if($vehicle_required == 'pickup') echo 'selected'; ?>>Pickup (up to 1 Ton)</option>
                                <option value="canter" <?php if($vehicle_required == 'canter') echo 'selected'; ?>>Canter (3-5 Tons)</option>
                                <option value="lorry" <?php if($vehicle_required == 'lorry') echo 'selected'; ?>>Lorry (10+ Tons)</option>
                                <option value="refrigerated" <?php if($vehicle_required == 'refrigerated') echo 'selected'; ?>>Refrigerated Truck (Milk/Meat)</option>
                                <option value="livestock_truck" <?php if($vehicle_required == 'livestock_truck') echo 'selected'; ?>>Livestock Carrier</option>
                            </select>
                        </div>
                        <div>
                            <label class="input-label flex justify-between items-end mb-1">
                                <span>Approximate Distance (KM)</span>
                                <button type="button" onclick="calculateAutoDistance()" class="text-xs bg-green-50 text-green-700 hover:bg-green-100 border border-green-200 px-2 py-1 rounded flex items-center gap-1 transition">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Calculate
                                </button>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-route text-gray-400"></i>
                                </div>
                                <input type="number" name="approx_distance" id="distanceField" step="0.1" class="input-field pl-10" value="<?php echo floatval($request['distance']); ?>">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="input-label">Preferred Pickup Date & Time</label>
                        <div class="relative">
                            <?php 
                                // Format the datetime for the HTML5 input
                                $formatted_date = "";
                                if(!empty($request['request_date'])) {
                                    $formatted_date = date('Y-m-d\TH:i', strtotime($request['request_date']));
                                }
                            ?>
                            <input type="datetime-local" name="preferred_datetime" class="input-field text-gray-700" value="<?php echo $formatted_date; ?>">
                        </div>
                    </div>
                </section>

                <div class="pt-6 flex flex-col sm:flex-row gap-4">
                    <button type="submit" class="w-full sm:w-auto flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition shadow-sm">
                        Save Changes
                    </button>
                    <a href="view_request.php?id=<?php echo $request_id; ?>" class="w-full sm:w-auto px-8 py-3 bg-white border border-gray-300 text-gray-700 text-center font-medium rounded-lg hover:bg-gray-50 transition decoration-none">
                        Cancel
                    </a>
                </div>

            </form>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // --- 1. Dynamic Towns Database ---
        const countyData = {
            "Uasin Gishu": ["Eldoret", "Burnt Forest", "Moiben", "Turbo", "Ziwa"],
            "Nairobi": ["Nairobi CBD", "Westlands", "Kasarani", "Embakasi", "Langata"],
            "Nakuru": ["Nakuru City", "Naivasha", "Molo", "Gilgil", "Njoro"],
            "Mombasa": ["Mombasa Island", "Nyali", "Mtwapa", "Changamwe", "Likoni"],
            "Kiambu": ["Thika", "Ruiru", "Kikuyu", "Limuru", "Githunguri"],
            "Trans Nzoia": ["Kitale", "Kiminini", "Endebess", "Cherangany"],
            "Bungoma": ["Bungoma Town", "Webuye", "Kimilili", "Chwele"]
        };

        function updateTowns(countyInputId, datalistId) {
            const selectedCounty = document.getElementById(countyInputId).value;
            const townDatalist = document.getElementById(datalistId);
            townDatalist.innerHTML = '';
            if (countyData[selectedCounty]) {
                countyData[selectedCounty].forEach(town => {
                    const option = document.createElement('option');
                    option.value = town;
                    townDatalist.appendChild(option);
                });
            }
        }

        // --- 2. Free API Auto-Distance Calculator ---
        async function calculateAutoDistance() {
            const pickupTown = document.getElementById('pickupTownInput').value;
            const deliveryTown = document.getElementById('deliveryTownInput').value;
            const distanceInput = document.getElementById('distanceField');
            
            if (!pickupTown || !deliveryTown) {
                alert("Please select both a Pickup Town and a Delivery Town first.");
                return;
            }
            distanceInput.value = "";
            distanceInput.placeholder = "Calculating...";

            try {
                const pickupRes = await fetch(`https://nominatim.openstreetmap.org/search?city=${pickupTown}&country=Kenya&format=json`);
                const pickupData = await pickupRes.json();
                const deliveryRes = await fetch(`https://nominatim.openstreetmap.org/search?city=${deliveryTown}&country=Kenya&format=json`);
                const deliveryData = await deliveryRes.json();

                if (pickupData.length > 0 && deliveryData.length > 0) {
                    const osrmRes = await fetch(`https://router.project-osrm.org/route/v1/driving/${pickupData[0].lon},${pickupData[0].lat};${deliveryData[0].lon},${deliveryData[0].lat}?overview=false`);
                    const osrmData = await osrmRes.json();
                    if (osrmData.routes && osrmData.routes.length > 0) {
                        distanceInput.value = (osrmData.routes[0].distance / 1000).toFixed(1);
                    } else {
                        distanceInput.placeholder = "Route not found";
                    }
                } else {
                    distanceInput.placeholder = "Town not found";
                }
            } catch (error) {
                distanceInput.placeholder = "Network error";
            }
        }

        // --- 3. Geolocation Function ---
        function getFarmerLocation() {
            const pinInput = document.getElementById('pickupPin');
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    pinInput.value = `${position.coords.latitude}, ${position.coords.longitude}`;
                },
                (error) => {
                    alert('Unable to retrieve your location.');
                }
            );
        }

        // --- 4. Cargo UI Logic ---
        let currentUnit = '<?php echo htmlspecialchars($unit_type); ?>';

        function updateCargoUI() {
            const cargoType = document.getElementById('cargoType').value;
            const quantityDiv = document.getElementById('quantityDiv');
            const bulkDiv = document.getElementById('bulkDiv');
            const unitLabel = document.getElementById('unitLabel');
            const amountLabel = document.getElementById('amountLabel');
            const perUnitContainer = document.getElementById('perUnitContainer');
            const unitTypeInput = document.getElementById('unitType');
            
            if (cargoType === 'bulk') {
                quantityDiv.style.display = 'none';
                bulkDiv.style.display = 'block';
                document.getElementById('bulkLabel').innerHTML = 'Total Estimated Weight (kg) <span class="text-red-500">*</span>';
                currentUnit = 'kg';
            } else if (cargoType === 'livestock') {
                quantityDiv.style.display = 'grid';
                bulkDiv.style.display = 'none';
                unitLabel.innerHTML = 'Number of Animals <span class="text-red-500">*</span>';
                perUnitContainer.style.display = 'none';
                currentUnit = 'heads';
            } else {
                quantityDiv.style.display = 'grid';
                bulkDiv.style.display = 'none';
                perUnitContainer.style.display = 'block';
                
                if (cargoType === 'crates') {
                    unitLabel.innerHTML = 'Number of Crates <span class="text-red-500">*</span>';
                    amountLabel.innerHTML = 'Weight per Crate (kg) <span class="text-red-500">*</span>';
                    currentUnit = 'kg';
                } else if (cargoType === 'liquid') {
                    unitLabel.innerHTML = 'Number of Cans <span class="text-red-500">*</span>';
                    amountLabel.innerHTML = 'Liters per Can <span class="text-red-500">*</span>';
                    currentUnit = 'liters';
                } else {
                    unitLabel.innerHTML = 'Number of Bags <span class="text-red-500">*</span>';
                    amountLabel.innerHTML = 'Weight per Bag (kg) <span class="text-red-500">*</span>';
                    currentUnit = 'kg';
                }
            }
            
            unitTypeInput.value = currentUnit;
            calculateAmount();
        }

        function calculateAmount() {
            const cargoType = document.getElementById('cargoType').value;
            let total = 0;

            if (cargoType === 'bulk') {
                total = parseFloat(document.getElementById('bulkAmount').value) || 0;
            } else if (cargoType === 'livestock') {
                total = parseFloat(document.getElementById('calcQuantity').value) || 0;
            } else {
                const quantity = parseFloat(document.getElementById('calcQuantity').value) || 0;
                const amountPerUnit = parseFloat(document.getElementById('calcAmountUnit').value) || 0;
                total = quantity * amountPerUnit;
            }
            
            document.getElementById('totalAmountDisplay').innerText = total.toLocaleString() + ' ' + currentUnit;
        }

        // Initialize UI on page load to match the current database values
        window.onload = function() {
            updateCargoUI();
            updateTowns('pickupCounty', 'pickupTownsList');
            updateTowns('deliveryCounty', 'deliveryTownsList');
        };
    </script>
</body>
</html>