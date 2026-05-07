<?php
// endpoints/donate.php
// ============================================================
//  Donation records — read/delete only.
//  Writing handled by:
//    /backend/create-order.php   → inserts pending row
//    /backend/verify-payment.php → updates to paid + payment_id
// ============================================================

function getAllDonations(): void {
    $db = getDB();

    // Clean up orphan duplicates: pending rows with no order_id
    // These were created by the old submitDonation() flow
    $db->exec("
        DELETE FROM donations
        WHERE payment_status = 'pending'
          AND (razorpay_order_id IS NULL OR razorpay_order_id = '')
          AND created_at < NOW() - INTERVAL 1 HOUR
    ");

    // Also deduplicate: if same order_id has both a paid and pending row,
    // remove the pending one
    $db->exec("
        DELETE d1 FROM donations d1
        INNER JOIN donations d2
            ON d1.razorpay_order_id = d2.razorpay_order_id
            AND d1.razorpay_order_id != ''
            AND d1.payment_status = 'pending'
            AND d2.payment_status = 'paid'
            AND d1.id != d2.id
    ");

    $rows = $db->query("
        SELECT
            id, donor_name, donor_email, amount,
            payment_method, payment_status,
            razorpay_order_id, razorpay_payment_id,
            created_at, updated_at
        FROM donations
        ORDER BY created_at DESC
    ")->fetchAll();

    $total = (float)$db->query("
        SELECT COALESCE(SUM(amount),0)
        FROM donations WHERE payment_status = 'paid'
    ")->fetchColumn();

    ok([
        'donations'     => $rows,
        'total_paid'    => $total,
        'count'         => count($rows),
        'paid_count'    => count(array_filter($rows, fn($r) => $r['payment_status'] === 'paid')),
        'pending_count' => count(array_filter($rows, fn($r) => $r['payment_status'] === 'pending')),
    ]);
}

function deleteDonation(string $id): void {
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM donations WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) error('Donation not found', 404);
    ok(null, 'Donation deleted');
}

function clearDonations(): void {
    getDB()->exec("DELETE FROM donations");
    ok(null, 'All donations cleared');
}

// Stubs — kept so router doesn't crash on old route calls
function submitDonation(): void {
    ok(['message' => 'Use /backend/create-order.php'], 'OK');
}
function confirmDonation(string $id): void {
    ok(['message' => 'Use /backend/verify-payment.php'], 'OK');
}