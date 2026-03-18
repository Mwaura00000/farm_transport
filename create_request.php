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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Grab Produce Details
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
    
    // 2. Grab Transport Details
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
            // STEP 1: Insert into `produce` table
            $produce_desc = "Vehicle Req: $vehicle_required | Labor: $loading_labor | Unit: $unit_type";
            $sql_produce = "INSERT INTO produce (farmer_id, name, quantity, weight, description) VALUES (?, ?, ?, ?, ?)";
            $stmt1 = mysqli_prepare($conn, $sql_produce);
            mysqli_stmt_bind_param($stmt1, "isdds", $farmer_id, $produce_type, $quantity, $total_amount, $produce_desc);
            mysqli_stmt_execute($stmt1);
            
            $produce_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt1);

            // STEP 2: Insert into `transport_requests` table
            $sql_transport = "INSERT INTO transport_requests 
                (farmer_id, produce_id, cargo_type, pickup_location, pickup_county, pickup_town, pickup_exact_address, pickup_description, 
                 destination_location, delivery_county, delivery_town, delivery_exact_address, emergency_contact_name, emergency_contact_phone, distance, request_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt2 = mysqli_prepare($conn, $sql_transport);
            mysqli_stmt_bind_param($stmt2, "iissssssssssssds", 
                $farmer_id, $produce_id, $cargo_type, $pickup_pin, $pickup_county, $pickup_town, $pickup_village, $road_condition,
                $delivery_pin, $delivery_county, $delivery_town, $delivery_address, $contact_name, $contact_phone, $approx_distance, $preferred_datetime
            );
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            mysqli_commit($conn);
            $success_msg = "Transport request submitted successfully! Transporters will be notified.";

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Transport Request - AgriMove</title>
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

    <nav class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center sticky top-0 z-10">
        <div class="flex items-center gap-2 text-green-600 text-xl font-bold">
            <i class="fa-solid fa-truck-fast"></i> AgriMove
        </div>
        <a href="farmer_dashboard.php" class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition text-gray-700 decoration-none">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </nav>

    <main class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
            
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Create Transport Request</h1>
                <p class="text-gray-500">Fill in the details below and we'll connect you with a trusted transporter</p>
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

            <form action="create_request.php" method="POST" class="space-y-8">
                <input type="hidden" name="unit_type" id="unitType" value="kg">

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
                            <input list="produceSuggestions" name="produce_type" class="input-field" placeholder="Type or select produce..." required>
                            <datalist id="produceSuggestions">
                                <option value="Maize"><option value="Potatoes"><option value="Cabbage"><option value="Fresh Milk"><option value="Live Chickens"><option value="Cows / Cattle"><option value="Tomatoes"><option value="Bananas">
                            </datalist>
                        </div>
                        <div>
                            <label class="input-label">Cargo Type <span class="text-red-500">*</span></label>
                            <select name="cargo_type" id="cargoType" class="input-field text-gray-700" onchange="updateCargoUI()" required>
                                <option value="">Select cargo type</option>
                                <option value="sacks">Sacks/Bags (Kg)</option>
                                <option value="crates">Crates/Boxes (Kg)</option>
                                <option value="liquid">Containers/Cans (Liters)</option>
                                <option value="livestock">Livestock (Heads)</option>
                                <option value="bulk">Bulk/Loose (Kg)</option>
                            </select>
                        </div>
                    </div>

                    <div id="quantityDiv" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label id="unitLabel" class="input-label">Number of Bags <span class="text-red-500">*</span></label>
                            <input type="number" name="quantity" id="calcQuantity" class="input-field" placeholder="e.g. 40" oninput="calculateAmount()">
                        </div>
                        <div id="perUnitContainer">
                            <label id="amountLabel" class="input-label">Weight per Bag (kg) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.1" name="amount_per_unit" id="calcAmountUnit" class="input-field" placeholder="e.g. 90" oninput="calculateAmount()">
                        </div>
                    </div>

                    <div id="bulkDiv" class="mt-6" style="display: none;">
                        <label id="bulkLabel" class="input-label">Total Estimated Weight (kg) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.1" name="total_bulk_amount" id="bulkAmount" class="input-field" placeholder="e.g. 5000" oninput="calculateAmount()">
                    </div>

                    <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-calculator text-green-600"></i>
                            <span class="text-gray-600">Total Amount:</span>
                            <span class="font-bold text-lg text-gray-900" id="totalAmountDisplay">0 kg</span>
                        </div>
                        <div class="hidden sm:block text-green-300">|</div>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-600">Suggested Vehicle:</span>
                            <span class="font-semibold text-gray-900" id="suggestedVehicle">-</span>
                            <i class="fa-solid fa-truck text-green-600 ml-1"></i>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label class="input-label">Who Provides Loading Labor? <span class="text-red-500">*</span></label>
                        <select name="loading_labor" class="input-field text-gray-700 mb-1" required>
                            <option value="">Select option</option>
                            <option value="farmer">Farmer (I will provide)</option>
                            <option value="driver">Driver/Transporter</option>
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
                            <input list="kenyaCounties" name="pickup_county" id="pickupCounty" class="input-field" placeholder="Type to search county..." onchange="updateTowns('pickupCounty', 'pickupTownsList')" required>
                        </div>
                        <div>
                            <label class="input-label">Town <span class="text-red-500">*</span></label>
                            <input list="pickupTownsList" name="pickup_town" id="pickupTownInput" class="input-field" placeholder="Select or type town..." required>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <label class="input-label">Village / Landmark</label>
                            <input type="text" name="pickup_village" class="input-field" placeholder="e.g. Near Kimathi Market">
                        </div>
                        <div>
                            <div class="flex justify-between items-end mb-1">
                                <label class="input-label mb-0 flex items-center gap-1">GPS Coordinates / Google Maps Pin</label>
                                <button type="button" onclick="getFarmerLocation()" class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-3 py-1 rounded flex items-center gap-1 transition">
                                    <i class="fa-solid fa-location-crosshairs"></i> Get My Location
                                </button>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-map-pin text-gray-400"></i>
                                </div>
                                <input type="text" name="pickup_pin" id="pickupPin" class="input-field pl-10" placeholder="e.g. 0.5143, 35.2698">
                            </div>
                        </div>
                        <div>
                            <label class="input-label">Road Condition</label>
                            <select name="road_condition" class="input-field text-gray-700">
                                <option value="">Select road condition</option>
                                <option value="tarmac">Tarmac (Good)</option>
                                <option value="murram">Murram (Fair)</option>
                                <option value="rough">Rough/Muddy (Requires 4x4)</option>
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
                            <input list="kenyaCounties" name="delivery_county" id="deliveryCounty" class="input-field" placeholder="Type to search county..." onchange="updateTowns('deliveryCounty', 'deliveryTownsList')" required>
                        </div>
                        <div>
                            <label class="input-label">Town <span class="text-red-500">*</span></label>
                            <input list="deliveryTownsList" name="delivery_town" id="deliveryTownInput" class="input-field" placeholder="Select or type town..." required>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <label class="input-label">Delivery Address / Market <span class="text-red-500">*</span></label>
                            <input list="marketSuggestions" name="delivery_address" class="input-field" placeholder="e.g. Wakulima Market, Stall 45" required>
                        </div>
                        
                        <div>
                            <label class="input-label">Delivery GPS Coordinates / Google Maps Link</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-map-location text-gray-400"></i>
                                </div>
                                <input type="text" name="delivery_pin" class="input-field pl-10" placeholder="Paste link shared by the buyer">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="input-label">Contact Name</label>
                                <input type="text" name="contact_name" class="input-field" placeholder="Receiver's name">
                            </div>
                            <div>
                                <label class="input-label">Contact Phone</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-phone text-gray-400"></i>
                                    </div>
                                    <input type="tel" name="contact_phone" class="input-field pl-10" placeholder="07XX XXX XXX">
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
                                <option value="">Suggested: -</option>
                                <option value="pickup">Pickup (up to 1 Ton)</option>
                                <option value="canter">Canter (3-5 Tons)</option>
                                <option value="lorry">Lorry (10+ Tons)</option>
                                <option value="refrigerated">Refrigerated Truck (Milk/Meat)</option>
                                <option value="livestock_truck">Livestock Carrier</option>
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
                                <input type="number" name="approx_distance" id="distanceField" step="0.1" class="input-field pl-10" placeholder="e.g. 120">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="input-label">Preferred Pickup Date & Time</label>
                        <div class="relative">
                            <input type="datetime-local" name="preferred_datetime" class="input-field text-gray-700">
                        </div>
                    </div>
                </section>

                <div class="pt-6 flex flex-col sm:flex-row gap-4">
                    <button type="submit" class="w-full sm:w-auto flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition shadow-sm">
                        Submit Transport Request
                    </button>
                    <a href="farmer_dashboard.php" class="w-full sm:w-auto px-8 py-3 bg-white border border-gray-300 text-gray-700 text-center font-medium rounded-lg hover:bg-gray-50 transition decoration-none">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </main>

    <script>
       // --- 1. Dynamic Towns Database (All 47 Counties) ---
        const countyData = {
            "Mombasa": ["Mombasa Island", "Nyali", "Mtwapa", "Changamwe", "Likoni"],
            "Kwale": ["Kwale Town", "Ukunda", "Diani", "Msambweni", "Kinango"],
            "Kilifi": ["Kilifi Town", "Malindi", "Mtwapa", "Mariakani", "Watamu", "Kaloleni"],
            "Tana River": ["Hola", "Garsen", "Bura", "Madogo", "Kipini"],
            "Lamu": ["Lamu Town", "Mpeketoni", "Hindi", "Faza", "Kiunga"],
            "Taita Taveta": ["Voi", "Taveta", "Wundanyi", "Mwatate", "Bura"],
            "Garissa": ["Garissa Town", "Masalani", "Dadaab", "Modogashe", "Bura"],
            "Wajir": ["Wajir Town", "Habaswein", "Griftu", "Tarbaj", "Bute"],
            "Mandera": ["Mandera Town", "Elwak", "Rhamu", "Takaba", "Banisa"],
            "Marsabit": ["Marsabit Town", "Moyale", "Laisamis", "North Horr", "Loiyangalani"],
            "Isiolo": ["Isiolo Town", "Garba Tula", "Merti", "Kinna", "Oldonyiro"],
            "Meru": ["Meru Town", "Maua", "Nkubu", "Timau", "Makutano"],
            "Tharaka Nithi": ["Chuka", "Chogoria", "Marimanti", "Kathwana", "Kibugua"],
            "Embu": ["Embu Town", "Runyenjes", "Ishiara", "Kiritiri", "Siakago"],
            "Kitui": ["Kitui Town", "Mwingi", "Mutomo", "Kyuso", "Kwa Vonza"],
            "Machakos": ["Machakos Town", "Athi River", "Kangundo", "Tala", "Mlolongo"],
            "Makueni": ["Wote", "Makindu", "Mtito Andei", "Kibwezi", "Emali"],
            "Nyandarua": ["Ol Kalou", "Engineer", "Njabini", "Mairo-Inya", "Ndaragwa"],
            "Nyeri": ["Nyeri Town", "Karatina", "Othaya", "Mukurwe-ini", "Mweiga"],
            "Kirinyaga": ["Kerugoya", "Kutus", "Wanguru (Mwea)", "Sagana", "Kagio"],
            "Murang'a": ["Murang'a Town", "Kenol", "Kangema", "Maragua", "Makuyu"],
            "Kiambu": ["Thika", "Ruiru", "Kikuyu", "Limuru", "Githunguri", "Kiambu Town"],
            "Turkana": ["Lodwar", "Kakuma", "Lokichogio", "Lokichar", "Kainuk"],
            "West Pokot": ["Kapenguria", "Makutano", "Chepareria", "Ortum", "Sigor"],
            "Samburu": ["Maralal", "Baragoi", "Wamba", "Archers Post", "Suguta Marmar"],
            "Trans Nzoia": ["Kitale", "Kiminini", "Endebess", "Cherangany", "Saboti"],
            "Uasin Gishu": ["Eldoret", "Burnt Forest", "Moiben", "Turbo", "Ziwa"],
            "Elgeyo Marakwet": ["Iten", "Kapsowar", "Chepkorio", "Kapcherop", "Tambach"],
            "Nandi": ["Kapsabet", "Nandi Hills", "Mosoriot", "Lessos", "Kobujoi"],
            "Baringo": ["Kabarnet", "Eldama Ravine", "Marigat", "Mogotio", "Chemolingot"],
            "Laikipia": ["Nanyuki", "Nyahururu", "Rumuruti", "Kinamba", "Wiyumiririe"],
            "Nakuru": ["Nakuru City", "Naivasha", "Molo", "Gilgil", "Njoro", "Egerton"],
            "Narok": ["Narok Town", "Kilgoris", "Ololulung'a", "Mulot", "Nairagie Enkare"],
            "Kajiado": ["Kajiado Town", "Kitengela", "Ngong", "Ongata Rongai", "Loitokitok"],
            "Kericho": ["Kericho Town", "Litein", "Kipkelion", "Londiani", "Sosiot"],
            "Bomet": ["Bomet Town", "Sotik", "Silibwet", "Longisa", "Ndaraweta"],
            "Kakamega": ["Kakamega Town", "Mumias", "Malava", "Butere", "Khayega"],
            "Vihiga": ["Mbale", "Chavakali", "Luanda", "Majengo", "Kaimosi"],
            "Bungoma": ["Bungoma Town", "Webuye", "Kimilili", "Chwele", "Sirisia"],
            "Busia": ["Busia Town", "Malaba", "Nambale", "Port Victoria", "Bumala"],
            "Siaya": ["Siaya Town", "Bondo", "Ugunja", "Yala", "Ukwala"],
            "Kisumu": ["Kisumu City", "Maseno", "Ahero", "Muhoroni", "Katito"],
            "Homa Bay": ["Homa Bay Town", "Kendu Bay", "Mbita", "Oyugis", "Ndhiwa"],
            "Migori": ["Migori Town", "Awendo", "Rongo", "Isebania", "Kehancha"],
            "Kisii": ["Kisii Town", "Ogembo", "Suneka", "Keroka", "Nyansiongo"],
            "Nyamira": ["Nyamira Town", "Nyansiongo", "Keroka", "Magwagwa", "Ikonge"],
            "Nairobi": ["Nairobi CBD", "Westlands", "Kasarani", "Embakasi", "Langata", "Roysambu", "Karen"]
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
                // Step A: Get coordinates for Pickup Town using OpenStreetMap (Free API)
                const pickupRes = await fetch(`https://nominatim.openstreetmap.org/search?city=${pickupTown}&country=Kenya&format=json`);
                const pickupData = await pickupRes.json();
                
                // Step B: Get coordinates for Delivery Town
                const deliveryRes = await fetch(`https://nominatim.openstreetmap.org/search?city=${deliveryTown}&country=Kenya&format=json`);
                const deliveryData = await deliveryRes.json();

                if (pickupData.length > 0 && deliveryData.length > 0) {
                    const pLat = pickupData[0].lat;
                    const pLon = pickupData[0].lon;
                    const dLat = deliveryData[0].lat;
                    const dLon = deliveryData[0].lon;

                    // Step C: Get road driving distance using OSRM (Free Routing API)
                    const osrmRes = await fetch(`https://router.project-osrm.org/route/v1/driving/${pLon},${pLat};${dLon},${dLat}?overview=false`);
                    const osrmData = await osrmRes.json();

                    if (osrmData.routes && osrmData.routes.length > 0) {
                        const distanceKm = (osrmData.routes[0].distance / 1000).toFixed(1);
                        distanceInput.value = distanceKm; // Placed directly into the input box!
                    } else {
                        distanceInput.placeholder = "Route not found, enter manually";
                    }
                } else {
                    distanceInput.placeholder = "Town not found on map, enter manually";
                }
            } catch (error) {
                console.error("API Error:", error);
                distanceInput.placeholder = "Network error, enter manually";
            }
        }

        // --- 3. Geolocation Function ---
        function getFarmerLocation() {
            const statusText = document.getElementById('geoStatus');
            const pinInput = document.getElementById('pickupPin');

            if (!navigator.geolocation) {
                statusText.innerText = 'Geolocation is not supported by your browser.';
                statusText.className = "text-xs text-red-500 mt-1";
                return;
            }

            statusText.innerText = 'Locating... Please wait and allow permissions.';
            statusText.className = "text-xs text-blue-500 mt-1 font-medium";

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;
                    pinInput.value = `${latitude}, ${longitude}`;
                    statusText.innerHTML = '<i class="fa-solid fa-check text-green-500"></i> Location captured successfully!';
                    statusText.className = "text-xs text-green-600 mt-1 font-medium";
                },
                (error) => {
                    statusText.innerText = 'Unable to retrieve your location. Please check your browser permissions.';
                    statusText.className = "text-xs text-red-500 mt-1";
                }
            );
        }

        // --- 4. Cargo UI Logic ---
        let currentUnit = 'kg';

        function updateCargoUI() {
            const cargoType = document.getElementById('cargoType').value;
            const quantityDiv = document.getElementById('quantityDiv');
            const bulkDiv = document.getElementById('bulkDiv');
            const unitLabel = document.getElementById('unitLabel');
            const amountLabel = document.getElementById('amountLabel');
            const perUnitContainer = document.getElementById('perUnitContainer');
            const unitTypeInput = document.getElementById('unitType');
            
            document.getElementById('calcQuantity').value = '';
            document.getElementById('calcAmountUnit').value = '';
            document.getElementById('bulkAmount').value = '';

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
            
            let suggested = "-";
            let vehicleValue = "";
            
            if (cargoType === 'liquid') {
                suggested = "Pickup / Refrigerated";
                vehicleValue = "refrigerated";
            } else if (cargoType === 'livestock') {
                suggested = "Livestock Carrier";
                vehicleValue = "livestock_truck";
            } else {
                if (total > 0 && total <= 1500) { suggested = "Pickup"; vehicleValue = "pickup"; }
                else if (total > 1500 && total <= 5000) { suggested = "Canter"; vehicleValue = "canter"; }
                else if (total > 5000) { suggested = "Lorry"; vehicleValue = "lorry"; }
            }
            
            document.getElementById('suggestedVehicle').innerText = suggested;
            
            const vehicleSelect = document.getElementById('vehicleSelect');
            vehicleSelect.options[0].text = "Suggested: " + suggested;
            if(vehicleValue !== "") {
                vehicleSelect.value = vehicleValue;
            }
        }
    </script>
</body>
</html>