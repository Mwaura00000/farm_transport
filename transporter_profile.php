<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'transporter') {
    header("Location: login.php");
    exit;
}

$message = "";

if (isset($_POST['save'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $vehicle_type = $_POST['vehicle_type'];
    $capacity = $_POST['capacity'];
    $price_per_km = $_POST['price_per_km'];
    $user_id = $_SESSION['user_id'];

    if (empty($name) || empty($vehicle_type) || empty($capacity)) {
        $message = "Name, vehicle type, and capacity are required.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO transporters (user_id, name, phone, vehicle_type, capacity, price_per_km)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssii", $user_id, $name, $phone, $vehicle_type, $capacity, $price_per_km);

        if ($stmt->execute()) {
            header("Location: transporter_dashboard.php");
            exit;
        } else {
            $message = "Error saving transporter details.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transporter Profile</title>
</head>
<body>
<h2>Transporter Details</h2>
<?php if ($message) echo "<p>$message</p>"; ?>

<form method="POST">
    <label>Full Name</label><br>
    <input type="text" name="name" required><br><br>

    <label>Phone</label><br>
    <input type="text" name="phone"><br><br>

    <label>Vehicle Type</label><br>
    <input type="text" name="vehicle_type" required><br><br>

    <label>Capacity (kg)</label><br>
    <input type="number" name="capacity" required><br><br>

    <label>Price per Km</label><br>
    <input type="number" name="price_per_km"><br><br>

    <button type="submit" name="save">Save Details</button>
</form>
</body>
</html>