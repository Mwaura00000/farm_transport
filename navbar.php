<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}
?>

<style>

.navbar{
position:fixed;
top:0;
left:0;
width:100%;
height:60px;
background:#27ae60;
display:flex;
align-items:center;
justify-content:space-between;
padding:0 25px;
color:white;
z-index:1000;
box-shadow:0 2px 8px rgba(0,0,0,0.15);
}

.logo{
font-size:20px;
font-weight:600;
}

.nav-right{
display:flex;
align-items:center;
gap:20px;
}

.nav-icon{
cursor:pointer;
font-size:18px;
}

.user{
background:rgba(255,255,255,0.2);
padding:6px 12px;
border-radius:20px;
}

</style>

<div class="navbar">

<div class="logo">
🚜 AgriMove
</div>

<div class="nav-right">

<div class="nav-icon">🔔</div>

<div class="nav-icon">✉️</div>

<div class="user">
<?php echo $_SESSION['name'] ?? 'User'; ?>
</div>

</div>

</div>