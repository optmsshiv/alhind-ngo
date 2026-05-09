<?php
/**
 * AL Hind Trust — Mail Diagnostic & Test Script
 * ------------------------------------------------
 * Upload this file to your server (same folder as your API).
 * Open it in browser: https://api.alhindtrust.com/mailtest.php
 * DELETE IT after testing — do not leave on production!
 */

// ── CONFIG ────────────────────────────────────────────────────
$TEST_TO      = 'alhindtrust@gmail.com';   // where to send the test
$SMTP_HOST    = 'smtp.gmail.com';
$SMTP_PORT    = 587;
$SMTP_USER    = 'alhindtrust@gmail.com';
$SMTP_PASS    = 'yyym lxhp pyro alyk';     // your Gmail App Password
$FROM_EMAIL   = 'alhindtrust@gmail.com';
$FROM_NAME    = 'AL Hind Trust';
// ─────────────────────────────────────────────────────────────

$results = [];

// ── HELPER ───────────────────────────────────────────────────
function row(string $label, $value, string $status = 'info'): string {
    $colors = ['ok' => '#dcfce7', 'fail' => '#fee2e2', 'info' => '#f8fafc', 'warn' => '#fef9c3'];
    $icons  = ['ok' => '✅', 'fail' => '❌', 'info' => 'ℹ️', 'warn' => '⚠️'];
    $bg     = $colors[$status] ?? '#f8fafc';
    $icon   = $icons[$status]  ?? '';
    return "<tr style='background:{$bg}'>
              <td style='padding:8px 14px;font-weight:600;color:#334155;width:240px'>{$label}</td>
              <td style='padding:8px 14px;font-family:monospace;font-size:13px'>{$icon} " . htmlspecialchars((string)$value) . "</td>
            </tr>";
}

ob_start();

// ════════════════════════════════════════════════════════════
// 1. ENVIRONMENT INFO
// ════════════════════════════════════════════════════════════
echo "<h2>1. Server Environment</h2><table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden'>";
echo row('PHP Version',        phpversion(),               version_compare(phpversion(), '7.4', '>=') ? 'ok' : 'warn');
echo row('Server Software',    $_SERVER['SERVER_SOFTWARE'] ?? 'unknown');
echo row('sendmail_path',      ini_get('sendmail_path') ?: '(empty)',   ini_get('sendmail_path') ? 'ok' : 'warn');
echo row('SMTP (php.ini)',      ini_get('SMTP')          ?: '(empty)');
echo row('smtp_port (php.ini)',ini_get('smtp_port')      ?: '(empty)');
echo row('disable_functions',  ini_get('disable_functions') ?: '(none)');
$mailDisabled = in_array('mail', array_map('trim', explode(',', ini_get('disable_functions'))));
echo row('mail() disabled?',   $mailDisabled ? 'YES — mail() is blocked on this server!' : 'No', $mailDisabled ? 'fail' : 'ok');
echo "</table>";

// ════════════════════════════════════════════════════════════
// 2. PHPMailer / Composer CHECK
// ════════════════════════════════════════════════════════════
echo "<h2>2. PHPMailer Detection</h2><table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden'>";
$autoload = __DIR__ . '/vendor/autoload.php';
$pmBase   = __DIR__ . '/phpmailer/src/PHPMailer.php';
echo row('vendor/autoload.php exists?', file_exists($autoload) ? 'YES' : 'NO', file_exists($autoload) ? 'ok' : 'warn');
echo row('phpmailer/src/PHPMailer.php?', file_exists($pmBase)  ? 'YES' : 'NO', file_exists($pmBase)  ? 'ok' : 'warn');

if (file_exists($autoload)) {
    require_once $autoload;
} elseif (file_exists($pmBase)) {
    require_once __DIR__ . '/phpmailer/src/Exception.php';
    require_once __DIR__ . '/phpmailer/src/SMTP.php';
    require_once $pmBase;
}
$hasPM = class_exists('PHPMailer\PHPMailer\PHPMailer');
echo row('PHPMailer class loadable?', $hasPM ? 'YES' : 'NO', $hasPM ? 'ok' : 'warn');
echo "</table>";

// ════════════════════════════════════════════════════════════
// 3. TEST 1 — PHP mail()
// ════════════════════════════════════════════════════════════
echo "<h2>3. Test A — PHP <code>mail()</code></h2>";
if ($mailDisabled) {
    echo "<p style='color:#b91c1c'>⛔ mail() is disabled on this server. Skip to Test B (SMTP).</p>";
} else {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n";
    $headers .= "Reply-To: {$FROM_EMAIL}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $body = "<h3>Test email via PHP mail()</h3><p>Sent at: " . date('d M Y H:i:s') . "</p><p>Server: " . ($_SERVER['SERVER_NAME'] ?? 'unknown') . "</p>";

    $ok = @mail($TEST_TO, 'Test A: PHP mail() — AL Hind Trust', $body, $headers);
    echo "<table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden'>";
    echo row('mail() return value', $ok ? 'TRUE (queued — check inbox/spam)' : 'FALSE (failed immediately)', $ok ? 'ok' : 'fail');
    echo "</table>";
    if ($ok) echo "<p style='color:#166534'>✅ mail() accepted the message. Check <strong>{$TEST_TO}</strong> inbox and spam folder.</p>";
    else     echo "<p style='color:#991b1b'>❌ mail() returned false. Your server cannot send mail this way. Use SMTP (Test B).</p>";
}

// ════════════════════════════════════════════════════════════
// 4. TEST 2 — SMTP via PHPMailer
// ════════════════════════════════════════════════════════════
echo "<h2>4. Test B — SMTP via PHPMailer</h2>";
if (!$hasPM) {
    echo "<p style='color:#92400e'>⚠️ PHPMailer not found. To install it, run in your <code>/api/</code> folder:<br>
          <code style='background:#f1f5f9;padding:4px 8px;border-radius:4px'>composer require phpmailer/phpmailer</code></p>";
} else {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER;
        $mail->Password   = $SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = 0; // set to 2 for full debug output
        $mail->setFrom($FROM_EMAIL, $FROM_NAME);
        $mail->addAddress($TEST_TO);
        $mail->isHTML(true);
        $mail->Subject = 'Test B: SMTP PHPMailer — AL Hind Trust';
        $mail->Body    = "<h3>Test email via SMTP</h3><p>Sent at: " . date('d M Y H:i:s') . "</p>";
        $mail->AltBody = "Test email via SMTP. Sent at: " . date('d M Y H:i:s');
        $mail->send();
        echo "<table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden'>";
        echo row('SMTP send result', 'SUCCESS', 'ok');
        echo "</table>";
        echo "<p style='color:#166534'>✅ SMTP works! Check <strong>{$TEST_TO}</strong> inbox.</p>";
    } catch (\Exception $e) {
        echo "<table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden'>";
        echo row('SMTP Error', $e->getMessage(), 'fail');
        echo "</table>";
        echo "<p style='color:#991b1b'>❌ SMTP failed. See error above. Common causes:</p>
              <ul>
                <li>App Password revoked — generate a new one at <a href='https://myaccount.google.com/apppasswords' target='_blank'>myaccount.google.com/apppasswords</a></li>
                <li>Port 587 blocked by host — try port 465 with SMTPSecure = 'ssl'</li>
                <li>Google account 2FA not enabled (App Passwords require 2FA)</li>
              </ul>";
    }
}

// ════════════════════════════════════════════════════════════
// 5. PORT CONNECTIVITY
// ════════════════════════════════════════════════════════════
echo "<h2>5. Network — Can server reach Gmail SMTP?</h2><table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden'>";
foreach ([587 => 'STARTTLS', 465 => 'SSL', 25 => 'Plain (usually blocked)'] as $port => $label) {
    $conn = @fsockopen('smtp.gmail.com', $port, $errno, $errstr, 5);
    if ($conn) {
        fclose($conn);
        echo row("smtp.gmail.com:{$port} ({$label})", 'OPEN ✅', 'ok');
    } else {
        echo row("smtp.gmail.com:{$port} ({$label})", "BLOCKED ❌ — {$errstr}", 'fail');
    }
}
echo "</table>";

$html = ob_get_clean();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Mail Diagnostic — AL Hind Trust</title>
  <style>
    body { font-family: Segoe UI, sans-serif; max-width: 820px; margin: 32px auto; padding: 0 16px; color: #1e293b; }
    h1   { color: #0f766e; border-bottom: 2px solid #0f766e; padding-bottom: 8px; }
    h2   { color: #0f766e; margin-top: 32px; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
    .warn-box { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px 16px; margin-top: 24px; color: #92400e; }
  </style>
</head>
<body>
  <h1>🔧 Mail Diagnostic — AL Hind Trust</h1>
  <div class="warn-box">⚠️ <strong>Delete this file after testing!</strong> It contains your SMTP password.</div>
  <?= $html ?>
</body>
</html>
