<?php
// ============================================================
//  debug-verify.php — TEMPORARY DEBUG FILE
//  Upload to /backend/ and open in browser ONCE after a test payment
//  DELETE THIS FILE after debugging is done!
// ============================================================
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

echo "<h2>AL Hind — DB Debug Panel</h2>";
echo "<p><b>PHP Time (IST):</b> " . date('Y-m-d H:i:s') . "</p>";

try {
    $pdo = getDB();

    // Check MySQL timezone
    $tz = $pdo->query("SELECT @@global.time_zone, @@session.time_zone, NOW() as mysql_now")->fetch();
    echo "<p><b>MySQL global timezone:</b> " . $tz['@@global.time_zone'] . "</p>";
    echo "<p><b>MySQL session timezone:</b> " . $tz['@@session.time_zone'] . "</p>";
    echo "<p><b>MySQL NOW():</b> " . $tz['mysql_now'] . "</p>";

    // Fix session timezone
    $pdo->exec("SET time_zone = '+05:30'");
    $tz2 = $pdo->query("SELECT NOW() as mysql_now_after_fix")->fetch();
    echo "<p><b>MySQL NOW() after SET timezone:</b> " . $tz2['mysql_now_after_fix'] . "</p>";

    echo "<hr>";

    // Show all donations with their order IDs
    $rows = $pdo->query("SELECT id, donor_name, amount, payment_status, razorpay_order_id, razorpay_payment_id, created_at FROM donations ORDER BY id DESC LIMIT 10")->fetchAll();

    echo "<h3>Last 10 Donations in DB:</h3>";
    echo "<table border='1' cellpadding='6' style='border-collapse:collapse;font-size:13px'>";
    echo "<tr><th>ID</th><th>Name</th><th>Amount</th><th>Status</th><th>Order ID</th><th>Payment ID</th><th>Created At</th></tr>";
    foreach ($rows as $r) {
        $statusColor = $r['payment_status'] === 'paid' ? 'green' : 'orange';
        echo "<tr>";
        echo "<td>{$r['id']}</td>";
        echo "<td>" . htmlspecialchars($r['donor_name']) . "</td>";
        echo "<td>₹{$r['amount']}</td>";
        echo "<td style='color:{$statusColor};font-weight:bold'>{$r['payment_status']}</td>";
        echo "<td style='font-size:11px'>" . htmlspecialchars($r['razorpay_order_id'] ?? '—') . "</td>";
        echo "<td style='font-size:11px'>" . htmlspecialchars($r['razorpay_payment_id'] ?? '—') . "</td>";
        echo "<td>{$r['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Test: manually simulate what verify-payment.php does
    // Grab the most recent pending row and test UPDATE
    $pending = $pdo->query("SELECT * FROM donations WHERE payment_status='pending' ORDER BY id DESC LIMIT 1")->fetch();

    echo "<hr><h3>Test UPDATE on most recent pending row:</h3>";
    if ($pending) {
        echo "<p>Found pending row ID: {$pending['id']}, Order ID: {$pending['razorpay_order_id']}</p>";

        $nowIST = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE donations
            SET
                payment_status      = 'paid',
                payment_method      = 'TestMethod',
                razorpay_payment_id = 'pay_TEST_DEBUG_123',
                razorpay_signature  = 'test_sig',
                updated_at          = :now
            WHERE razorpay_order_id = :order_id
              AND payment_status    = 'pending'
        ");
        $stmt->execute([
            ':now'      => $nowIST,
            ':order_id' => $pending['razorpay_order_id'],
        ]);

        $affected = $stmt->rowCount();
        echo "<p style='color:" . ($affected > 0 ? 'green' : 'red') . ";font-weight:bold'>";
        echo "Rows updated: {$affected}";
        echo $affected > 0 ? " ✅ UPDATE WORKS!" : " ❌ UPDATE FAILED — order_id mismatch or PDO issue";
        echo "</p>";

        if ($affected > 0) {
            // Rollback test change
            $pdo->prepare("UPDATE donations SET payment_status='pending', razorpay_payment_id=NULL, razorpay_signature=NULL WHERE id=?")
                ->execute([$pending['id']]);
            echo "<p style='color:gray'>(Test change rolled back — row restored to pending)</p>";
        }
    } else {
        echo "<p>No pending rows found to test.</p>";
    }

    // Check for duplicate order_ids (common cause of silent failures)
    echo "<hr><h3>Duplicate Order ID Check:</h3>";
    $dups = $pdo->query("
        SELECT razorpay_order_id, COUNT(*) as cnt
        FROM donations
        WHERE razorpay_order_id IS NOT NULL AND razorpay_order_id != ''
        GROUP BY razorpay_order_id
        HAVING cnt > 1
    ")->fetchAll();

    if ($dups) {
        echo "<p style='color:red'>⚠ Found duplicate order IDs:</p><ul>";
        foreach ($dups as $d) echo "<li>{$d['razorpay_order_id']} — {$d['cnt']} rows</li>";
        echo "</ul>";
    } else {
        echo "<p style='color:green'>✅ No duplicate order IDs found.</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red'>DB Error: " . $e->getMessage() . "</p>";
}

echo "<hr><p style='color:red;font-weight:bold'>⚠ DELETE THIS FILE (debug-verify.php) after you're done debugging!</p>";
