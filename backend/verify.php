<?php
// ============================================================
//  backend/verify.php — Razorpay payment verification
//  AL Hind Educational and Charitable Trust
//  Uses hash_hmac only — NO Razorpay PHP SDK
//  Called after Razorpay checkout succeeds:
//    verify.php?ticket=TKT-XXXX&razorpay_payment_id=...
//              &razorpay_order_id=...&razorpay_signature=...
// ============================================================
date_default_timezone_set('Asia/Kolkata');

// DB: backend/ is inside public_html/, so config/db.php is one level up
require_once __DIR__ . '/../config/db.php';

// ── PHPMailer ────────────────────────────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

// ── Razorpay credentials ─────────────────────────────────────
// define('RZP_KEY_ID',     'rzp_live_SmY6H2HIaVOr6Q');
// define('RZP_KEY_SECRET', '3VXI0InXLgL9BlO4B19kroDj');

define('RZP_KEY_ID',     'rzp_test_SnKA1cM9qun4jI');
define('RZP_KEY_SECRET', 'rv1mU7vdQtKQ7uUbGeMyUMe6');

// ════════════════════════════════════════════════════════════
//  1. VALIDATE INPUTS
// ════════════════════════════════════════════════════════════

$ticket    = trim($_GET['ticket']            ?? '');
$paymentId = trim($_GET['razorpay_payment_id']  ?? '');
$orderId   = trim($_GET['razorpay_order_id']    ?? '');
$signature = trim($_GET['razorpay_signature']   ?? '');

if (!$ticket || !$paymentId || !$orderId || !$signature) {
    showError('Invalid payment response. Please contact support.');
}

// ════════════════════════════════════════════════════════════
//  2. VERIFY RAZORPAY SIGNATURE
// ════════════════════════════════════════════════════════════

$expectedSig = hash_hmac('sha256', $orderId . '|' . $paymentId, RZP_KEY_SECRET);

if (!hash_equals($expectedSig, $signature)) {
    error_log("[AL Hind] Signature mismatch for ticket {$ticket}");
    showError('Payment verification failed. Please contact support with your ticket ID: ' . htmlspecialchars($ticket));
}

// ════════════════════════════════════════════════════════════
//  3. FETCH RECORD FROM DB  (PDO — same as create-order.php)
// ════════════════════════════════════════════════════════════

try {
    $pdo = getDB();
    $pdo->exec("SET time_zone = '+05:30'");

    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, interest, joining_fee, status, member_id
        FROM ngo_inquiries
        WHERE ticket_id = ?
        LIMIT 1
    ");
    $stmt->execute([$ticket]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[AL Hind] verify.php DB fetch failed: ' . $e->getMessage());
    showError('Database error. Please contact support.');
}

if (!$row) {
    showError('Ticket not found. Please contact support.');
}

// ── Already processed? Show success page again (idempotent) ──
if ($row['status'] === 'approved' && !empty($row['member_id'])) {
    showSuccess($row['name'], $row['member_id'], $row['email'], true);
}

if ($row['status'] !== 'payment_pending') {
    showError('This payment link is no longer valid.');
}

// ════════════════════════════════════════════════════════════
//  4. GENERATE MEMBER ID  (MEM-0001, MEM-0002, …)
// ════════════════════════════════════════════════════════════

// Atomic transaction: get next sequence, update in one go
$pdo->beginTransaction();

try {
    // Get current max member number
    $r  = $pdo->query("SELECT MAX(CAST(SUBSTRING(member_id, 5) AS UNSIGNED)) AS mx FROM ngo_inquiries WHERE member_id IS NOT NULL");
    $mx = (int)($r->fetch(PDO::FETCH_ASSOC)['mx'] ?? 0);
    $memberId = 'MEM-' . str_pad($mx + 1, 4, '0', STR_PAD_LEFT); // MEM-0001

    // Update record: mark approved, save payment details, assign member ID
    $pdo->prepare("
        UPDATE ngo_inquiries
        SET status              = 'approved',
            member_id           = ?,
            razorpay_payment_id = ?,
            razorpay_order_id   = ?,
            razorpay_signature  = ?,
            paid_at             = NOW()
        WHERE ticket_id = ?
    ")->execute([$memberId, $paymentId, $orderId, $signature, $ticket]);

    // Also update contact_messages table
    $pdo->prepare("
        UPDATE contact_messages
        SET status = 'approved', updated_at = NOW()
        WHERE ticket_id = ?
    ")->execute([$ticket]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[AL Hind] DB error during member approval: ' . $e->getMessage());
    showError('A database error occurred. Your payment was received — please contact support with ticket: ' . htmlspecialchars($ticket));
}

// ════════════════════════════════════════════════════════════
//  5. SEND JOINING LETTER + ID CARD EMAIL
// ════════════════════════════════════════════════════════════

sendMemberWelcomeEmail($row, $memberId, $ticket);

// ════════════════════════════════════════════════════════════
//  6. SHOW SUCCESS PAGE
// ════════════════════════════════════════════════════════════

showSuccess($row['name'], $memberId, $row['email'], false);


// ════════════════════════════════════════════════════════════
//  FUNCTIONS
// ════════════════════════════════════════════════════════════

/**
 * Send joining letter + ID card email to new member
 */
function sendMemberWelcomeEmail(array $row, string $memberId, string $ticketId): void {
    $name  = $row['name'];
    $email = $row['email'];
    $phone = $row['phone'] ?? '';
    $fee   = (int)$row['joining_fee'];

    if (empty($email)) return;

    $subject = "Welcome to AL Hind Trust — Member {$memberId} 🎉";

    // Generate initials for avatar
    $parts    = explode(' ', trim($name));
    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    $joinDate = date('d M Y');
    $joinYear = date('Y');
    $role     = ucfirst($row['interest'] ?? 'Member');

    $html = emailWrap("
        <h2 style='color:#0f766e;margin-top:0'>Welcome to AL Hind Trust, {$name}! 🎉</h2>
        <p>Your membership has been <strong style='color:#0f766e'>confirmed</strong>.
           Thank you for your joining contribution of <strong>₹{$fee}</strong>.</p>
        <p>Your membership details and ID card are below. Please save this email for your records.</p>

        <!-- ══ MEMBER ID CARD ══ -->
        <table width='100%' cellpadding='0' cellspacing='0' style='max-width:420px;margin:28px auto;border:1.5px solid #0f766e;border-radius:14px;overflow:hidden;font-family:Segoe UI,Arial,sans-serif'>

            <!-- Card header -->
            <tr>
                <td style='background:#0f766e;padding:20px 24px'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                            <!-- Amber initials circle -->
                            <td width='68' valign='middle'>
                                <div style='width:60px;height:60px;border-radius:50%;
                                            background:#EF9F27;border:2.5px solid #FAC775;
                                            text-align:center;line-height:60px;
                                            font-size:22px;font-weight:700;
                                            color:#412402;font-family:Segoe UI,Arial,sans-serif'>
                                    {$initials}
                                </div>
                            </td>
                            <!-- Name + subtitle + ID badge -->
                            <td valign='middle' style='padding-left:14px'>
                                <div style='color:#ffffff;font-size:17px;font-weight:600;letter-spacing:0.2px'>{$name}</div>
                                <div style='color:rgba(255,255,255,0.75);font-size:12px;margin-top:3px'>Life Member &middot; AL Hind Trust</div>
                                <!-- Amber ID badge -->
                                <div style='margin-top:8px;display:inline-block;background:#EF9F27;
                                            padding:3px 10px;border-radius:6px'>
                                    <span style='color:#412402;font-size:11px;font-weight:700'>ID</span>
                                    <span style='color:#BA7517;font-size:11px;margin:0 4px'>|</span>
                                    <span style='color:#412402;font-size:12px;font-weight:600;
                                                letter-spacing:1px;font-family:Courier New,monospace'>{$memberId}</span>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <!-- Card body -->
            <tr>
                <td style='background:#ffffff;padding:16px 24px'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='font-size:13px'>
                        <tr>
                            <td style='padding:9px 0;color:#64748b;width:38%'>Organisation</td>
                            <td style='padding:9px 0;font-weight:600;color:#1e293b'>AL Hind Educational &amp; Charitable Trust</td>
                        </tr>
                        <tr>
                            <td style='padding:9px 0;color:#64748b;border-top:1px solid #f1f5f9'>Role</td>
                            <td style='padding:9px 0;font-weight:600;color:#1e293b;border-top:1px solid #f1f5f9'>{$role}</td>
                        </tr>
                        <tr>
                            <td style='padding:9px 0;color:#64748b;border-top:1px solid #f1f5f9'>Phone</td>
                            <td style='padding:9px 0;font-weight:600;color:#1e293b;border-top:1px solid #f1f5f9'>{$phone}</td>
                        </tr>
                        <tr>
                            <td style='padding:9px 0;color:#64748b;border-top:1px solid #f1f5f9'>Member Since</td>
                            <td style='padding:9px 0;font-weight:600;color:#1e293b;border-top:1px solid #f1f5f9'>{$joinDate}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <!-- Card footer -->
            <tr>
                <td style='background:#f8fafc;border-top:1px solid #e2e8f0;padding:10px 24px'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                            <td style='font-size:12px;color:#64748b'>alhindtrust.com &middot; Madhepura, Bihar</td>
                            <td align='right'>
                                <span style='background:#dcfce7;color:#15803d;font-size:11px;
                                             font-weight:600;padding:3px 10px;border-radius:6px'>
                                    &#10003; Valid Life Member
                                </span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

        </table>

        <!-- ══ JOINING LETTER ══ -->
        <div style='border:1px solid #e2e8f0;border-radius:10px;padding:24px;
                    margin:24px 0;background:#fafafa;font-size:14px;color:#1e293b;
                    line-height:1.7'>
            <p style='margin:0 0 12px;font-size:12px;color:#94a3b8'>
                Ref: {$ticketId} &nbsp;·&nbsp; Date: {$joinDate}
            </p>
            <p style='margin:0 0 14px'>Dear <strong>{$name}</strong>,</p>
            <p style='margin:0 0 14px'>
                On behalf of the Board of Trustees of <strong>AL Hind Educational &amp; Charitable Trust</strong>,
                we are delighted to welcome you as a <strong>Life Member</strong> of our organisation,
                effective <strong>{$joinDate}</strong>.
            </p>
            <p style='margin:0 0 14px'>
                Your membership number is <strong style='font-family:monospace;color:#0f766e'>{$memberId}</strong>.
                Please quote this number in all future correspondence with us.
            </p>
            <p style='margin:0 0 14px'>
                As a member, you are an integral part of our mission to promote education,
                healthcare, and community welfare in Madhepura and beyond. We look forward
                to your active participation in our programs and initiatives.
            </p>
            <p style='margin:0 0 24px'>
                Once again, welcome aboard. Together, we can make a meaningful difference.
            </p>
            <p style='margin:0'>
                Yours sincerely,<br><br>
                <strong>Secretary</strong><br>
                AL Hind Educational &amp; Charitable Trust<br>
                Kohinoor Complex, College Chowk, Madhepura – 852113, Bihar<br>
                <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a>
                &nbsp;·&nbsp; +91-9263190568
            </p>
        </div>

        <p style='font-size:12px;color:#94a3b8;text-align:center'>
            Keep this email as proof of membership · AL Hind Trust © {$joinYear}
        </p>
    ");

    $plain = "Welcome to AL Hind Trust, {$name}!\n\n"
           . "Your membership has been confirmed.\n\n"
           . "Member ID   : {$memberId}\n"
           . "Member Since: {$joinDate}\n"
           . "Phone       : {$phone}\n\n"
           . "Please quote your Member ID ({$memberId}) in all future correspondence.\n\n"
           . "AL Hind Trust | alhindtrust@gmail.com | +91-9263190568\n"
           . "Kohinoor Complex, College Chowk, Madhepura – 852113, Bihar";

    sendVerifyMail($email, $name, $subject, $html, $plain);
}

/**
 * PHPMailer sender (same SMTP config as contact.php)
 */
function sendVerifyMail(string $toEmail, string $toName, string $subject, string $html, string $plain): bool {
    $autoload = '/home/u699609112/domains/alhindtrust.com/public_html/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('[AL Hind] PHPMailer not found');
        return false;
    }
    require_once $autoload;
    try {
        $mail             = new \PHPMailer\PHPMailer\PHPMailer(true);
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
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $plain;
        $mail->send();
        error_log('[AL Hind] Welcome email sent → ' . $toEmail);
        return true;
    } catch (\Exception $e) {
        error_log('[AL Hind] Welcome email FAILED → ' . $toEmail . ' | ' . $e->getMessage());
        return false;
    }
}

/**
 * Shared HTML email wrapper
 */
function emailWrap(string $content): string {
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,sans-serif'>
  <div style='max-width:580px;margin:32px auto;background:#fff;border-radius:14px;
              overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)'>
    <div style='background:#0f766e;padding:20px 28px'>
      <div style='color:#fff;font-weight:700;font-size:17px'>AL Hind Educational &amp; Charitable Trust</div>
      <div style='font-size:12px;color:rgba(255,255,255,.75);margin-top:2px'>Madhepura, Bihar · alhindtrust.com</div>
    </div>
    <div style='padding:28px'>{$content}</div>
    <div style='background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 28px;
                font-size:12px;color:#94a3b8;text-align:center'>
      AL Hind Educational &amp; Charitable Trust · Kohinoor Complex, College Chowk, Madhepura – 852113, Bihar<br>
      <a href='mailto:alhindtrust@gmail.com' style='color:#0f766e'>alhindtrust@gmail.com</a>
      &nbsp;·&nbsp; <a href='tel:+919263190568' style='color:#0f766e'>+91-9263190568</a>
    </div>
  </div>
</body></html>";
}

/**
 * Show success HTML page to the user
 */
function showSuccess(string $name, string $memberId, string $email, bool $duplicate): void {
    $msg = $duplicate
        ? 'Your membership was already confirmed. Details were emailed to you.'
        : 'Payment verified! Your joining letter and Member ID card have been emailed to <strong>' . htmlspecialchars($email) . '</strong>.';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Membership Confirmed — AL Hind Trust</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0 }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; padding: 20px }
        .card { background: #fff; border-radius: 18px; padding: 40px 36px; max-width: 480px;
                width: 100%; box-shadow: 0 8px 40px rgba(0,0,0,.10); text-align: center }
        .icon { width: 72px; height: 72px; border-radius: 50%; background: #dcfce7;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 20px; font-size: 36px }
        h1 { font-size: 1.5rem; color: #0f766e; margin-bottom: 10px }
        .member-id { display: inline-block; background: #f0fdf4; border: 2px solid #0f766e;
                     color: #0f766e; font-family: monospace; font-size: 1.4rem; font-weight: 800;
                     padding: 8px 24px; border-radius: 8px; margin: 16px 0; letter-spacing: 2px }
        p { color: #475569; font-size: .92rem; line-height: 1.6; margin-top: 12px }
        .note { font-size: .8rem; color: #94a3b8; margin-top: 20px }
        a.home { display: inline-block; margin-top: 24px; padding: 10px 28px;
                 background: #0f766e; color: #fff; border-radius: 8px; text-decoration: none;
                 font-weight: 700; font-size: .9rem }
        a.home:hover { background: #0d6561 }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">🎉</div>
    <h1>Welcome, <?= htmlspecialchars($name) ?>!</h1>
    <p>Your Member ID is</p>
    <div class="member-id"><?= htmlspecialchars($memberId) ?></div>
    <p><?= $msg ?></p>
    <p class="note">Please save your Member ID for all future correspondence with AL Hind Trust.</p>
    <a class="home" href="https://alhindtrust.com">← Back to Website</a>
</div>
</body>
</html>
<?php
    exit;
}

/**
 * Show error HTML page
 */
function showError(string $msg): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Payment Error — AL Hind Trust</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0 }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; padding: 20px }
        .card { background: #fff; border-radius: 18px; padding: 40px 36px; max-width: 460px;
                width: 100%; box-shadow: 0 8px 40px rgba(0,0,0,.10); text-align: center }
        .icon { width: 72px; height: 72px; border-radius: 50%; background: #fee2e2;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 20px; font-size: 36px }
        h1 { font-size: 1.4rem; color: #dc2626; margin-bottom: 12px }
        p { color: #475569; font-size: .92rem; line-height: 1.6 }
        a.home { display: inline-block; margin-top: 24px; padding: 10px 28px;
                 background: #0f766e; color: #fff; border-radius: 8px; text-decoration: none;
                 font-weight: 700 }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">❌</div>
    <h1>Something went wrong</h1>
    <p><?= htmlspecialchars($msg) ?></p>
    <p style="margin-top:12px;font-size:.8rem;color:#94a3b8">
        Contact us at <a href="mailto:alhindtrust@gmail.com" style="color:#0f766e">alhindtrust@gmail.com</a>
        or call <a href="tel:+919263190568" style="color:#0f766e">+91-9263190568</a>
    </p>
    <a class="home" href="https://alhindtrust.com">← Back to Website</a>
</div>
</body>
</html>
<?php
    exit;
}