<?php

include "../config/db.php";

$id=$_GET['id'];

mysqli_query($conn,

"DELETE FROM donors WHERE id='$id'"

);

header("location:dashboard.php");