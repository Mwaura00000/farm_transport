<?php
session_start();
include "db.php"; // Connect to database

// Ensure farmer is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer'){
    header("Location: login.php");
    exit;
}

// Check if form is submitted
if(isset($_POST['transporter_id'], $_POST['farmer_id'], $_POST['pickup'], $_POST['destination'], $_POST['distance'])){

    $transporter_id = mysqli_real_escape_string($conn, $_POST['transporter_id']);
    $farmer_id = mysqli_real_escape_string($conn, $_POST['farmer_id']);
    $pickup = mysqli_real_escape_string($conn, $_POST['pickup']);
    $destination = mysqli_real_escape_string($conn, $_POST['destination']);
    $distance = mysqli_real_escape_string($conn, $_POST['distance']);

    // Get transporter price_per_km
    $sql_trans = "SELECT price_per_km FROM transporters WHERE id='$transporter_id'";
    $res_trans = mysqli_query($conn, $sql_trans);
    if(mysqli_num_rows($res_trans) == 1){
        $trans = mysqli_fetch_assoc($res_trans);
        $total_price = $distance * $trans['price_per_km'];

        // Insert new transport request
        $sql_insert = "INSERT INTO transport_requests (farmer_id, transporter_id, pickup_location, destination, distance, total_price, status) 
                       VALUES ('$farmer_id', '$transporter_id', '$pickup', '$destination', '$distance', '$total_price', 'pending')";
        if(mysqli_query($conn, $sql_insert)){
            // Redirect back to dashboard
            header("Location: farmer_dashboard.php");
            exit;
        } else {
            echo "Error submitting request: " . mysqli_error($conn);
        }
    } else {
        echo "Transporter not found!";
    }

} else {
    echo "All fields are required!";
}
?>