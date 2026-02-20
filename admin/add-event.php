<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['admin'])){
header("Location:login.php");
exit;
}

if(isset($_POST['submit'])){

$title=$_POST['title'];

$slug=strtolower(
preg_replace('/[^A-Za-z0-9-]+/', '-',$title)
);

$date=$_POST['date'];

$location=$_POST['location'];

$short=$_POST['short'];

$full=$_POST['full'];

$link=$_POST['register'];

$status=$_POST['status'];

$image=$_FILES['image']['name'];

move_uploaded_file(
$_FILES['image']['tmp_name'],
"../assets/events/".$image
);

$conn->query("INSERT INTO events
(slug,title,event_date,location,image,
short_desc,full_desc,register_link,status)

VALUES(

'$slug',
'$title',
'$date',
'$location',
'$image',
'$short',
'$full',
'$link',
'$status'

)");

echo "Event Published";

}
?>