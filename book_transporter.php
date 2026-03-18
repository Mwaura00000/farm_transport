<?php
session_start();
include("db_connect.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $farmer_id = $_SESSION['user_id'];
    $transporter_id = $_POST['transporter_id'];
    $distance = $_POST['distance'];
    $pickup = $_POST['pickup_location'] ?? '';
    $destination = $_POST['destination_location'] ?? '';
    $cargo_type = $_POST['cargo_type'] ?? '';

    // Insert booking into database
    $stmt = $conn->prepare("INSERT INTO bookings (farmer_id, transporter_id, pickup_location, destination_location, distance, cargo_type, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iissds", $farmer_id, $transporter_id, $pickup, $destination, $distance, $cargo_type);

    if($stmt->execute()){
        // Booking successful
        header("Location: farmer_dashboard.php?message=Booking+sent+successfully");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>