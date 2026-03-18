<?php
session_start();
include "db.php"; // Make sure this is your database connection

// Only logged-in transporters can update
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'transporter'){
    header("Location: login.php");
    exit();
}

$transporter_id = $_SESSION['user_id'];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $vehicle_type = $_POST['vehicle_type'];
    $capacity = $_POST['capacity'];
    $price_per_km = $_POST['price_per_km'];
    $phone = $_POST['phone'];
    $location = $_POST['location'];

    $stmt = $conn->prepare("UPDATE transporters SET vehicle_type=?, capacity=?, price_per_km=?, phone=?, location=? WHERE user_id=?");
    $stmt->bind_param("sidssi", $vehicle_type, $capacity, $price_per_km, $phone, $location, $transporter_id);

    if($stmt->execute()){
        // Redirect back with success message
        header("Location: transporter_dashboard.php?message=Info+updated+successfully");
        exit();
    } else {
        echo "Error updating info: " . $conn->error;
    }
} else {
    echo "Invalid request.";
}
?>