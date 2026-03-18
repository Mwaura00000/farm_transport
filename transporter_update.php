<?php
session_start();
include "db.php"; // Connect to database

// Ensure only logged-in transporters can access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'transporter'){
    header("Location: login.php");
    exit;
}

// Check if request_id and action are set
if(isset($_POST['request_id']) && isset($_POST['action'])){
    $request_id = $_POST['request_id'];
    $action = $_POST['action']; // should be 'accept' or 'reject'

    // Sanitize input
    $request_id = mysqli_real_escape_string($conn, $request_id);
    $action = mysqli_real_escape_string($conn, $action);

    // Only allow updating to accept or reject
    if($action == 'accept' || $action == 'reject'){
        // Update the request status in the database
        $sql = "UPDATE transport_requests SET status='$action' WHERE id='$request_id' AND transporter_id='".$_SESSION['user_id']."'";
        if(mysqli_query($conn, $sql)){
            // Redirect back to dashboard after update
            header("Location: transporter_dashboard.php");
            exit;
        } else {
            echo "Failed to update request status: " . mysqli_error($conn);
        }
    } else {
        echo "Invalid action!";
    }
} else {
    echo "No request selected!";
}
?>