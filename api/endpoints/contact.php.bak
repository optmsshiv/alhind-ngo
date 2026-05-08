<?php
// endpoints/contact.php
// Writes to BOTH contact_messages AND ngo_inquiries (keeps your existing table intact)

function submitContact(): void {
    $b = body();
    if (empty($b['name']) || empty($b['message'])) {
        error('Name and message are required');
    }

    $db       = getDB();
    $ticketId = 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));

    // ── Write to contact_messages (new unified table) ────────
    $stmt = $db->prepare("
        INSERT INTO contact_messages
            (sender_name, sender_email, sender_phone, interest_type, message, ticket_id, status)
        VALUES (?, ?, ?, ?, ?, ?, 'submitted')
    ");
    $stmt->execute([
        sanitize($b['name']),
        sanitize($b['email']    ?? ''),
        sanitize($b['phone']    ?? ''),
        sanitize($b['interest'] ?? 'general'),
        sanitize($b['message']),
        $ticketId,
    ]);
    $newId = $db->lastInsertId();

    // ── Also write to ngo_inquiries (preserve existing flow) ─
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt2 = $db->prepare("
        INSERT INTO ngo_inquiries
            (ticket_id, name, email, phone, interest, message, ip_address, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted')
    ");
    $stmt2->execute([
        $ticketId,
        sanitize($b['name']),
        sanitize($b['email']    ?? ''),
        sanitize($b['phone']    ?? ''),
        sanitize($b['interest'] ?? 'general'),
        sanitize($b['message']),
        sanitize($ip),
    ]);

    ok(['id' => $newId, 'ticket_id' => $ticketId], 'Message received', 201);
}

function getAllMessages(): void {
    $db   = getDB();
    $stmt = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    $unread = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    ok(['messages' => $rows, 'unread_count' => (int)$unread, 'count' => count($rows)]);
}

function markMessageRead(string $id): void {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) error('Message not found', 404);
    ok(null, 'Marked as read');
}

function markAllRead(): void {
    getDB()->exec("UPDATE contact_messages SET is_read = 1");
    ok(null, 'All messages marked as read');
}

function deleteMessage(string $id): void {
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) error('Message not found', 404);
    ok(null, 'Message deleted');
}

function clearMessages(): void {
    getDB()->exec("DELETE FROM contact_messages");
    ok(null, 'All messages cleared');
}