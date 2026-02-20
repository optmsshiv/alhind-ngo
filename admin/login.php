<?php
session_start();

if(isset($_POST['user'])){

    $u = $_POST['user'];
    $p = $_POST['pass'];

    /* HARD SIMPLE LOGIN — LATER YOU CAN MOVE TO MYSQL */
    if($u=="admin" && $p=="9263"){
        $_SESSION['admin']=true;
        header("Location: dashboard.php");
        exit;
    }else{
        $err="❌ Invalid Username or Password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NGO Admin Login</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>

*{
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

/* Background */
body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background: linear-gradient(135deg,#1abc9c,#3498db);
}

/* Login Card */
.login-box{
    width:100%;
    max-width:420px;
    background:#fff;
    padding:35px 30px;
    border-radius:12px;
    box-shadow:0 15px 40px rgba(0,0,0,0.2);
    animation:fadeIn 0.7s ease;
}

/* Logo */
/* Circular Logo */
.login-box img{
    width:90px;
    height:90px;
    display:block;
    margin:0 auto 15px;

    border-radius:50%;
    object-fit:cover;

    border:3px solid #1abc9c;
    padding:3px;
    background:#fff;

    box-shadow:0 4px 10px rgba(0,0,0,0.15);
}

.login-box img:hover{
    transform:scale(1.05);
    transition:0.3s;
}



/* Title */
.login-box h2{
    text-align:center;
    margin-bottom:25px;
    color:#2c3e50;
}

/* Input */
.login-box input{
    width:100%;
    padding:12px 15px;
    border:1px solid #ddd;
    border-radius:6px;
    font-size:15px;
    margin-bottom:15px;
    transition:0.3s;
}

.login-box input:focus{
    border-color:#1abc9c;
    outline:none;
}

/* Button */
.login-box button{
    width:100%;
    padding:12px;
    border:none;
    background:#1abc9c;
    color:#fff;
    font-size:16px;
    border-radius:6px;
    cursor:pointer;
    transition:0.3s;
}

.login-box button:hover{
    background:#16a085;
}

/* Button Loader */
#loginBtn{
    position:relative;
    overflow:hidden;
}

/* Spinner */
#loginBtn .loader{
    width:20px;
    height:20px;
    border:3px solid rgba(255,255,255,0.3);
    border-top:3px solid #fff;
    border-radius:50%;
    position:absolute;
    top:50%;
    left:50%;
    transform:translate(-50%,-50%);
    display:none;
    animation:spin 1s linear infinite;
}

/* Hide Text When Loading */
#loginBtn.loading .btn-text{
    visibility:hidden;
}

#loginBtn.loading .loader{
    display:block;
}

@keyframes spin{
    0%{transform:translate(-50%,-50%) rotate(0deg);}
    100%{transform:translate(-50%,-50%) rotate(360deg);}
}


/* Error */
.error{
    background:#ffe6e6;
    color:#c0392b;
    padding:10px;
    border-radius:5px;
    margin-bottom:15px;
    text-align:center;
    font-size:14px;
}

/* Footer */
.footer-text{
    text-align:center;
    margin-top:18px;
    font-size:13px;
    color:#777;
}

/* Animation */
@keyframes fadeIn{
    from{
        opacity:0;
        transform:translateY(20px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}

</style>
</head>

<body>

<div class="login-box">

    <!-- NGO Logo (Optional) -->
    <!-- Replace logo.png with your NGO logo -->
    <img src="../assets/logo.jpeg" alt="NGO Logo">

    <h2>NGO Admin Panel</h2>

    <?php if(isset($err)){ ?>
        <div class="error"><?php echo $err; ?></div>
    <?php } ?>

    <form method="POST">

        <input type="text" name="user" placeholder="Admin Username" required>

        <input type="password" name="pass" placeholder="Password" required>

        <button type="submit" id="loginBtn">
                <span class="btn-text">Login</span>
                <span class="loader"></span>
        </button>


    </form>

    <div class="footer-text">
        © <?php echo date("Y"); ?> Al Hind Educational and Cheritable Trust<br>
        Secure Admin Access
    </div>

</div>

<!-- JavaScript for Button Loading Effect -->
<script>
document.querySelector("form").addEventListener("submit", function(){

    const btn = document.getElementById("loginBtn");

    btn.classList.add("loading");
    btn.disabled = true;

});
</script>


</body>
</html>
