<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

$res = $conn->query("SELECT * FROM ngo_inquiries ORDER BY id DESC");

$pending  = $conn->query("SELECT COUNT(*) c FROM ngo_inquiries WHERE status='pending'")->fetch_assoc()['c'];
$approved = $conn->query("SELECT COUNT(*) c FROM ngo_inquiries WHERE status='approved'")->fetch_assoc()['c'];
$rejected = $conn->query("SELECT COUNT(*) c FROM ngo_inquiries WHERE status='rejected'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Admin CSS -->
    <link rel="stylesheet" href="/css/admin-style.css">
</head>
<body>

<div class="admin-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <h2 class="logo">
            <i class="fa-solid fa-hand-holding-heart"></i> NGO Admin
        </h2>

        <ul>
            <li class="active"><i class="fa fa-users"></i> Join Leads</li>
            <li><i class="fa fa-envelope"></i> Messages</li>
            <li><i class="fa fa-calendar"></i> Events</li>
            <li><i class="fa fa-gear"></i> Settings</li>
            <li><a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <!-- MAIN -->
    <main class="main">

        <!-- TOPBAR -->
        <div class="topbar">
            <h3>Join Leads Dashboard</h3>
            <span class="admin-name"><i class="fa fa-user"></i> Admin</span>
        </div>

        <!-- CARDS -->
        <div class="cards">
            <div class="card pending">
                <i class="fa fa-clock"></i>
                <h4>Pending</h4>
                <span><?= $pending ?></span>
            </div>

            <div class="card approved">
                <i class="fa fa-check"></i>
                <h4>Approved</h4>
                <span><?= $approved ?></span>
            </div>

            <div class="card rejected">
                <i class="fa fa-times"></i>
                <h4>Rejected</h4>
                <span><?= $rejected ?></span>
            </div>
        </div>

        <!-- TOOLS -->
        <div class="table-tools">
            <input type="text" id="searchBox" placeholder="Search name / email / phone">
            <select id="statusFilter">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <!-- TABLE -->
        <table class="table">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Interest</th>
                <th>Date</th>
                <th>Status</th>
                <th>Approval</th>
                <th>Action</th>
            </tr>

            <?php while ($r = $res->fetch_assoc()) { ?>
            <tr>
                <td><?= $r['name'] ?></td>
                <td><?= $r['email'] ?></td>
                <td><?= $r['phone'] ?></td>
                <td><?= $r['interest'] ?></td>
                <td><?= $r['created_at'] ?></td>
                <td>
                    <span class="status <?= $r['status'] ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>

                <td>
                    <?php if ($r['status'] == 'pending') { ?>
                        <a class="btn btn-primary" href="approve.php?id=<?= $r['id'] ?>">Approve</a>
                        <a class="btn btn-outline" href="reject.php?id=<?= $r['id'] ?>">Reject</a>
                    <?php } else { echo '-'; } ?>
                </td>

                <td>
                    <a class="btn" href="view.php?id=<?= $r['id'] ?>">View</a>
                    <a class="btn btn-outline" href="reply.php?id=<?= $r['id'] ?>">Reply</a>
                    <a class="btn btn-danger"
                       onclick="return confirm('Delete this record?')"
                       href="delete.php?id=<?= $r['id'] ?>">
                       Delete
                    </a>
                </td>
            </tr>
            <?php } ?>
        </table>

    </main>
</div>

</body>
</html>
