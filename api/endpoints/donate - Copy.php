<?php
// endpoints/donate.php
// Writes to BOTH donations AND donations_pending (keeps your existing table intact)

function submitDonation(): void {
    $b = body();
    if (empty($b['name']) || empty($b['amount'])) {
        error('Name and amount are required');
    }

    $db  = getDB();
    $amt = round((float)$b['amount'], 2);

    // ── Write to new donations table ─────────────────────────
    $stmt = $db->prepare("
        INSERT INTO donations
            (donor_name, donor_email, amount, payment_method, payment_status, razorpay_order_id, notes)
        VALUES (?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([
        sanitize($b['name']),
        sanitize($b['email']  ?? ''),
        $amt,
        sanitize($b['method'] ?? 'Online'),
        sanitize($b['razorpay_order_id'] ?? ''),
        sanitize($b['notes'] ?? ''),
    ]);
    $newId = $db->lastInsertId();

    // ── Also write to donations_pending (preserve existing flow)
    $stmt2 = $db->prepare("
        INSERT INTO donations_pending (order_id, amount, status, donor_name, donor_email, created_at)
        VALUES (?, ?, 'pending', ?, ?, NOW())
    ");
    $stmt2->execute([
        sanitize($b['razorpay_order_id'] ?? ''),
        $amt,
        sanitize($b['name']),
        sanitize($b['email'] ?? ''),
    ]);

    ok(['id' => $newId], 'Donation recorded', 201);
}

function confirmDonation(string $id): void {
    $b  = body();
    $db = getDB();
    $pid = sanitize($b['razorpay_payment_id'] ?? '');

    // Update new donations table
    $db->prepare("
        UPDATE donations
        SET payment_status = 'paid', razorpay_payment_id = ?, paid_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ")->execute([$pid, $id]);

    // Update donations_pending too
    $db->prepare("
        UPDATE donations_pending SET status = 'paid'
        WHERE order_id = (SELECT razorpay_order_id FROM donations WHERE id = ? LIMIT 1)
    ")->execute([$id]);

    ok(null, 'Payment confirmed');
}

function getAllDonations(): void {
    $db   = getDB();
    $stmt = $db->query("SELECT * FROM donations ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    $total = $db->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='paid'")->fetchColumn();
    ok(['donations' => $rows, 'total_paid' => (float)$total, 'count' => count($rows)]);
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