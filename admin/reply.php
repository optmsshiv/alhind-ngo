<?php
session_start();
include '../config/db.php';

$id=$_GET['id'];

$r=$conn->query("SELECT * FROM ngo_inquiries WHERE id=$id")->fetch_assoc();
?>

<h2>Reply to <?php echo $r['name']; ?></h2>

<form action="send.php" method="POST">

<input type="hidden" name="id" value="<?php echo $id; ?>">

<textarea name="reply" rows="6" style="width:100%;"></textarea>

<br><br>

<button class="btn btn-primary">Send Reply</button>

</form>
