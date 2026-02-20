<?php

include 'config/db.php';

$id=$_GET['id'];

$res=$conn->query("
SELECT * FROM events
WHERE id='$id'
");

$row=$res->fetch_assoc();

?>

<h1>

<?=$row['title']?>

</h1>

<img
src="assets/events/<?=$row['image']?>"
width="600">

<p>

<?=$row['full_desc']?>

</p>