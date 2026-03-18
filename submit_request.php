<?php
session_start();
include "db_connect.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $farmer_id = $_SESSION['user_id'];
    $transporter_id = $_POST['transporter_id'] ?? null;
    $pickup = $_POST['pickup_location'];
    $destination = $_POST['destination_location'];
    $distance = $_POST['distance'];
    $cargo_type = $_POST['cargo_type'];

    $stmt = $conn->prepare("INSERT INTO bookings (farmer_id, transporter_id, pickup_location, destination_location, distance, cargo_type, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iissds", $farmer_id, $transporter_id, $pickup, $destination, $distance, $cargo_type);

    if($stmt->execute()){
        header("Location: farmer_dashboard.php?message=Request+submitted+successfully");
        exit();
    } else {
        echo "Error submitting request: " . $conn->error;
    }
} else {
    echo "Invalid request.";
}
?>