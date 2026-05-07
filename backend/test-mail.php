<?php
// ============================================================
//  test-mail.php — TEMPORARY email diagnostic
//  Upload to /public_html/backend/ and open in browser
//  DELETE after testing!
// ============================================================
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>AL Hind — Email Diagnostic</h2>";
echo "<p><b>PHP Version:</b> " . PHP_VERSION . "</p>";
echo "<p><b>Server:</b> " . ($_SERVER['SERVER_NAME'] ?? 'unknown') . "</p>";
echo "<hr>";

// ── Test 1: Is mail() function available? ─────────────────────
echo "<h3>Test 1: mail() availability</h3>";
if (function_exists('mail')) {
    echo "<p style='color:green'>✅ mail() function EXISTS</p>";
} else {
    echo "<p style='color:red'>❌ mail() is DISABLED on this server — you must use SMTP</p>";
}

// ── Test 2: Check sendmail path ───────────────────────────────
echo "<h3>Test 2: Sendmail path</h3>";
$sendmailPath = ini_get('sendmail_path');
echo "<p><b>sendmail_path:</b> " . ($sendmailPath ?: '(empty — not configured)') . "</p>";
if ($sendmailPath) {
    echo "<p style='color:green'>✅ Sendmail path configured</p>";
} else {
    echo "<p style='color:orange'>⚠ No sendmail path — mail() may still work via hosting config</p>";
}

// ── Test 3: Send a real test email ───────────────────────────
echo "<h3>Test 3: Send actual test email</h3>";

// CHANGE THIS to your own email to receive the test
$testTo = 'sr21er@gmail.com'; // ← donor's email from your screenshot

$subject = 'AL Hind Mail Test - ' . date('H:i:s');
$message = "This is a test email from AL Hind Trust server.\n\nTime: " . date('Y-m-d H:i:s') . "\nServer: " . ($_SERVER['SERVER_NAME'] ?? 'unknown');

$headers  = "From: AL Hind Trust <info@alhindtrust.com>\r\n";
$headers .= "Reply-To: info@alhindtrust.com\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . PHP_VERSION;

$result = mail($testTo, $subject, $message, $headers);

if ($result) {
    echo "<p style='color:green'>✅ mail() returned TRUE — email was accepted by server</p>";
    echo "<p>Check <b>{$testTo}</b> inbox (and spam folder!) for subject: <b>{$subject}</b></p>";
} else {
    echo "<p style='color:red'>❌ mail() returned FALSE — server REJECTED the email</p>";
    echo "<p>This means mail() is blocked or misconfigured on your hosting.</p>";
}

// ── Test 4: Check TCPDF ───────────────────────────────────────
echo "<h3>Test 4: TCPDF check</h3>";
$tcpdfPath = __DIR__ . '/tcpdf/tcpdf.php';
if (file_exists($tcpdfPath)) {
    echo "<p style='color:green'>✅ TCPDF found at: {$tcpdfPath}</p>";
    try {
        require_once $tcpdfPath;
        $pdf = new TCPDF();
        echo "<p style='color:green'>✅ TCPDF loaded successfully — PDF generation will work</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>❌ TCPDF load error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ TCPDF NOT found at: {$tcpdfPath}</p>";
    echo "<p>Check that tcpdf/tcpdf.php exists inside /public_html/backend/</p>";
}

// ── Test 5: Check send-receipt.php ────────────────────────────
echo "<h3>Test 5: send-receipt.php check</h3>";
$receiptFile = __DIR__ . '/send-receipt.php';
if (file_exists($receiptFile)) {
    echo "<p style='color:green'>✅ send-receipt.php found</p>";
} else {
    echo "<p style='color:red'>❌ send-receipt.php NOT found at: {$receiptFile}</p>";
}

// ── Test 6: PHP error log location ───────────────────────────
echo "<h3>Test 6: Error log location</h3>";
$logPath = ini_get('error_log');
echo "<p><b>error_log path:</b> " . ($logPath ?: '(not set — check cPanel error logs)') . "</p>";
if ($logPath && file_exists($logPath)) {
    $lastLines = array_slice(file($logPath), -20);
    $alhindLines = array_filter($lastLines, fn($l) => str_contains($l, 'AL Hind'));
    if ($alhindLines) {
        echo "<p><b>Recent AL Hind log entries:</b></p><pre style='background:#f1f5f9;padding:10px;font-size:11px'>";
        echo htmlspecialchars(implode('', $alhindLines));
        echo "</pre>";
    } else {
        echo "<p style='color:orange'>⚠ No AL Hind entries in error log yet (make a test donation first)</p>";
    }
}

echo "<hr><p style='color:red;font-weight:bold'>⚠ DELETE test-mail.php after you're done!</p>";
