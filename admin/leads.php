<?php
include '../config/db.php';

$res = $conn->query("SELECT * FROM ngo_inquiries ORDER BY id DESC");
?>

<h2>All Leads</h2>

<table border="1" cellpadding="10">
<tr>
<th>Name</th>
<th>Email</th>
<th>Phone</th>
<th>Interest</th>
<th>Date</th>
</tr>

<?php while($r = $res->fetch_assoc()){ ?>

<tr>
<td><?php echo $r['name']; ?></td>
<td><?php echo $r['email']; ?></td>
<td><?php echo $r['phone']; ?></td>
<td><?php echo $r['interest']; ?></td>
<td><?php echo $r['created_at']; ?></td>
</tr>

<?php } ?>

</table>
