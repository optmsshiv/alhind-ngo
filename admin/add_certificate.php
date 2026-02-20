<?php
session_start();
require '../config/db.php';

// Security (optional but recommended)
if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit;
}

$msg = "";

if(isset($_POST['submit'])){

    $title = trim($_POST['title']);

    $img = $_FILES['image']['name'];
    $pdf = $_FILES['pdf']['name'];

    $imgTmp = $_FILES['image']['tmp_name'];
    $pdfTmp = $_FILES['pdf']['tmp_name'];

    $imgName = time().'_'.$img;
    $pdfName = time().'_'.$pdf;

    $imgPath = "../uploads/certificates/images/".$imgName;
    $pdfPath = "../uploads/certificates/pdfs/".$pdfName;

    if(move_uploaded_file($imgTmp,$imgPath) && move_uploaded_file($pdfTmp,$pdfPath)){

        $stmt = $conn->prepare("INSERT INTO certificates (title,image,pdf) VALUES (?,?,?)");
        $stmt->bind_param("sss",$title,$imgName,$pdfName);

        if($stmt->execute()){
            $msg = "Certificate Uploaded Successfully!";
        } else {
            $msg = "Database Error!";
        }

    } else {
        $msg = "File Upload Failed!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Certificate</title>
<style>
body{font-family:Arial;background:#f4f6f9}
.form-box{
    max-width:400px;
    margin:50px auto;
    background:#fff;
    padding:25px;
    border-radius:8px;
}
input,button{
    width:100%;
    padding:10px;
    margin:8px 0;
}
button{
    background:#0a7cff;
    color:#fff;
    border:none;
}
.msg{color:green;text-align:center}
</style>
</head>

<body>

<div class="form-box">

<h3>Add New Certificate</h3>

<?php if($msg): ?>
<p class="msg"><?= $msg ?></p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">

<input type="text" name="title" placeholder="Certificate Title" required>

<label>Preview Image</label>
<input type="file" name="image" accept="image/*" required>

<label>PDF File</label>
<input type="file" name="pdf" accept="application/pdf" required>

<button name="submit">Upload</button>

</form>

</div>

</body>
</html>
