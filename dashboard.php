<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
header("Location: login.php");
exit();
}

$farmer_id = $_SESSION['user_id'];

/* DASHBOARD STATS */

$total_requests = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE farmer_id='$farmer_id'")->fetch_assoc()['total'];

$pending = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE farmer_id='$farmer_id' AND status='pending'")->fetch_assoc()['total'];

$accepted = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE farmer_id='$farmer_id' AND status='accepted'")->fetch_assoc()['total'];

$completed = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE farmer_id='$farmer_id' AND status='completed'")->fetch_assoc()['total'];

?>

<?php include "navbar.php"; ?>
<?php include "sidebar_farmer.php"; ?>

<style>

.content{
margin-left:220px;
margin-top:80px;
padding:30px;
}

.cards{
display:grid;
grid-template-columns:repeat(4,1fr);
gap:20px;
margin-bottom:30px;
}

.card{
background:white;
padding:25px;
border-radius:10px;
box-shadow:0 4px 10px rgba(0,0,0,0.1);
}

.card h3{
font-size:28px;
color:#27ae60;
}

.card p{
color:#777;
font-size:14px;
}

.quick-actions{
display:grid;
grid-template-columns:repeat(3,1fr);
gap:20px;
}

.action{
background:white;
padding:25px;
border-radius:10px;
box-shadow:0 4px 10px rgba(0,0,0,0.1);
text-align:center;
cursor:pointer;
transition:0.3s;
}

.action:hover{
transform:translateY(-5px);
}

.action a{
text-decoration:none;
color:#333;
font-weight:500;
}

</style>

<div class="content">

<h2>Farmer Dashboard</h2>

<br>

<div class="cards">

<div class="card">
<h3><?php echo $total_requests; ?></h3>
<p>Total Requests</p>
</div>

<div class="card">
<h3><?php echo $pending; ?></h3>
<p>Pending Requests</p>
</div>

<div class="card">
<h3><?php echo $accepted; ?></h3>
<p>Accepted Jobs</p>
</div>

<div class="card">
<h3><?php echo $completed; ?></h3>
<p>Completed Jobs</p>
</div>

</div>

<h3>Quick Actions</h3>

<br>

<div class="quick-actions">

<div class="action">
<a href="create_request.php">
📦 Create Transport Request
</a>
</div>

<div class="action">
<a href="transporters.php">
🚚 Browse Transporters
</a>
</div>

<div class="action">
<a href="my_requests.php">
📋 View My Requests
</a>
</div>

</div>

</div>