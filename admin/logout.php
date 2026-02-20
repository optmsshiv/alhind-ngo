<?php
session_start();

/* Destroy All Sessions */
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Logged Out</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>

*{
    font-family:'Poppins',sans-serif;
}

body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#3498db,#1abc9c);
}

.box{
    background:#fff;
    padding:35px 30px;
    width:100%;
    max-width:400px;
    border-radius:12px;
    text-align:center;
    box-shadow:0 15px 35px rgba(0,0,0,0.2);
    animation:fade 0.6s ease;
}

.box h2{
    color:#2c3e50;
    margin-bottom:10px;
}

.box p{
    color:#666;
    margin-bottom:20px;
}

.box a{
    display:inline-block;
    padding:10px 25px;
    background:#1abc9c;
    color:#fff;
    text-decoration:none;
    border-radius:6px;
    transition:0.3s;
}

.box a:hover{
    background:#16a085;
}

@keyframes fade{
    from{opacity:0;transform:translateY(20px);}
    to{opacity:1;transform:translateY(0);}
}

</style>
</head>

<body>

<div class="box">

    <h2>✅ Logged Out Successfully</h2>

    <p>You have been safely logged out from Admin Panel.</p>

    <a href="../index.php">Login Again</a>

</div>

</body>
</html>
