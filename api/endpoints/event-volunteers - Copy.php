<?php
// endpoints/event-volunteers.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ── SMTP helper — matches existing join.php pattern ─────────── */
function evtSmtp(): PHPMailer {
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = 'smtp.gmail.com';
    $m->SMTPAuth   = true;
    $m->Username   = 'alhindtrust@gmail.com';
    $m->Password   = 'yyym lxhp pyro alyk';
    $m->SMTPSecure = 'tls';
    $m->Port       = 587;
    $m->setFrom('alhindtrust@gmail.com', 'AL Hind Trust');
    $m->CharSet    = 'UTF-8';
    return $m;
}

/* ── Register a volunteer for an event ───────────────────────── */
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

    $evtStmt = $db->prepare("SELECT id, title, event_date, location FROM events WHERE id = ? AND is_active = 1");
    $evtStmt->execute([$eventId]);
    $event = $evtStmt->fetch();
    if (!$event) error('Event not found or no longer active', 404);

    $dup = $db->prepare("SELECT id FROM event_volunteers WHERE event_id = ? AND phone = ?");
    $dup->execute([$eventId, $phone]);
    if ($dup->fetch()) error('This phone number is already registered for this event', 409);

    $db->prepare("
        INSERT INTO event_volunteers (event_id, name, email, phone, city, message)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$eventId, $name, $email ?: null, $phone, $city, $message ?: null]);

    $volId = $db->lastInsertId();

    if (!empty($email)) sendVolunteerConfirmation($email, $name, $phone, $city, $event);
    sendAdminVolunteerAlert($name, $phone, $email, $city, $message, $event);

    ok(['id' => (int)$volId, 'name' => $name, 'phone' => $phone, 'email' => $email, 'event_id' => $eventId],
       'Registration successful', 201);
}

/* ── Get all volunteers for an event (admin) ─────────────────── */
function getEventVolunteers(string $eventId): void {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT ev.id, ev.name, ev.email, ev.phone, ev.city, ev.message, ev.created_at,
               e.title AS event_title, e.event_date
        FROM event_volunteers ev
        JOIN events e ON e.id = ev.event_id
        WHERE ev.event_id = ?
        ORDER BY ev.created_at DESC
    ");
    $stmt->execute([$eventId]);
    ok($stmt->fetchAll());
}

/* ── Confirmation email to visitor ───────────────────────────── */
function sendVolunteerConfirmation(string $email, string $name, string $phone, string $city, array $event): void {
    try {
        $mail    = evtSmtp();
        $dateStr = date('l, d F Y', strtotime($event['event_date']));
        $sName   = htmlspecialchars($name);
        $sTitle  = htmlspecialchars($event['title']);
        $sLoc    = htmlspecialchars($event['location'] ?? 'To be announced');
        $sCity   = htmlspecialchars($city);

        $mail->addEmbeddedImage(__DIR__ . '/../../assets/logo.jpeg', 'ngologo');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = "Registration Confirmed – {$event['title']} | AL Hind Trust";
        $mail->Body = "
<table width='600' align='center' cellpadding='0' cellspacing='0'
  style='font-family:Segoe UI,Arial,sans-serif;border:1px solid #e5e7eb;'>
  <tr><td style='background:#0f766e;padding:20px;text-align:center;'>
    <h2 style='color:#fff;margin:0;'>AL Hind Educational &amp; Charitable Trust</h2>
    <p style='color:#ccfbf1;margin:6px 0 0;font-size:.875rem;'>Event Registration Confirmation</p>
  </td></tr>
  <tr><td style='text-align:center;padding:16px;'>
    <img src='cid:ngologo' style='width:80px;height:80px;border-radius:50%;border:3px solid #0f766e;'>
  </td></tr>
  <tr><td style='padding:10px 28px 20px;color:#111827;font-size:14px;line-height:1.7;'>
    <p>Dear <strong>{$sName}</strong>,</p>
    <p>&#127881; You are successfully registered for our upcoming event. We look forward to seeing you there!</p>
    <div style='background:#f0fdf9;border-left:4px solid #0f766e;border-radius:8px;padding:16px 20px;margin:16px 0;'>
      <p style='margin:0 0 10px;font-weight:700;color:#0f766e;'>&#128197; Event Details</p>
      <table style='font-size:13.5px;color:#1e293b;width:100%;'>
        <tr><td style='padding:4px 0;width:110px;color:#64748b;'>Event</td><td><strong>{$sTitle}</strong></td></tr>
        <tr><td style='padding:4px 0;color:#64748b;'>Date</td><td><strong>{$dateStr}</strong></td></tr>
        <tr><td style='padding:4px 0;color:#64748b;'>Location</td><td><strong>{$sLoc}</strong></td></tr>
        <tr><td style='padding:4px 0;color:#64748b;'>Your City</td><td>{$sCity}</td></tr>
        <tr><td style='padding:4px 0;color:#64748b;'>Phone</td><td>{$phone}</td></tr>
      </table>
    </div>
    <p>&#128204; <strong>Please note:</strong> Arrive 15-20 minutes before the event starts.</p>
    <p>If you have any questions, reply to this email or contact us directly.</p>
    <div style='text-align:center;margin:20px 0;'>
      <a href='https://alhindtrust.com/events.html'
         style='display:inline-block;background:#0f766e;color:#fff;padding:11px 24px;border-radius:8px;text-decoration:none;font-weight:700;'>
        View All Events
      </a>
    </div>
    <hr style='border:none;border-top:1px solid #e5e7eb;margin:16px 0;'>
    <p style='font-size:12px;color:#6b7280;'>AL Hind Educational and Charitable Trust, Madhepura, Bihar</p>
  </td></tr>
  <tr><td style='background:#111827;color:#fff;text-align:center;padding:14px;font-size:13px;'>
    AL Hind Team | Madhepura, Bihar<br>
    <a href='https://alhindtrust.com' style='color:#6ee7b7;text-decoration:none;'>alhindtrust.com</a>
  </td></tr>
</table>";
        $mail->AltBody = "Dear {$name},\n\nYou are registered for: {$event['title']}\nDate: {$dateStr}\nLocation: {$event['location']}\n\nThank you!\nAL Hind Trust";
        $mail->send();
    } catch (Exception $e) {
        error_log('Volunteer confirmation email failed: ' . $e->getMessage());
    }
}

/* ── Admin alert email ───────────────────────────────────────── */
function sendAdminVolunteerAlert(string $name, string $phone, string $email, string $city, string $message, array $event): void {
    try {
        $admin   = evtSmtp();
        $dateStr = date('d M Y', strtotime($event['event_date']));
        $sName   = htmlspecialchars($name);
        $sTitle  = htmlspecialchars($event['title']);
        $sEmail  = htmlspecialchars($email ?: 'Not provided');
        $sCity   = htmlspecialchars($city);
        $sMsg    = nl2br(htmlspecialchars($message ?: '—'));

        $admin->addAddress('alhindtrust@gmail.com', 'AL Hind Admin');
        if ($email) $admin->addReplyTo($email, $name);
        $admin->isHTML(true);
        $admin->Subject = "NEW Registration – {$event['title']} | {$name}";
        $admin->Body = "
<table width='540' align='center' cellpadding='0' cellspacing='0'
  style='font-family:Segoe UI,Arial,sans-serif;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;'>
  <tr><td style='background:#0f766e;padding:16px 20px;'>
    <h3 style='color:#fff;margin:0;'>New Event Registration</h3>
    <p style='color:#ccfbf1;margin:4px 0 0;font-size:13px;'>{$sTitle} — {$dateStr}</p>
  </td></tr>
  <tr><td style='padding:20px 24px;'>
    <table width='100%' style='border-collapse:collapse;font-size:13.5px;color:#1e293b;'>
      <tr style='background:#f8fafc;'><td style='padding:8px 12px;font-weight:600;color:#64748b;width:130px;'>Name</td><td style='padding:8px 12px;'><strong>{$sName}</strong></td></tr>
      <tr><td style='padding:8px 12px;font-weight:600;color:#64748b;'>Phone</td><td style='padding:8px 12px;'>{$phone}</td></tr>
      <tr style='background:#f8fafc;'><td style='padding:8px 12px;font-weight:600;color:#64748b;'>Email</td><td style='padding:8px 12px;'>{$sEmail}</td></tr>
      <tr><td style='padding:8px 12px;font-weight:600;color:#64748b;'>City</td><td style='padding:8px 12px;'>{$sCity}</td></tr>
      <tr style='background:#f8fafc;'><td style='padding:8px 12px;font-weight:600;color:#64748b;'>Message</td><td style='padding:8px 12px;'>{$sMsg}</td></tr>
      <tr><td style='padding:8px 12px;font-weight:600;color:#64748b;'>Time</td><td style='padding:8px 12px;'>" . date('d M Y, h:i A') . "</td></tr>
    </table>
    <div style='text-align:center;margin-top:20px;'>
      <a href='https://alhindtrust.com/admin/'
         style='display:inline-block;background:#0f766e;color:#fff;padding:10px 22px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px;'>
        View in Admin Panel
      </a>
    </div>
  </td></tr>
  <tr><td style='background:#f1f5f9;text-align:center;padding:12px;font-size:12px;color:#94a3b8;'>
    AL Hind Trust Admin Notification
  </td></tr>
</table>";
        $admin->send();
    } catch (Exception $e) {
        error_log('Admin volunteer alert failed: ' . $e->getMessage());
    }
}