<?php
// endpoints/event-volunteers.php
// ============================================================
//  Email: currently uses PHP mail() — SMTP-ready (see comments)
//  Ticket: auto-generated ALH-YYYYMMDD-XXXX format
// ============================================================

/* ════════════════════════════════════════════════════════════
   MAILER
   ─────────────────────────────────────────────────────────
   Currently: PHP mail() — works on most shared hosting
   To switch to SMTP later:
     1. composer require phpmailer/phpmailer
     2. Uncomment the SMTP block inside evtSendMail() below
     3. Comment out the mail() lines
════════════════════════════════════════════════════════════ */
function evtSendMail(
    string $toEmail, string $toName,
    string $subject, string $htmlBody,
    string $plainBody, string $replyTo = ''
): bool {
    $from     = 'noreply@alhindtrust.com'; // ← use your domain email
    $fromName = 'AL Hind Trust';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$from}>\r\n";
    $headers .= $replyTo ? "Reply-To: {$replyTo}\r\n" : "Reply-To: alhindtrust@gmail.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $ok = mail($toEmail, $subject, $htmlBody, $headers);
    if (!$ok) error_log("[AL Hind] mail() failed → {$toEmail}");
    return $ok;

    /* ── SMTP OPTION — uncomment when ready ──────────────────
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    try {
        $mail             = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alhindtrust@gmail.com';
        $mail->Password   = 'yyym lxhp pyro alyk';   // Gmail app password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom('alhindtrust@gmail.com', 'AL Hind Trust');
        if ($replyTo) $mail->addReplyTo($replyTo);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[AL Hind] SMTP failed: ' . $e->getMessage());
        return false;
    }
    ──────────────────────────────────────────────────────── */
}

/* ── Ticket number generator ─────────────────────────────────── */
function generateTicket(): string {
    return 'ALH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
}

/* ════════════════════════════════════════════════════════════
   REGISTER VOLUNTEER
════════════════════════════════════════════════════════════ */
function registerVolunteer(): void {
    $b  = body();
    $db = getDB();

    $eventId = isset($b['event_id']) ? (int)$b['event_id'] : 0;
    $name    = sanitize($b['name']    ?? '');
    $phone   = sanitize($b['phone']   ?? '');
    $email   = sanitize($b['email']   ?? '');
    $city    = sanitize($b['city']    ?? '');
    $message = sanitize($b['message'] ?? '');

    if (!$eventId)     error('event_id is required');
    if (empty($name))  error('Name is required');
    if (empty($phone)) error('Phone is required');
    if (!preg_match('/^[6-9]\d{9}$/', $phone)) error('Enter a valid 10-digit Indian mobile number');
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) error('Enter a valid email address');
    if (empty($city))  error('City is required');

    // Fetch event
    $evtStmt = $db->prepare("
        SELECT id, title, event_date, location
        FROM events WHERE id = ? AND is_active = 1
    ");
    $evtStmt->execute([$eventId]);
    $event = $evtStmt->fetch();
    if (!$event) error('Event not found or no longer active', 404);

    // Duplicate phone check
    $dup = $db->prepare("SELECT id FROM event_volunteers WHERE event_id = ? AND phone = ?");
    $dup->execute([$eventId, $phone]);
    if ($dup->fetch()) error('This phone number is already registered for this event', 409);

    // Add ticket_no column if missing (safe one-time migration)
    try {
        $db->exec("ALTER TABLE event_volunteers ADD COLUMN ticket_no VARCHAR(30) DEFAULT NULL UNIQUE");
    } catch (PDOException $e) {
        // Column already exists — safe to ignore
    }

    // Generate unique ticket
    $ticket = generateTicket();
    $ticketCheck = $db->prepare("SELECT id FROM event_volunteers WHERE ticket_no = ?");
    $ticketCheck->execute([$ticket]);
    if ($ticketCheck->fetch()) {
        $ticket = 'ALH-' . date('Ymd') . '-' . strtoupper(uniqid());
    }

    // Insert
    $db->prepare("
        INSERT INTO event_volunteers (event_id, name, email, phone, city, message, ticket_no)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$eventId, $name, $email ?: null, $phone, $city, $message ?: null, $ticket]);

    $volId = $db->lastInsertId();

    // Send emails (failures are logged but never block registration)
    if (!empty($email)) {
        sendVolunteerConfirmation($email, $name, $phone, $city, $event, $ticket);
    }
    sendAdminVolunteerAlert($name, $phone, $email, $city, $message, $event, $ticket);

    ok([
        'id'        => (int)$volId,
        'name'      => $name,
        'phone'     => $phone,
        'email'     => $email,
        'event_id'  => $eventId,
        'ticket_no' => $ticket,
    ], 'Registration successful', 201);
}

/* ════════════════════════════════════════════════════════════
   GET VOLUNTEERS FOR AN EVENT (admin)
════════════════════════════════════════════════════════════ */
function getEventVolunteers(string $eventId): void {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT ev.id, ev.name, ev.email, ev.phone, ev.city,
               ev.message, ev.ticket_no, ev.created_at,
               e.title AS event_title, e.event_date
        FROM event_volunteers ev
        JOIN events e ON e.id = ev.event_id
        WHERE ev.event_id = ?
        ORDER BY ev.created_at DESC
    ");
    $stmt->execute([$eventId]);
    ok($stmt->fetchAll());
}

/* ════════════════════════════════════════════════════════════
   CONFIRMATION EMAIL → REGISTRANT
════════════════════════════════════════════════════════════ */
function sendVolunteerConfirmation(
    string $email, string $name, string $phone,
    string $city, array $event, string $ticket
): void {
    try {
        $dateStr = date('l, d F Y', strtotime($event['event_date']));
        $sName   = htmlspecialchars($name,                         ENT_QUOTES, 'UTF-8');
        $sTitle  = htmlspecialchars($event['title'],               ENT_QUOTES, 'UTF-8');
        $sLoc    = htmlspecialchars($event['location'] ?? 'TBA',   ENT_QUOTES, 'UTF-8');
        $sCity   = htmlspecialchars($city,                         ENT_QUOTES, 'UTF-8');
        $sTicket = htmlspecialchars($ticket,                       ENT_QUOTES, 'UTF-8');

        $subject = "Registration Confirmed – {$event['title']} | AL Hind Trust";

        $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0"
  style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">

  <tr><td style="background:linear-gradient(135deg,#0a4e48 0%,#0f766e 100%);padding:28px 32px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:20px;font-weight:700;">AL Hind Educational &amp; Charitable Trust</h1>
    <p style="color:#99f6e4;margin:6px 0 0;font-size:13px;">Event Registration Confirmation</p>
  </td></tr>

  <tr><td style="padding:28px 32px 0;">
    <p style="font-size:16px;color:#0f172a;margin:0 0 6px;">Dear <strong>{$sName}</strong>,</p>
    <p style="font-size:14px;color:#475569;margin:0;line-height:1.65;">
      🎉 You are successfully registered for our upcoming event. We look forward to seeing you!
    </p>
  </td></tr>

  <tr><td style="padding:20px 32px 0;">
    <table width="100%" cellpadding="0" cellspacing="0"
      style="background:#f0fdf9;border:2px dashed #0f766e;border-radius:12px;">
      <tr><td style="padding:18px 20px;text-align:center;">
        <p style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:.1em;
                  text-transform:uppercase;color:#0f766e;">Your Ticket Number</p>
        <p style="margin:0;font-size:28px;font-weight:800;color:#0a4e48;
                  letter-spacing:3px;font-family:monospace;">{$sTicket}</p>
        <p style="margin:6px 0 0;font-size:11px;color:#64748b;">
          Please save this number — you may need it at the event
        </p>
      </td></tr>
    </table>
  </td></tr>

  <tr><td style="padding:20px 32px 0;">
    <p style="margin:0 0 10px;font-size:12px;font-weight:700;color:#0f766e;
              text-transform:uppercase;letter-spacing:.07em;">📅 Event Details</p>
    <table width="100%" cellpadding="0" cellspacing="0"
      style="border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;font-size:13.5px;">
      <tr style="background:#f8fafc;">
        <td style="padding:10px 16px;color:#64748b;font-weight:600;width:110px;border-bottom:1px solid #e2e8f0;">Event</td>
        <td style="padding:10px 16px;color:#0f172a;font-weight:700;border-bottom:1px solid #e2e8f0;">{$sTitle}</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">Date</td>
        <td style="padding:10px 16px;color:#0f172a;border-bottom:1px solid #e2e8f0;"><strong>{$dateStr}</strong></td>
      </tr>
      <tr style="background:#f8fafc;">
        <td style="padding:10px 16px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">Location</td>
        <td style="padding:10px 16px;color:#0f172a;border-bottom:1px solid #e2e8f0;">{$sLoc}</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">Your City</td>
        <td style="padding:10px 16px;color:#0f172a;border-bottom:1px solid #e2e8f0;">{$sCity}</td>
      </tr>
      <tr style="background:#f8fafc;">
        <td style="padding:10px 16px;color:#64748b;font-weight:600;">Phone</td>
        <td style="padding:10px 16px;color:#0f172a;">{$phone}</td>
      </tr>
    </table>
  </td></tr>

  <tr><td style="padding:16px 32px 0;">
    <table width="100%" cellpadding="0" cellspacing="0"
      style="background:#fef3c7;border-left:4px solid #f59e0b;border-radius:8px;">
      <tr><td style="padding:12px 16px;font-size:13px;color:#92400e;line-height:1.6;">
        📌 <strong>Please note:</strong> Arrive 15–20 minutes before the event starts.
        Keep your ticket number handy for entry.
      </td></tr>
    </table>
  </td></tr>

  <tr><td style="padding:24px 32px;text-align:center;">
    <a href="https://alhindtrust.com/events.html"
       style="display:inline-block;background:#0f766e;color:#fff;
              padding:12px 28px;border-radius:99px;text-decoration:none;
              font-weight:700;font-size:14px;">
      View All Events
    </a>
    <p style="margin:14px 0 0;font-size:12px;color:#94a3b8;">
      Questions? Reply to this email or visit
      <a href="https://alhindtrust.com" style="color:#0f766e;">alhindtrust.com</a>
    </p>
  </td></tr>

  <tr><td style="background:#0f172a;padding:14px 32px;text-align:center;">
    <p style="color:#94a3b8;font-size:12px;margin:0;">
      AL Hind Educational and Charitable Trust · Madhepura, Bihar<br>
      <a href="https://alhindtrust.com" style="color:#6ee7b7;text-decoration:none;">alhindtrust.com</a>
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>
HTML;

        $plain = "Dear {$name},\n\n"
               . "You are registered for: {$event['title']}\n"
               . "Ticket   : {$ticket}\n"
               . "Date     : {$dateStr}\n"
               . "Location : {$event['location']}\n"
               . "City     : {$city}\n"
               . "Phone    : {$phone}\n\n"
               . "Please carry your ticket number to the event.\n\n"
               . "Thank you!\nAL Hind Trust\nhttps://alhindtrust.com";

        evtSendMail($email, $name, $subject, $html, $plain);

    } catch (Throwable $e) {
        error_log('[AL Hind] Volunteer confirmation email failed: ' . $e->getMessage());
    }
}

/* ════════════════════════════════════════════════════════════
   ADMIN ALERT EMAIL
════════════════════════════════════════════════════════════ */
function sendAdminVolunteerAlert(
    string $name, string $phone, string $email,
    string $city, string $message, array $event, string $ticket
): void {
    try {
        $dateStr = date('d M Y', strtotime($event['event_date']));
        $sName   = htmlspecialchars($name,                          ENT_QUOTES, 'UTF-8');
        $sTitle  = htmlspecialchars($event['title'],                ENT_QUOTES, 'UTF-8');
        $sEmail  = htmlspecialchars($email ?: 'Not provided',       ENT_QUOTES, 'UTF-8');
        $sCity   = htmlspecialchars($city,                          ENT_QUOTES, 'UTF-8');
        $sMsg    = nl2br(htmlspecialchars($message ?: '—',          ENT_QUOTES, 'UTF-8'));
        $sTicket = htmlspecialchars($ticket,                        ENT_QUOTES, 'UTF-8');
        $time    = date('d M Y, h:i A');

        $subject = "New Registration [{$ticket}] – {$sTitle} | {$name}";

        $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:30px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0"
  style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">

  <tr><td style="background:#0f766e;padding:18px 24px;">
    <h3 style="color:#fff;margin:0;font-size:16px;">🔔 New Event Registration</h3>
    <p style="color:#99f6e4;margin:4px 0 0;font-size:13px;">{$sTitle} — {$dateStr}</p>
  </td></tr>

  <tr><td style="padding:16px 24px 0;text-align:center;">
    <span style="display:inline-block;background:#f0fdf9;border:2px dashed #0f766e;
                 border-radius:8px;padding:6px 20px;font-size:18px;font-weight:800;
                 color:#0a4e48;letter-spacing:2px;font-family:monospace;">
      {$sTicket}
    </span>
  </td></tr>

  <tr><td style="padding:16px 24px 24px;">
    <table width="100%" style="border-collapse:collapse;font-size:13.5px;
                                border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;">
      <tr style="background:#f8fafc;">
        <td style="padding:9px 14px;font-weight:600;color:#64748b;width:110px;border-bottom:1px solid #e2e8f0;">Name</td>
        <td style="padding:9px 14px;color:#0f172a;font-weight:700;border-bottom:1px solid #e2e8f0;">{$sName}</td>
      </tr>
      <tr>
        <td style="padding:9px 14px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">Phone</td>
        <td style="padding:9px 14px;color:#0f172a;border-bottom:1px solid #e2e8f0;">{$phone}</td>
      </tr>
      <tr style="background:#f8fafc;">
        <td style="padding:9px 14px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">Email</td>
        <td style="padding:9px 14px;color:#0f172a;border-bottom:1px solid #e2e8f0;">{$sEmail}</td>
      </tr>
      <tr>
        <td style="padding:9px 14px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">City</td>
        <td style="padding:9px 14px;color:#0f172a;border-bottom:1px solid #e2e8f0;">{$sCity}</td>
      </tr>
      <tr style="background:#f8fafc;">
        <td style="padding:9px 14px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">Message</td>
        <td style="padding:9px 14px;color:#0f172a;border-bottom:1px solid #e2e8f0;">{$sMsg}</td>
      </tr>
      <tr>
        <td style="padding:9px 14px;font-weight:600;color:#64748b;">Time</td>
        <td style="padding:9px 14px;color:#0f172a;">{$time}</td>
      </tr>
    </table>
    <div style="text-align:center;margin-top:18px;">
      <a href="https://alhindtrust.com/admin/"
         style="display:inline-block;background:#0f766e;color:#fff;
                padding:10px 24px;border-radius:99px;text-decoration:none;
                font-weight:700;font-size:13px;">
        View in Admin Panel
      </a>
    </div>
  </td></tr>

  <tr><td style="background:#f1f5f9;text-align:center;padding:12px;font-size:12px;color:#94a3b8;">
    AL Hind Trust Admin Notification
  </td></tr>

</table>
</td></tr>
</table>
</body></html>
HTML;

        $plain = "New Registration\n"
               . "Ticket  : {$ticket}\n"
               . "Event   : {$event['title']} ({$dateStr})\n"
               . "Name    : {$name}\n"
               . "Phone   : {$phone}\n"
               . "Email   : " . ($email ?: 'Not provided') . "\n"
               . "City    : {$city}\n"
               . "Message : " . ($message ?: '—') . "\n"
               . "Time    : {$time}\n";

        $replyTo = $email ? "{$name} <{$email}>" : '';
        evtSendMail('alhindtrust@gmail.com', 'AL Hind Admin', $subject, $html, $plain, $replyTo);

    } catch (Throwable $e) {
        error_log('[AL Hind] Admin alert email failed: ' . $e->getMessage());
    }
}