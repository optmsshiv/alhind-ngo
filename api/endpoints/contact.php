<?php
// endpoints/contact.php
// Handles all /contact routes: submit, list, view, mark-read, delete

/* ══════════════════════════════════════════════════════
   FEE + STATUS CONFIG
   Volunteer  → free → status = 'submitted'  (no payment)
   Team/Member→ ₹500 → status = 'payment_pending'
   Partner    → free → status = 'submitted'  (manual follow-up)
   General    → free → status = 'submitted'
══════════════════════════════════════════════════════ */
const PAID_ROLES = [
    'team' => 500,   // Member — ₹500
    // Add more paid roles here in future, e.g. 'premium' => 1000
];

// Roles that get a payment-link email after submit
function requiresPayment(string $interest): bool {
    global $PAID_ROLES;
    return isset($PAID_ROLES[$interest]);
}

function joiningFeeFor(string $interest): int {
    global $PAID_ROLES;
    return $PAID_ROLES[$interest] ?? 0;
}


/* ══════════════════════════════════════════════════════
   MAIN ENDPOINT FUNCTION
══════════════════════════════════════════════════════ */
function submitContact(): void {
    $b = body();

    // ── Validation ──────────────────────────────────────
    if (empty($b['name']) || empty($b['message'])) {
        error('Name and message are required');
    }

    $name     = sanitize($b['name']);
    $email    = sanitize($b['email']    ?? '');
    $phone    = sanitize($b['phone']    ?? '');
    $interest = sanitize($b['interest'] ?? 'general');
    $message  = sanitize($b['message']);
    $ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

    // ── Determine status & fee ───────────────────────────
    $needsPayment = requiresPayment($interest);
    $joiningFee   = joiningFeeFor($interest);
    $status       = $needsPayment ? 'payment_pending' : 'submitted';

    // ── Generate ticket ──────────────────────────────────
    $ticketId = 'TKT-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));

    $db = getDB();

    // ── Write to contact_messages ────────────────────────
    $stmt = $db->prepare("
        INSERT INTO contact_messages
            (sender_name, sender_email, sender_phone, interest_type, message, ticket_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $email, $phone, $interest, $message, $ticketId, $status]);
    $newId = $db->lastInsertId();

    // ── Write to ngo_inquiries (preserve existing flow) ──
    $stmt2 = $db->prepare("
        INSERT INTO ngo_inquiries
            (ticket_id, name, email, phone, interest, message, ip_address, joining_fee, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt2->execute([
        $ticketId, $name, $email, $phone, $interest,
        $message, sanitize($ip), $joiningFee, $status,
    ]);

    // ── Send emails ──────────────────────────────────────
    if ($needsPayment && !empty($email)) {
        sendPaymentLinkEmail($name, $email, $interest, $joiningFee, $ticketId);
    } elseif (!empty($email)) {
        sendConfirmationEmail($name, $email, $interest, $ticketId);
    }

    // Always notify admin
    sendAdminNotification($name, $email, $phone, $interest, $message, $ticketId, $status, $joiningFee);

    ok(['id' => $newId, 'ticket_id' => $ticketId], 'Message received', 201);
}


/* ══════════════════════════════════════════════════════
   EMAIL FUNCTIONS
   Requires PHPMailer. Adjust SMTP settings in getMailer().
══════════════════════════════════════════════════════ */

/**
 * Returns a configured PHPMailer instance.
 * Edit the SMTP credentials once here — all emails use this.
 */
function getMailer(): \PHPMailer\PHPMailer\PHPMailer {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';       // or your cPanel SMTP
    $mail->SMTPAuth   = true;
    $mail->Username   = 'alhindtrust@gmail.com';
    $mail->Password   = 'YOUR_APP_PASSWORD';     // use Gmail App Password
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('alhindtrust@gmail.com', 'AL Hind Trust');
    return $mail;
}

/**
 * Email 1 — Free roles (volunteer, partner, general)
 * Tells the user: thank you, we'll respond in 24-48h
 */
function sendConfirmationEmail(
    string $name, string $email,
    string $interest, string $ticketId
): void {
    $interestLabel = [
        'volunteer' => 'Volunteer',
        'partner'   => 'Partner / Collaborator',
        'general'   => 'General Inquiry',
    ][$interest] ?? ucfirst($interest);

    $subject = "Thank you for contacting AL Hind Trust — {$ticketId}";

    $html = emailWrapper("
        <h2 style='color:#0f766e;margin-top:0'>Thank you, {$name}! 🙏</h2>
        <p>We've received your message and will respond within <strong>24–48 hours</strong>.</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b;width:130px'>Interest</td>
                <td style='padding:7px 10px;background:#f0fdf4'>{$interestLabel}</td></tr>
            <tr><td style='padding:7px 10px;background:#fff;color:#64748b'>Ticket&nbsp;ID</td>
                <td style='padding:7px 10px;background:#fff;font-family:monospace'>{$ticketId}</td></tr>
        </table>
        <p style='font-size:13px;color:#64748b'>
            Please keep your ticket ID for reference.<br>
            If you have urgent queries, email us at
            <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a>
            or call <a href='tel:+919263190568' style='color:#0f766e'>+91-9263190568</a>.
        </p>
    ");

    try {
        $mail = getMailer();
        $mail->addAddress($email, $name);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);
        $mail->send();
    } catch (\Exception $e) {
        error_log("Confirmation email failed [{$ticketId}]: " . $e->getMessage());
    }
}

/**
 * Email 2 — Paid roles (Member = ₹500)
 * Tells the user: complete payment via the link
 */
function sendPaymentLinkEmail(
    string $name, string $email,
    string $interest, int $fee, string $ticketId
): void {
    $interestLabel = [
        'team' => 'Member',
    ][$interest] ?? ucfirst($interest);

    $payUrl  = "https://api.alhindtrust.com/pay.php?ticket={$ticketId}";
    $subject = "Complete your joining contribution — AL Hind Trust ({$ticketId})";

    $html = emailWrapper("
        <h2 style='color:#0f766e;margin-top:0'>Almost there, {$name}! 🙏</h2>
        <p>Thank you for your interest in joining AL Hind Trust as a <strong>{$interestLabel}</strong>.</p>
        <p>To complete your registration, please make a one-time joining contribution of
           <strong style='color:#0f766e'>₹{$fee}</strong>.</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b;width:130px'>Role</td>
                <td style='padding:7px 10px;background:#f0fdf4'>{$interestLabel}</td></tr>
            <tr><td style='padding:7px 10px;background:#fff;color:#64748b'>Amount</td>
                <td style='padding:7px 10px;background:#fff'><strong>₹{$fee}</strong> (one-time)</td></tr>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b'>Ticket&nbsp;ID</td>
                <td style='padding:7px 10px;background:#f0fdf4;font-family:monospace'>{$ticketId}</td></tr>
        </table>
        <div style='text-align:center;margin:28px 0'>
            <a href='{$payUrl}'
               style='background:#0f766e;color:#fff;padding:14px 32px;border-radius:8px;
                      text-decoration:none;font-weight:700;font-size:16px;display:inline-block'>
                Pay ₹{$fee} Now →
            </a>
        </div>
        <p style='font-size:12px;color:#94a3b8;text-align:center'>
            Link expires in 72 hours · Powered by Razorpay
        </p>
        <p style='font-size:13px;color:#64748b'>
            This contribution supports onboarding, ID card, training material, and program coordination.<br>
            If you have questions, contact us at
            <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a>.
        </p>
    ");

    try {
        $mail = getMailer();
        $mail->addAddress($email, $name);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = "Complete your joining contribution of ₹{$fee}: {$payUrl}";
        $mail->send();
    } catch (\Exception $e) {
        error_log("Payment link email failed [{$ticketId}]: " . $e->getMessage());
    }
}

/**
 * Email 3 — Admin notification (all submissions)
 * Goes to alhindtrust@gmail.com on every new message
 */
function sendAdminNotification(
    string $name, string $email, string $phone,
    string $interest, string $message,
    string $ticketId, string $status, int $fee
): void {
    $statusLabel = $status === 'payment_pending'
        ? "⏳ Payment Pending (₹{$fee})"
        : '✅ Submitted';

    $interestLabel = [
        'volunteer' => 'Volunteer (Free)',
        'team'      => 'Member (₹500)',
        'partner'   => 'Partner / Collaborator',
        'general'   => 'General Inquiry',
    ][$interest] ?? ucfirst($interest);

    $subject = "[New Inquiry] {$name} — {$interestLabel} · {$ticketId}";

    $html = emailWrapper("
        <h2 style='color:#0f766e;margin-top:0'>New Contact Inquiry</h2>
        <table style='width:100%;border-collapse:collapse;font-size:14px'>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b;width:110px'>Name</td>
                <td style='padding:7px 10px;background:#f0fdf4'><strong>{$name}</strong></td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Email</td>
                <td style='padding:7px 10px'><a href='mailto:{$email}' style='color:#0f766e'>{$email}</a></td></tr>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b'>Phone</td>
                <td style='padding:7px 10px;background:#f0fdf4'>{$phone}</td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Interest</td>
                <td style='padding:7px 10px'>{$interestLabel}</td></tr>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b'>Status</td>
                <td style='padding:7px 10px;background:#f0fdf4'>{$statusLabel}</td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Ticket</td>
                <td style='padding:7px 10px;font-family:monospace'>{$ticketId}</td></tr>
        </table>
        <div style='background:#f8fafc;border-left:4px solid #0f766e;padding:12px 16px;margin:16px 0;border-radius:0 6px 6px 0'>
            <div style='font-size:12px;color:#64748b;margin-bottom:6px'>MESSAGE</div>
            <div style='font-size:14px;color:#1e293b'>" . nl2br(htmlspecialchars($message)) . "</div>
        </div>
        <p style='text-align:center'>
            <a href='https://admin.alhindtrust.com'
               style='background:#0f766e;color:#fff;padding:10px 24px;border-radius:7px;
                      text-decoration:none;font-weight:700;font-size:14px;display:inline-block'>
                Open Admin Panel →
            </a>
        </p>
    ");

    try {
        $mail = getMailer();
        $mail->addAddress('alhindtrust@gmail.com', 'AL Hind Trust Admin');
        if (!empty($email)) $mail->addReplyTo($email, $name);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = "New inquiry from {$name} ({$interestLabel}) · Ticket: {$ticketId}";
        $mail->send();
    } catch (\Exception $e) {
        error_log("Admin notification email failed [{$ticketId}]: " . $e->getMessage());
    }
}

/**
 * Shared HTML email wrapper — consistent branded template
 */
function emailWrapper(string $content): string {
    return "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,sans-serif'>
  <div style='max-width:560px;margin:32px auto;background:#fff;border-radius:14px;
              overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)'>
    <!-- Header -->
    <div style='background:#0f766e;padding:20px 28px;display:flex;align-items:center;gap:12px'>
      <div style='color:#fff'>
        <div style='font-weight:700;font-size:17px'>AL Hind Educational & Charitable Trust</div>
        <div style='font-size:12px;opacity:.75;margin-top:2px'>Madhepura, Bihar · alhindtrust.com</div>
      </div>
    </div>
    <!-- Body -->
    <div style='padding:28px'>
      {$content}
    </div>
    <!-- Footer -->
    <div style='background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 28px;
                font-size:12px;color:#94a3b8;text-align:center'>
      AL Hind Educational &amp; Charitable Trust · Kohinoor Complex, College Chowk,
      Madhepura – 852113, Bihar<br>
      <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a>
      &nbsp;·&nbsp;
      <a href='tel:+919263190568' style='color:#0f766e'>+91-9263190568</a>
    </div>
  </div>
</body>
</html>";
}


/* ══════════════════════════════════════════════════════
   ADMIN READ / MANAGE ROUTES (unchanged from original)
══════════════════════════════════════════════════════ */

function getAllMessages(): void {
    $db     = getDB();
    $stmt   = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
    $rows   = $stmt->fetchAll();
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

/**
 * POST /messages/{id}/resend-payment
 * Admin manually re-sends the payment link email to a payment_pending inquiry
 */
function resendPaymentLink(string $id): void {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT cm.*, ni.joining_fee
        FROM contact_messages cm
        LEFT JOIN ngo_inquiries ni ON ni.ticket_id = cm.ticket_id
        WHERE cm.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row)                               error('Message not found', 404);
    if ($row['status'] !== 'payment_pending') error('Not a payment-pending record', 400);
    if (empty($row['sender_email']))          error('No email on record', 400);

    sendPaymentLinkEmail(
        $row['sender_name'],
        $row['sender_email'],
        $row['interest_type'],
        (int)($row['joining_fee'] ?? 500),
        $row['ticket_id']
    );

    ok(null, 'Payment link resent');
}
