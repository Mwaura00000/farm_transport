<?php
include "db.php";

if(isset($_GET['id']) && isset($_GET['action'])){
    $request_id = $_GET['id'];
    $action = $_GET['action'];

    if($action == 'accept' || $action == 'reject'){
        $sql = "UPDATE transport_requests SET status='$action' WHERE id='$request_id'";
        if(mysqli_query($conn, $sql)){
            header("Location: transporter_dashboard.php");
            exit;
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    } else {
        echo "Invalid action!";
    }
} else {
    echo "Request ID or action missing!";
}
?>