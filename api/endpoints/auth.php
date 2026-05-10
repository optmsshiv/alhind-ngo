<?php
// endpoints/auth.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ── Login ───────────────────────────────────────────────────── */
function handleAuth(): void {
    $body     = body();
    $username = sanitize($body['username'] ?? '');
    $password = $body['password']           ?? '';

    if (empty($username) || empty($password)) error('Username and password are required');

    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id, name, email, password_hash, role
        FROM admin_users
        WHERE (email = ? OR id = 1) AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Also allow login with username 'admin' mapped to the main admin row
    if (!$user && $username === 'admin') {
        $stmt = $db->prepare("SELECT id, name, email, password_hash, role FROM admin_users WHERE role = 'superadmin' AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        error('Invalid credentials', 401);
    }

    // Update last login timestamp
    $db->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?")
       ->execute([$user['id']]);

    $token = createToken(['user_id' => $user['id'], 'role' => $user['role']]);

    ok([
        'token' => $token,
        'role'  => $user['role'],
        'name'  => $user['name'],
        'email' => $user['email'],
    ], 'Login successful');
}

/* ── Forgot Password ─────────────────────────────────────────── */
function forgotPassword(): void {
    // Generate strong 10-char temp password
    $chars   = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#!';
    $tempPwd = '';
    for ($i = 0; $i < 10; $i++) $tempPwd .= $chars[random_int(0, strlen($chars) - 1)];

    $hash = password_hash($tempPwd, PASSWORD_DEFAULT);
    $db   = getDB();

    // Update the superadmin row
    $stmt = $db->prepare("SELECT id FROM admin_users WHERE role = 'superadmin' AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();

    if ($row) {
        $db->prepare("UPDATE admin_users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$hash, $row['id']]);
    } else {
        // Create admin row if missing
        $db->prepare("
            INSERT INTO admin_users (name, email, password_hash, role, is_active)
            VALUES ('Admin', 'alhindtrust@gmail.com', ?, 'superadmin', 1)
        ")->execute([$hash]);
    }

    // Email the temp password
    try {
        $mail = authSmtp();
        $mail->addAddress('alhindtrust@gmail.com', 'AL Hind Admin');
        $mail->isHTML(true);
        $mail->Subject = 'Admin Panel — Temporary Password';
        $mail->Body    = "
<table width='520' align='center' cellpadding='0' cellspacing='0'
  style='font-family:Segoe UI,Arial,sans-serif;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;'>
  <tr><td style='background:#0f766e;padding:18px 24px;'>
    <h2 style='color:#fff;margin:0;font-size:1.1rem;'>AL Hind Trust — Admin Panel</h2>
    <p style='color:#ccfbf1;margin:4px 0 0;font-size:.8rem;'>Password Reset Requested</p>
  </td></tr>
  <tr><td style='padding:24px;color:#1e293b;font-size:14px;line-height:1.7;'>
    <p>A password reset was requested for the Admin Panel.</p>
    <p>Your temporary password is:</p>
    <div style='background:#f0fdf9;border:2px dashed #0f766e;border-radius:10px;
                padding:16px;text-align:center;margin:16px 0;'>
      <span style='font-size:1.4rem;font-weight:700;color:#0f766e;
                   letter-spacing:.12em;font-family:monospace;'>{$tempPwd}</span>
    </div>
    <p style='color:#64748b;font-size:13px;'>
      Log in using your <strong>password only</strong> on the admin panel.<br>
      Please change your password after logging in.
    </p>
    <p style='color:#94a3b8;font-size:12px;margin-top:16px;'>
      If you did not request this, please ignore this email.
    </p>
  </td></tr>
  <tr><td style='background:#f8fafc;text-align:center;padding:12px;font-size:12px;color:#94a3b8;'>
    AL Hind Educational and Charitable Trust, Madhepura, Bihar
  </td></tr>
</table>";
        $mail->AltBody = "Your temporary admin panel password is: {$tempPwd}\nIf you did not request this, ignore this email.";
        $mail->send();
    } catch (Exception $e) {
        error_log('Forgot password email failed: ' . $e->getMessage());
        error('Failed to send reset email. Please try again.', 500);
    }

    ok(null, 'Temporary password sent to alhindtrust@gmail.com');
}

/* ── SMTP helper ─────────────────────────────────────────────── */
function authSmtp(): PHPMailer {
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
