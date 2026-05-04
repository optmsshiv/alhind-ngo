<?php
// endpoints/event-volunteers.php

/* ── Register a volunteer for an event ─────────────────────── */
function registerVolunteer(): void {
    $b  = body();
    $db = getDB();

    // ── Validate required fields ─────────────────────────────
    $eventId = isset($b['event_id']) ? (int)$b['event_id'] : 0;
    $name    = sanitize($b['name']    ?? '');
    $phone   = sanitize($b['phone']   ?? '');
    $email   = sanitize($b['email']   ?? '');
    $city    = sanitize($b['city']    ?? '');
    $message = sanitize($b['message'] ?? '');

    if (!$eventId)          error('event_id is required');
    if (empty($name))       error('Name is required');
    if (empty($phone))      error('Phone is required');
    if (!preg_match('/^[6-9]\d{9}$/', $phone)) error('Enter a valid 10-digit phone number');
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) error('Enter a valid email address');

    // ── Check event exists and is active ─────────────────────
    $evtStmt = $db->prepare("SELECT id, title, event_date, location FROM events WHERE id = ? AND is_active = 1");
    $evtStmt->execute([$eventId]);
    $event = $evtStmt->fetch();
    if (!$event) error('Event not found or no longer active', 404);

    // ── Check for duplicate registration ─────────────────────
    $dupStmt = $db->prepare("SELECT id FROM event_volunteers WHERE event_id = ? AND phone = ?");
    $dupStmt->execute([$eventId, $phone]);
    if ($dupStmt->fetch()) error('This phone number is already registered for this event', 409);

    // ── Insert registration ───────────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO event_volunteers (event_id, name, email, phone, city, message)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$eventId, $name, $email ?: null, $phone, $city, $message ?: null]);
    $volId = $db->lastInsertId();

    // ── Send confirmation email if email provided ─────────────
    if (!empty($email)) {
        sendVolunteerEmail($email, $name, $event);
    }

    // ── Notify admin ──────────────────────────────────────────
    sendAdminNotification($name, $phone, $email, $city, $event);

    ok([
        'id'       => (int)$volId,
        'name'     => $name,
        'phone'    => $phone,
        'email'    => $email,
        'event_id' => $eventId,
    ], 'Registration successful', 201);
}

/* ── Get all volunteers for an event (admin) ────────────────── */
function getEventVolunteers(string $eventId): void {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT ev.id, ev.name, ev.email, ev.phone, ev.city, ev.message, ev.created_at,
               e.title AS event_title
        FROM event_volunteers ev
        JOIN events e ON e.id = ev.event_id
        WHERE ev.event_id = ?
        ORDER BY ev.created_at DESC
    ");
    $stmt->execute([$eventId]);
    ok($stmt->fetchAll());
}

/* ── Send confirmation email to volunteer ───────────────────── */
function sendVolunteerEmail(string $email, string $name, array $event): void {
    try {
        $mail = getMailer(); // Your existing PHPMailer instance

        $dateStr = date('l, d F Y', strtotime($event['event_date']));

        $mail->addAddress($email, $name);
        $mail->Subject = '✅ Registration Confirmed — ' . $event['title'];
        $mail->isHTML(true);
        $mail->Body = "
<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
    .wrap { max-width: 560px; margin: 2rem auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,.08); }
    .header { background: #0f766e; padding: 2rem; text-align: center; }
    .header h1 { color: #fff; font-size: 1.4rem; margin: 0; }
    .header p  { color: #ccfbf1; font-size: .9rem; margin: .5rem 0 0; }
    .body { padding: 2rem; }
    .body h2 { color: #0f172a; font-size: 1.1rem; margin-bottom: 1rem; }
    .details { background: #f0fdf9; border-left: 4px solid #0f766e; border-radius: 8px; padding: 1rem 1.25rem; margin: 1rem 0; }
    .details p { margin: .4rem 0; font-size: .9rem; color: #1e293b; }
    .details strong { color: #0f766e; }
    .footer { background: #f8fafc; padding: 1.25rem 2rem; text-align: center; font-size: .78rem; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    .btn { display: inline-block; background: #0f766e; color: #fff; padding: .7rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 700; margin-top: 1rem; }
  </style>
</head>
<body>
<div class='wrap'>
  <div class='header'>
    <h1>✅ Registration Confirmed!</h1>
    <p>AL Hind Educational &amp; Charitable Trust</p>
  </div>
  <div class='body'>
    <h2>Dear {$name},</h2>
    <p>Thank you for registering for our event. Your spot has been confirmed.</p>
    <div class='details'>
      <p><strong>📅 Event:</strong> {$event['title']}</p>
      <p><strong>🗓️ Date:</strong> {$dateStr}</p>
      <p><strong>📍 Location:</strong> {$event['location']}</p>
    </div>
    <p>Please arrive on time. If you have any questions, reply to this email or contact us.</p>
    <a href='https://alhindtrust.com/events.html' class='btn'>View All Events</a>
  </div>
  <div class='footer'>
    AL Hind Educational and Charitable Trust, Madhepura, Bihar<br>
    &copy; " . date('Y') . " AL Hind Trust
  </div>
</div>
</body>
</html>";

        $mail->AltBody = "Dear {$name},\n\nYour registration is confirmed for: {$event['title']}\nDate: {$dateStr}\nLocation: {$event['location']}\n\nThank you!\nAL Hind Trust";

        $mail->send();
    } catch (Exception $e) {
        // Don't fail the request if email fails — just log
        error_log('Volunteer email failed: ' . $e->getMessage());
    }
}

/* ── Notify admin of new registration ───────────────────────── */
function sendAdminNotification(string $name, string $phone, string $email, string $city, array $event): void {
    try {
        $mail = getMailer();
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@alhindtrust.com';

        $mail->addAddress($adminEmail, 'AL Hind Admin');
        $mail->Subject = '🔔 New Event Registration — ' . $event['title'];
        $mail->isHTML(true);
        $mail->Body = "
<div style='font-family:Arial,sans-serif;max-width:480px;'>
  <h2 style='color:#0f766e;'>New Registration</h2>
  <p><strong>Event:</strong> {$event['title']}</p>
  <p><strong>Name:</strong> {$name}</p>
  <p><strong>Phone:</strong> {$phone}</p>
  <p><strong>Email:</strong> " . ($email ?: 'Not provided') . "</p>
  <p><strong>City:</strong> {$city}</p>
  <p><strong>Time:</strong> " . date('d M Y, h:i A') . "</p>
</div>";
        $mail->send();
    } catch (Exception $e) {
        error_log('Admin notification failed: ' . $e->getMessage());
    }
}
