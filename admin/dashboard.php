<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$page_title = "Dashboard";

include "../includes/header.php";
include '../config/db.php';

/* ---------- DATA ---------- */

$res = $conn->query("SELECT * FROM ngo_inquiries ORDER BY id DESC");

$pending  = $conn->query("SELECT COUNT(*) c FROM ngo_inquiries WHERE status='pending'")
                ->fetch_assoc()['c'];

$approved = $conn->query("SELECT COUNT(*) c FROM ngo_inquiries WHERE status='approved'")
                ->fetch_assoc()['c'];

$rejected = $conn->query("SELECT COUNT(*) c FROM ngo_inquiries WHERE status='rejected'")
                ->fetch_assoc()['c'];
?>


<div class="admin-wrapper">

    <!-- SIDEBAR -->
    <?php include "../includes/sidebar.php"; ?>

    <!-- MAIN AREA -->
    <main class="main">

        <!-- TOPBAR -->
        <?php include "../includes/topbar.php"; ?>


        <!-- DASHBOARD CARDS -->
        <div class="cards">

            <div class="card pending">
                <i class="fa fa-clock"></i>
                <div>
                    <h4>Pending</h4>
                    <span><?= $pending ?></span>
                </div>
            </div>

            <div class="card approved">
                <i class="fa fa-check"></i>
                <div>
                    <h4>Approved</h4>
                    <span><?= $approved ?></span>
                </div>
            </div>

            <div class="card rejected">
                <i class="fa fa-times"></i>
                <div>
                    <h4>Rejected</h4>
                    <span><?= $rejected ?></span>
                </div>
            </div>

        </div>



        <!-- SEARCH + FILTER -->
        <div class="table-tools">

            <input type="text"
                   id="searchBox"
                   placeholder="Search name / email / phone">

            <select id="statusFilter">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>

        </div>



        <!-- TABLE -->
        <div class="table-wrapper">

        <table class="table">

            <thead>

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

            </thead>

            <tbody>

            <?php while ($r = $res->fetch_assoc()) { ?>

            <tr>

                <td><?= htmlspecialchars($r['name']) ?></td>

                <td><?= htmlspecialchars($r['email']) ?></td>

                <td><?= htmlspecialchars($r['phone']) ?></td>

                <td><?= htmlspecialchars($r['interest']) ?></td>

                <td><?= date("d M Y",strtotime($r['created_at'])) ?></td>

                <td>

                    <span class="status <?= $r['status'] ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>

                </td>


                <td>

                <?php if ($r['status']=="pending"){ ?>

                    <a class="btn btn-primary"
                       href="approve.php?id=<?= $r['id'] ?>">
                       Approve
                    </a>

                    <a class="btn btn-outline"
                       href="reject.php?id=<?= $r['id'] ?>">
                       Reject
                    </a>

                <?php } else { echo "-"; } ?>

                </td>



                <td>

                    <a class="btn"
                       href="view.php?id=<?= $r['id'] ?>">
                       View
                    </a>

                    <a class="btn btn-outline"
                       href="reply.php?id=<?= $r['id'] ?>">
                       Reply
                    </a>

                    <a class="btn btn-danger"
                       onclick="return confirm('Delete this record?')"
                       href="delete.php?id=<?= $r['id'] ?>">
                       Delete
                    </a>

                </td>

            </tr>

            <?php } ?>

            </tbody>

        </table>

        </div>


    </main>

</div>


<?php include "../includes/footer.php"; ?>