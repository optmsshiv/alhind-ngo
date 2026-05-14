<?php
// endpoints/contact.php

const PAID_ROLES     = ['team' => 500];
const APPROVAL_ROLES = ['volunteer'];

function requiresPayment(string $interest): bool { return isset(PAID_ROLES[$interest]); }
function joiningFeeFor(string $interest): int    { return PAID_ROLES[$interest] ?? 0; }

function submitContact(): void {
    $b = body();
    if (empty($b['name']) || empty($b['message'])) error('Name and message are required');

    $name           = sanitize($b['name']);
    $email          = sanitize($b['email']            ?? '');
    $phone          = sanitize($b['phone']            ?? '');
    $interest       = sanitize($b['interest']         ?? 'general');
    $message        = sanitize($b['message']);
    $ip             = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $fathersName    = sanitize($b['fathers_name']      ?? '');
    $dob            = sanitize($b['dob']               ?? '');
    $gender         = sanitize($b['gender']            ?? '');
    $bloodGroup     = sanitize($b['blood_group']       ?? '');
    $aadhaar        = sanitize($b['aadhaar']           ?? '');
    $city           = sanitize($b['city']              ?? '');
    $pinCode        = sanitize($b['pin_code']          ?? '');
    $address        = sanitize($b['address']           ?? '');
    $qualification  = sanitize($b['qualification']     ?? '');
    $occupation     = sanitize($b['occupation']        ?? '');
    $areaOfInterest = sanitize($b['area_of_interest']  ?? '');
    $availability   = sanitize($b['availability']      ?? '');
    $howHeard       = sanitize($b['how_heard']          ?? '');
    $orgName        = sanitize($b['org_name']          ?? '');
    $orgType        = sanitize($b['org_type']          ?? '');
    $designation    = sanitize($b['designation']       ?? '');
    $website        = sanitize($b['website']           ?? '');
    $collabType     = sanitize($b['collab_type']       ?? '');

    $needsPayment  = requiresPayment($interest);
    $joiningFee    = joiningFeeFor($interest);
    $needsApproval = in_array($interest, APPROVAL_ROLES);

    if ($needsPayment)      $status = 'payment_pending';
    elseif ($needsApproval) $status = 'pending_approval';
    else                    $status = 'submitted';

    $ticketId = 'TKT-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    $db = getDB();

    $stmt = $db->prepare("
        INSERT INTO contact_messages (
            sender_name, sender_email, sender_phone, interest_type, message, ticket_id, status,
            fathers_name, dob, gender, blood_group, aadhaar,
            city, pin_code, address, qualification, occupation,
            area_of_interest, availability, how_heard,
            org_name, org_type, designation, website, collab_type
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $name, $email, $phone, $interest, $message, $ticketId, $status,
        $fathersName, $dob, $gender, $bloodGroup, $aadhaar,
        $city, $pinCode, $address, $qualification, $occupation,
        $areaOfInterest, $availability, $howHeard,
        $orgName, $orgType, $designation, $website, $collabType,
    ]);
    $newId = $db->lastInsertId();

    $stmt2 = $db->prepare("
        INSERT INTO ngo_inquiries (ticket_id, name, email, phone, interest, message, ip_address, joining_fee, status)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $stmt2->execute([$ticketId, $name, $email, $phone, $interest, $message, sanitize($ip), $joiningFee, $status]);

    if ($needsPayment && !empty($email))      sendPaymentLinkEmail($name, $email, $interest, $joiningFee, $ticketId);
    elseif ($needsApproval && !empty($email)) sendPendingApprovalEmail($name, $email, $ticketId);
    elseif (!empty($email))                   sendConfirmationEmail($name, $email, $interest, $ticketId);

    $extra = compact('fathersName','dob','gender','bloodGroup','aadhaar','city','pinCode',
                     'address','qualification','occupation','areaOfInterest','availability',
                     'howHeard','orgName','orgType','designation','website','collabType');
    sendAdminNotification($name, $email, $phone, $interest, $message, $ticketId, $status, $joiningFee, $extra);

    ok(['id' => $newId, 'ticket_id' => $ticketId], 'Message received', 201);
}

function cntSendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody, string $replyTo = ''): bool {
    $autoload = '/home/u699609112/domains/alhindtrust.com/public_html/vendor/autoload.php';
    if (!file_exists($autoload)) { error_log('[AL Hind] PHPMailer not found'); return false; }
    require_once $autoload;
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alhindtrust@gmail.com';
        $mail->Password   = 'yyym lxhp pyro alyk';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom('alhindtrust@gmail.com', 'AL Hind Trust');
        $mail->addAddress($toEmail, $toName);
        if ($replyTo) $mail->addReplyTo($replyTo);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;
        $mail->send();
        error_log('[AL Hind] SMTP OK → ' . $toEmail);
        return true;
    } catch (\Exception $e) {
        error_log('[AL Hind] SMTP FAILED → ' . $toEmail . ' | ' . $e->getMessage());
        return false;
    }
}

function sendConfirmationEmail(string $name, string $email, string $interest, string $ticketId): void {
    $lbl = ['partner'=>'Partner / Collaborator','general'=>'General Inquiry'][$interest] ?? ucfirst($interest);
    $subject = "Thank you for contacting AL Hind Trust — {$ticketId}";
    $html = emailWrapper("
        <h2 style='color:#0f766e;margin-top:0'>Thank you, {$name}! 🙏</h2>
        <p>We've received your message and will respond within <strong>24–48 hours</strong>.</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b;width:130px'>Interest</td><td style='padding:7px 10px;background:#f0fdf4'>{$lbl}</td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Ticket ID</td><td style='padding:7px 10px;font-family:monospace'>{$ticketId}</td></tr>
        </table>
        <p style='font-size:13px;color:#64748b'>
            Email: <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a>
            · Phone: <a href='tel:+919263190568' style='color:#0f766e'>+91-9263190568</a>
        </p>");
    $plain = "Thank you {$name}! Interest: {$lbl}\nTicket: {$ticketId}\nReply within 24-48 hours.\nAL Hind Trust";
    cntSendMail($email, $name, $subject, $html, $plain);
}

function sendPendingApprovalEmail(string $name, string $email, string $ticketId): void {
    $subject = "Application received — AL Hind Trust ({$ticketId})";
    $html = emailWrapper("
        <h2 style='color:#0f766e;margin-top:0'>Thank you, {$name}! 🙏</h2>
        <p>We've received your volunteer application and it is now <strong>under review</strong> by our team.</p>
        <p>We will notify you by email once approved. This usually takes <strong>2–3 working days</strong>.</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b;width:130px'>Role</td><td style='padding:7px 10px;background:#f0fdf4'>Volunteer</td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Status</td><td style='padding:7px 10px'>Pending Approval</td></tr>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b'>Ticket ID</td><td style='padding:7px 10px;background:#f0fdf4;font-family:monospace'>{$ticketId}</td></tr>
        </table>
        <p style='font-size:13px;color:#64748b'>
            <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a>
            · <a href='tel:+919263190568' style='color:#0f766e'>+91-9263190568</a>
        </p>");
    $plain = "Dear {$name},\nYour volunteer application is under review.\nTicket: {$ticketId}\nWe'll notify you in 2-3 working days.\nAL Hind Trust";
    cntSendMail($email, $name, $subject, $html, $plain);
}

function sendWelcomeVolunteerEmail(array $row): void {
    if (empty($row['sender_email'])) return;
    $name = $row['sender_name']; $email = $row['sender_email']; $ticketId = $row['ticket_id'];
    $subject = "Welcome to AL Hind Trust — Volunteer Approved! ({$ticketId})";
    $parts = explode(' ', trim($name));
    $initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):''));
    $extraRows = '';
    if (!empty($row['blood_group'])) $extraRows .= "<tr><td style='padding:5px 0;color:#64748b;width:110px'>Blood Group</td><td style='padding:5px 0;font-weight:600'>{$row['blood_group']}</td></tr>";
    if (!empty($row['city']))        $extraRows .= "<tr><td style='padding:5px 0;color:#64748b'>City</td><td style='padding:5px 0'>{$row['city']}</td></tr>";
    if (!empty($row['area_of_interest'])) $extraRows .= "<tr><td style='padding:5px 0;color:#64748b'>Area</td><td style='padding:5px 0'>{$row['area_of_interest']}</td></tr>";
    $html = emailWrapper("
        <h2 style='color:#0f766e;margin-top:0'>Welcome, {$name}! 🎉</h2>
        <p>Your volunteer application has been <strong style='color:#0f766e'>approved</strong>. We're excited to have you on board!</p>
        <div style='border:2px solid #0f766e;border-radius:14px;overflow:hidden;max-width:380px;margin:24px auto;font-family:Segoe UI,sans-serif'>
            <div style='background:#0f766e;padding:16px 20px;display:flex;align-items:center;gap:14px'>
                <div style='width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;flex-shrink:0'>{$initials}</div>
                <div style='color:#fff'><div style='font-weight:700;font-size:16px'>{$name}</div><div style='font-size:12px;opacity:.8;margin-top:2px'>Volunteer</div></div>
            </div>
            <div style='padding:16px 20px;background:#fff'>
                <table style='width:100%;border-collapse:collapse;font-size:13px'>
                    <tr><td style='padding:5px 0;color:#64748b;width:110px'>Organisation</td><td style='padding:5px 0;font-weight:600;color:#0f766e'>AL Hind Trust</td></tr>
                    <tr><td style='padding:5px 0;color:#64748b'>Ticket ID</td><td style='padding:5px 0;font-family:monospace;font-size:12px'>{$ticketId}</td></tr>
                    <tr><td style='padding:5px 0;color:#64748b'>Email</td><td style='padding:5px 0'>{$email}</td></tr>
                    <tr><td style='padding:5px 0;color:#64748b'>Valid from</td><td style='padding:5px 0'>" . date('d M Y') . "</td></tr>
                    {$extraRows}
                </table>
            </div>
            <div style='background:#f0fdf4;padding:10px 20px;text-align:center;font-size:11px;color:#15803d;border-top:1px solid #dcfce7'>AL Hind Educational &amp; Charitable Trust · Madhepura, Bihar</div>
        </div>
        <p style='font-size:13px;color:#64748b;text-align:center'>
            Our team will be in touch with onboarding details.<br>
            <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a> · <a href='tel:+919263190568' style='color:#0f766e'>+91-9263190568</a>
        </p>");
    $plain = "Congratulations {$name}!\nApproved as Volunteer.\nTicket: {$ticketId}\nValid from: " . date('d M Y') . "\nAL Hind Trust";
    cntSendMail($email, $name, $subject, $html, $plain);
}

function sendPaymentLinkEmail(string $name, string $email, string $interest, int $fee, string $ticketId): void {
    $lbl    = ['team'=>'Member'][$interest] ?? ucfirst($interest);
    $payUrl = "https://alhindtrust.com/backend/pay.php?ticket={$ticketId}";
    $subject = "Complete your joining contribution — AL Hind Trust ({$ticketId})";
    $html = emailWrapper("
        <h2 style='color:#0f766e;margin-top:0'>Almost there, {$name}! 🙏</h2>
        <p>Thank you for your interest in joining AL Hind Trust as a <strong>{$lbl}</strong>.</p>
        <p>Please make a one-time joining contribution of <strong style='color:#0f766e'>₹{$fee}</strong>.</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b;width:130px'>Role</td><td style='padding:7px 10px;background:#f0fdf4'>{$lbl}</td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Amount</td><td style='padding:7px 10px'><strong>₹{$fee}</strong> (one-time)</td></tr>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b'>Ticket ID</td><td style='padding:7px 10px;background:#f0fdf4;font-family:monospace'>{$ticketId}</td></tr>
        </table>
        <div style='text-align:center;margin:28px 0'>
            <a href='{$payUrl}' style='background:#0f766e;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block'>Pay ₹{$fee} Now →</a>
        </div>
        <p style='font-size:12px;color:#94a3b8;text-align:center'>Link expires in 72 hours · Powered by Razorpay</p>
        <p style='font-size:13px;color:#64748b'>
            <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a>
        </p>");
    $plain = "Dear {$name},\nPay Rs.{$fee}: {$payUrl}\nTicket: {$ticketId}\nExpires in 72 hours.\nAL Hind Trust";
    cntSendMail($email, $name, $subject, $html, $plain);
}

function sendAdminNotification(string $name, string $email, string $phone, string $interest, string $message, string $ticketId, string $status, int $fee, array $extra = []): void {
    $statusLabel = match($status) {
        'payment_pending'  => "Payment Pending (₹{$fee})",
        'pending_approval' => "Pending Admin Approval",
        default            => 'Submitted',
    };
    $intLabel = ['volunteer'=>'Volunteer','team'=>'Member (₹500)','partner'=>'Partner / Collaborator','general'=>'General Inquiry'][$interest] ?? ucfirst($interest);
    $approveUrl = "https://api.alhindtrust.com/messages/approve?ticket={$ticketId}&secret=ADMIN_SECRET_KEY";
    $subject = "[New Inquiry] {$name} — {$intLabel} · {$ticketId}";

    $extraFields = [];
    if ($interest === 'volunteer' || $interest === 'team') {
        $extraFields = [
            "Father's Name"   => $extra['fathersName']    ?? '',
            'DOB'             => $extra['dob']            ?? '',
            'Gender'          => $extra['gender']         ?? '',
            'Blood Group'     => $extra['bloodGroup']     ?? '',
            'Aadhaar'         => !empty($extra['aadhaar']) ? '••••'.substr($extra['aadhaar'],-4) : '',
            'City / District' => $extra['city']           ?? '',
            'PIN Code'        => $extra['pinCode']        ?? '',
            'Address'         => $extra['address']        ?? '',
            'Qualification'   => $extra['qualification']  ?? '',
            'Occupation'      => $extra['occupation']     ?? '',
            'Area of Interest'=> $extra['areaOfInterest'] ?? '',
            'Availability'    => $extra['availability']   ?? '',
            'How Heard'       => $extra['howHeard']       ?? '',
        ];
    } elseif ($interest === 'partner') {
        $extraFields = [
            'Organisation'    => $extra['orgName']     ?? '',
            'Org Type'        => $extra['orgType']     ?? '',
            'Designation'     => $extra['designation'] ?? '',
            'Website'         => $extra['website']     ?? '',
            'Collaboration'   => $extra['collabType']  ?? '',
        ];
    }

    $bg = ['#f0fdf4','#fff']; $i = 0; $extraHtml = '';
    foreach ($extraFields as $lbl => $val) {
        if (empty($val)) continue;
        $extraHtml .= "<tr><td style='padding:7px 10px;background:{$bg[$i%2]};color:#64748b;width:150px'>{$lbl}</td><td style='padding:7px 10px;background:{$bg[$i%2]}'>" . htmlspecialchars($val) . "</td></tr>";
        $i++;
    }

    $html = emailWrapper("
        <h2 style='color:#0f766e;margin-top:0'>New Contact Inquiry</h2>
        <table style='width:100%;border-collapse:collapse;font-size:14px'>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b;width:150px'>Name</td><td style='padding:7px 10px;background:#f0fdf4'><strong>{$name}</strong></td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Email</td><td style='padding:7px 10px'><a href='mailto:{$email}' style='color:#0f766e'>{$email}</a></td></tr>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b'>Phone</td><td style='padding:7px 10px;background:#f0fdf4'>{$phone}</td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Role</td><td style='padding:7px 10px'>{$intLabel}</td></tr>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b'>Status</td><td style='padding:7px 10px;background:#f0fdf4'>{$statusLabel}</td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Ticket</td><td style='padding:7px 10px;font-family:monospace'>{$ticketId}</td></tr>
            {$extraHtml}
        </table>
        <div style='background:#f8fafc;border-left:4px solid #0f766e;padding:12px 16px;margin:16px 0;border-radius:0 6px 6px 0'>
            <div style='font-size:12px;color:#64748b;margin-bottom:6px'>MESSAGE</div>
            <div style='font-size:14px;color:#1e293b'>" . nl2br(htmlspecialchars($message)) . "</div>
        </div>
        <p style='text-align:center;display:flex;gap:10px;justify-content:center;flex-wrap:wrap'>
            <a href='https://admin.alhindtrust.com' style='background:#0f766e;color:#fff;padding:10px 24px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;display:inline-block'>Open Admin Panel →</a>"
            . ($status === 'pending_approval' ? "<a href='{$approveUrl}' style='background:#16a34a;color:#fff;padding:10px 24px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;display:inline-block'>✓ Approve Volunteer</a>" : "") . "
        </p>");
    $plain = "New inquiry\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\nRole: {$intLabel}\nStatus: {$statusLabel}\nTicket: {$ticketId}\nMessage: {$message}";
    $replyTo = $email ? "{$name} <{$email}>" : '';
    cntSendMail('alhindtrust@gmail.com', 'AL Hind Admin', $subject, $html, $plain, $replyTo);
}

function emailWrapper(string $content): string {
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,sans-serif'>
  <div style='max-width:560px;margin:32px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)'>
    <div style='background:#0f766e;padding:20px 28px'><div style='color:#fff'>
      <div style='font-weight:700;font-size:17px'>AL Hind Educational &amp; Charitable Trust</div>
      <div style='font-size:12px;opacity:.75;margin-top:2px'>Madhepura, Bihar · alhindtrust.com</div>
    </div></div>
    <div style='padding:28px'>{$content}</div>
    <div style='background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 28px;font-size:12px;color:#94a3b8;text-align:center'>
      AL Hind Educational &amp; Charitable Trust · Kohinoor Complex, College Chowk, Madhepura – 852113, Bihar<br>
      <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a> &nbsp;·&nbsp;
      <a href='tel:+919263190568' style='color:#0f766e'>+91-9263190568</a>
    </div>
  </div>
</body></html>";
}

/* ═══════════════ ADMIN ROUTES (unchanged) ═══════════════ */
function getAllMessages(): void {
    $db   = getDB();
    $stmt = $db->query("SELECT cm.*, ni.joining_fee, ni.member_id FROM contact_messages cm LEFT JOIN ngo_inquiries ni ON ni.ticket_id = cm.ticket_id ORDER BY cm.created_at DESC");
    $rows   = $stmt->fetchAll();
    $unread = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    ok(['messages' => $rows, 'unread_count' => (int)$unread, 'count' => count($rows)]);
}
function markMessageRead(string $id): void {
    $db = getDB(); $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1, updated_at = NOW() WHERE id = ?"); $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) error('Message not found', 404); ok(null, 'Marked as read');
}
function markAllRead(): void { getDB()->exec("UPDATE contact_messages SET is_read = 1"); ok(null, 'All messages marked as read'); }
function deleteMessage(string $id): void {
    $db = getDB(); $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?"); $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) error('Message not found', 404); ok(null, 'Message deleted');
}
function clearMessages(): void { getDB()->exec("DELETE FROM contact_messages"); ok(null, 'All messages cleared'); }

function approveVolunteer(string $id): void {
    $db = getDB(); $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = ? LIMIT 1"); $stmt->execute([$id]); $row = $stmt->fetch();
    if (!$row) error('Message not found', 404);
    if ($row['status'] !== 'pending_approval') error('Not a pending approval record', 400);
    $db->prepare("UPDATE contact_messages SET status='approved', updated_at=NOW() WHERE id=?")->execute([$id]);
    $db->prepare("UPDATE ngo_inquiries SET status='approved' WHERE ticket_id=?")->execute([$row['ticket_id']]);
    sendWelcomeVolunteerEmail($row);
    ok(null, 'Volunteer approved and welcome email sent');
}

function rejectVolunteer(string $id): void {
    $b = body(); $db = getDB(); $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = ? LIMIT 1"); $stmt->execute([$id]); $row = $stmt->fetch();
    if (!$row) error('Message not found', 404);
    if ($row['status'] !== 'pending_approval') error('Not a pending approval record', 400);
    $reason = sanitize($b['reason'] ?? '');
    $db->prepare("UPDATE contact_messages SET status='rejected', updated_at=NOW() WHERE id=?")->execute([$id]);
    $db->prepare("UPDATE ngo_inquiries SET status='rejected' WHERE ticket_id=?")->execute([$row['ticket_id']]);
    if (!empty($row['sender_email'])) sendRejectionEmail($row, $reason);
    ok(null, 'Volunteer rejected and notification email sent');
}

function sendRejectionEmail(array $row, string $reason = ''): void {
    $name = $row['sender_name']; $email = $row['sender_email']; $ticketId = $row['ticket_id'];
    $subject = "Update on your application — AL Hind Trust ({$ticketId})";
    $reasonBlock = $reason ? "<p style='background:#fff7ed;border-left:3px solid #f97316;padding:10px 14px;border-radius:0 6px 6px 0;font-size:13px;color:#7c2d12;margin:16px 0'><strong>Reason:</strong> {$reason}</p>" : "";
    $html = emailWrapper("
        <h2 style='color:#1e293b;margin-top:0'>Application Update</h2>
        <p>Dear <strong>{$name}</strong>,</p>
        <p>Thank you for your interest in volunteering with AL Hind Trust.</p>
        <p>After reviewing your application, we regret to inform you that we are unable to move forward at this time.</p>
        {$reasonBlock}
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
            <tr><td style='padding:7px 10px;background:#f0fdf4;color:#64748b;width:130px'>Role applied</td><td style='padding:7px 10px;background:#f0fdf4'>Volunteer</td></tr>
            <tr><td style='padding:7px 10px;color:#64748b'>Ticket ID</td><td style='padding:7px 10px;font-family:monospace'>{$ticketId}</td></tr>
        </table>
        <p>We encourage you to apply again in the future.</p>
        <p style='font-size:13px;color:#64748b'>
            <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a> · <a href='tel:+919263190568' style='color:#0f766e'>+91-9263190568</a>
        </p>");
    $plain = "Dear {$name},\nWe are unable to proceed with your application at this time.\n".($reason?"Reason: {$reason}\n":"")."Ticket: {$ticketId}\nAL Hind Trust";
    cntSendMail($email, $name, $subject, $html, $plain);
}

function resendPaymentLink(string $id): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT cm.*, ni.joining_fee FROM contact_messages cm LEFT JOIN ngo_inquiries ni ON ni.ticket_id = cm.ticket_id WHERE cm.id = ? LIMIT 1");
    $stmt->execute([$id]); $row = $stmt->fetch();
    if (!$row) error('Message not found', 404);
    if ($row['status'] !== 'payment_pending') error('Not a payment-pending record', 400);
    if (empty($row['sender_email'])) error('No email on record', 400);
    sendPaymentLinkEmail($row['sender_name'], $row['sender_email'], $row['interest_type'], (int)($row['joining_fee'] ?? 500), $row['ticket_id']);
    ok(null, 'Payment link resent');
}
