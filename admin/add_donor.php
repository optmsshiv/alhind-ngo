<?php
include "../config/db.php";

if(isset($_POST['submit'])){

$name=$_POST['name'];
$amount=$_POST['amount'];
$badge=$_POST['badge'];

$photo="";

if(!empty($_FILES['photo']['name'])){

$photo=time().$_FILES['photo']['name'];

move_uploaded_file(

$_FILES['photo']['tmp_name'],

"../uploads/".$photo

);

}

mysqli_query($conn,

"INSERT INTO donors(name,photo,amount,badge)

VALUES('$name','$photo','$amount','$badge')"

);

echo "Donor Added";

}
?>

<form method="POST" enctype="multipart/form-data">

Name

<input type="text" name="name" required>

Amount Sponsored (Optional)

<input type="text" name="amount">

Badge

<select name="badge">

<option value="gold">Gold</option>

<option value="platinum">Platinum</option>

</select>

Photo

<input type="file" name="photo">

<button name="submit">Add Donor</button>

</form>