<?php
// ============================================================
//  send-receipt.php — 80G Donation Receipt Generator & Mailer
//  AL Hind Educational and Charitable Trust
//  Generates PDF receipt and emails it to donor
//  Uses: TCPDF (single file, no Composer) + PHP mail()
// ============================================================
date_default_timezone_set('Asia/Kolkata');

// ── NGO Details ───────────────────────────────────────────────
define('NGO_NAME',        'AL Hind Educational and Charitable Trust');
define('NGO_PAN',         'AACTA6326K');
define('NGO_80G_REG',     'AACTA6326KF20261');
define('NGO_ADDRESS',     'Ward No. 04, Kohinoor Complex, College Chowk, Madhepura, Bihar');
define('NGO_EMAIL',       'info@alhindtrust.com');
define('NGO_WEBSITE',     'www.alhindtrust.com');
define('NGO_DARPAN',      'BR/2025/0937487');
// 80G validity — update when renewed
define('NGO_80G_VALID_FROM', '01/04/2026');
define('NGO_80G_VALID_TO',   '31/03/2027');

/**
 * Convert number to Indian words
 */
function numberToWords(float $num): string {
    $num    = (int)$num;
    $ones   = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven',
               'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen',
               'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens   = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty',
               'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($num === 0) return 'Zero';

    $words = '';
    if ($num >= 10000000) { // crore
        $words .= numberToWords((int)($num / 10000000)) . ' Crore ';
        $num    %= 10000000;
    }
    if ($num >= 100000) { // lakh
        $words .= numberToWords((int)($num / 100000)) . ' Lakh ';
        $num    %= 100000;
    }
    if ($num >= 1000) { // thousand
        $words .= numberToWords((int)($num / 1000)) . ' Thousand ';
        $num    %= 1000;
    }
    if ($num >= 100) {
        $words .= $ones[(int)($num / 100)] . ' Hundred ';
        $num    %= 100;
    }
    if ($num >= 20) {
        $words .= $tens[(int)($num / 10)] . ' ';
        $num    %= 10;
    }
    if ($num > 0) {
        $words .= $ones[$num] . ' ';
    }
    return trim($words);
}

/**
 * Generate the PDF receipt using TCPDF
 * Returns raw PDF bytes as string
 */
function generateReceiptPDF(array $d): string {
    // ── Load TCPDF ────────────────────────────────────────────
    // TCPDF must be in /backend/tcpdf/ — see README
    $tcpdfPath = __DIR__ . '/tcpdf/tcpdf.php';
    if (!file_exists($tcpdfPath)) {
        throw new RuntimeException('TCPDF not found at ' . $tcpdfPath);
    }
    require_once $tcpdfPath;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(NGO_NAME);
    $pdf->SetAuthor(NGO_NAME);
    $pdf->SetTitle('Donation Receipt - ' . $d['receipt_no']);
    $pdf->SetSubject('80G Donation Receipt');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // ── Color palette ─────────────────────────────────────────
    $teal   = [15,  118, 110]; // #0f766e
    $dark   = [15,   23,  42]; // #0f172a
    $gray   = [71,   85, 105]; // #475569
    $light  = [241, 245, 249]; // #f1f5f9
    $white  = [255, 255, 255];
    $green  = [22,  163, 74];  // #16a34a

    // ── Header band ───────────────────────────────────────────
    $pdf->SetFillColor(...$teal);
    $pdf->Rect(0, 0, 210, 38, 'F');

    $pdf->SetTextColor(...$white);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetXY(15, 8);
    $pdf->Cell(0, 8, NGO_NAME, 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(15, 17);
    $pdf->Cell(0, 5, NGO_ADDRESS, 0, 1, 'L');
    $pdf->SetXY(15, 22);
    $pdf->Cell(0, 5, 'PAN: ' . NGO_PAN . '   |   DARPAN: ' . NGO_DARPAN . '   |   ' . NGO_WEBSITE, 0, 1, 'L');

    // RECEIPT label (right side of header)
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->SetXY(130, 7);
    $pdf->Cell(65, 12, 'DONATION', 0, 1, 'R');
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetXY(130, 19);
    $pdf->Cell(65, 7, 'RECEIPT', 0, 1, 'R');

    // ── 80G Badge strip ───────────────────────────────────────
    $pdf->SetFillColor(6, 78, 59); // darker green
    $pdf->Rect(0, 38, 210, 8, 'F');
    $pdf->SetTextColor(...$white);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY(0, 39.5);
    $pdf->Cell(210, 5,
        'Tax Exemption under Section 80G of Income Tax Act, 1961  |  Reg. No: ' . NGO_80G_REG .
        '  |  Valid: ' . NGO_80G_VALID_FROM . ' to ' . NGO_80G_VALID_TO,
        0, 1, 'C');

    // ── Receipt meta row ─────────────────────────────────────
    $pdf->SetFillColor(...$light);
    $pdf->Rect(15, 50, 180, 14, 'F');
    $pdf->SetTextColor(...$dark);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY(20, 52);
    $pdf->Cell(55, 5, 'Receipt No:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(35, 5, $d['receipt_no'], 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 5, 'Date:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, $d['date'], 0, 1, 'L');

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY(20, 58);
    $pdf->Cell(55, 5, 'Payment ID:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, $d['payment_id'], 0, 1, 'L');

    // ── Donor Details ─────────────────────────────────────────
    $pdf->SetTextColor(...$teal);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(15, 70);
    $pdf->Cell(0, 6, 'DONOR DETAILS', 0, 1, 'L');

    // underline
    $pdf->SetDrawColor(...$teal);
    $pdf->Line(15, 77, 195, 77);

    $pdf->SetTextColor(...$dark);
    $y = 80;
    $rows = [
        ['Donor Name',    $d['donor_name']],
        ['Email Address', $d['donor_email']],
        ['Payment Mode',  $d['payment_method']],
    ];
    foreach ($rows as [$label, $value]) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(15, $y);
        $pdf->Cell(50, 7, $label . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 7, $value, 0, 1, 'L');
        $y += 7;
    }

    // ── Amount Box ────────────────────────────────────────────
    $pdf->SetFillColor(...$teal);
    $pdf->RoundedRect(15, $y + 5, 180, 30, 4, '1111', 'F');

    $pdf->SetTextColor(...$white);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(15, $y + 9);
    $pdf->Cell(180, 6, 'DONATION AMOUNT', 0, 1, 'C');

    $pdf->SetFont('helvetica', 'B', 28);
    $pdf->SetXY(15, $y + 15);
    $amtFormatted = 'Rs. ' . number_format((float)$d['amount'], 2);
    $pdf->Cell(180, 12, $amtFormatted, 0, 1, 'C');

    // Amount in words
    $words = numberToWords((float)$d['amount']) . ' Rupees Only';
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetXY(15, $y + 5 + 26);
    $pdf->Cell(180, 5, '(' . $words . ')', 0, 1, 'C');

    $y += 42;

    // ── 80G Tax Info box ─────────────────────────────────────
    $pdf->SetFillColor(...$light);
    $pdf->RoundedRect(15, $y + 5, 180, 28, 3, '1111', 'F');
    $pdf->SetDrawColor(...$teal);
    $pdf->RoundedRect(15, $y + 5, 180, 28, 3, '1111', 'D');

    $pdf->SetTextColor(...$teal);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY(20, $y + 9);
    $pdf->Cell(0, 5, 'TAX EXEMPTION INFORMATION (Section 80G)', 0, 1, 'L');

    $pdf->SetTextColor(...$gray);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(20, $y + 15);
    $pdf->MultiCell(170, 5,
        "This donation qualifies for tax deduction under Section 80G of the Income Tax Act, 1961. " .
        "The deductible amount is 50% of the donated sum, subject to 10% of your Adjusted Gross Total Income.\n" .
        "Please quote Registration No. " . NGO_80G_REG . " when filing your Income Tax Return.",
        0, 'L');

    $y += 40;

    // ── Declaration ───────────────────────────────────────────
    $pdf->SetTextColor(...$gray);
    $pdf->SetFont('helvetica', 'I', 7.5);
    $pdf->SetXY(15, $y + 5);
    $pdf->MultiCell(180, 5,
        "We hereby certify that the above donation was received by " . NGO_NAME .
        " and will be used solely for charitable and educational purposes. " .
        "The trust is registered under NITI Aayog NGO-DARPAN (ID: " . NGO_DARPAN . "). " .
        "This receipt is computer-generated and is valid without a physical signature.",
        0, 'L');

    $y += 22;

    // ── Signature area ────────────────────────────────────────
    $pdf->SetDrawColor(...$teal);
    $pdf->Line(130, $y + 14, 195, $y + 14);
    $pdf->SetTextColor(...$dark);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY(120, $y + 16);
    $pdf->Cell(75, 5, 'Authorised Signatory', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetXY(120, $y + 21);
    $pdf->Cell(75, 5, NGO_NAME, 0, 1, 'C');

    // ── Footer band ───────────────────────────────────────────
    $pdf->SetFillColor(...$teal);
    $pdf->Rect(0, 277, 210, 20, 'F');
    $pdf->SetTextColor(...$white);
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetXY(0, 280);
    $pdf->Cell(210, 5, NGO_NAME . '  |  ' . NGO_ADDRESS, 0, 1, 'C');
    $pdf->SetXY(0, 285);
    $pdf->Cell(210, 5, 'Email: ' . NGO_EMAIL . '  |  Website: ' . NGO_WEBSITE . '  |  PAN: ' . NGO_PAN, 0, 1, 'C');
    $pdf->SetXY(0, 290);
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->Cell(210, 5, 'Thank you for your generous contribution. Your support transforms lives.', 0, 1, 'C');

    return $pdf->Output('receipt.pdf', 'S'); // return as string
}

/**
 * Send receipt email with PDF attachment using PHP mail()
 */
function sendReceiptEmail(array $d, string $pdfBytes): bool {
    $to      = $d['donor_email'];
    $subject = 'Donation Receipt - ' . $d['receipt_no'] . ' | ' . NGO_NAME;

    $boundary = '==_ALHIND_' . md5(uniqid('', true));

    // ── HTML email body ───────────────────────────────────────
    $amtFormatted = 'Rs. ' . number_format((float)$d['amount'], 2);
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f1f5f9">
<tr><td align="center" style="padding:30px 10px">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">

  <!-- Header -->
  <tr><td style="background:#0f766e;padding:28px 32px">
    <p style="margin:0;color:#fff;font-size:20px;font-weight:bold">{$d['ngo_name']}</p>
    <p style="margin:4px 0 0;color:rgba(255,255,255,.8);font-size:12px">{$d['ngo_address']}</p>
  </td></tr>

  <!-- Green strip -->
  <tr><td style="background:#064e3b;padding:8px 32px;text-align:center">
    <p style="margin:0;color:#fff;font-size:11px">Tax Exemption Certificate under Section 80G &nbsp;|&nbsp; Reg. No: {$d['reg_80g']}</p>
  </td></tr>

  <!-- Body -->
  <tr><td style="padding:32px">
    <p style="color:#0f172a;font-size:22px;font-weight:bold;margin:0 0 4px">Thank you, {$d['donor_name']}! 🙏</p>
    <p style="color:#475569;font-size:14px;margin:0 0 24px">Your donation has been received and will make a real difference.</p>

    <!-- Amount box -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f766e;border-radius:10px;margin-bottom:24px">
    <tr><td style="padding:20px;text-align:center">
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:12px;text-transform:uppercase;letter-spacing:1px">Donation Amount</p>
      <p style="margin:6px 0 0;color:#fff;font-size:36px;font-weight:bold">{$amtFormatted}</p>
    </td></tr>
    </table>

    <!-- Details table -->
    <table width="100%" cellpadding="10" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;font-size:13px">
      <tr style="background:#f8fafc"><td style="color:#64748b;width:40%">Receipt No.</td><td style="color:#0f172a;font-weight:bold">{$d['receipt_no']}</td></tr>
      <tr><td style="color:#64748b;border-top:1px solid #e2e8f0">Date</td><td style="color:#0f172a;border-top:1px solid #e2e8f0">{$d['date']}</td></tr>
      <tr style="background:#f8fafc"><td style="color:#64748b;border-top:1px solid #e2e8f0">Payment ID</td><td style="color:#0f172a;border-top:1px solid #e2e8f0;font-size:11px">{$d['payment_id']}</td></tr>
      <tr><td style="color:#64748b;border-top:1px solid #e2e8f0">Payment Mode</td><td style="color:#0f172a;border-top:1px solid #e2e8f0">{$d['payment_method']}</td></tr>
      <tr style="background:#f8fafc"><td style="color:#64748b;border-top:1px solid #e2e8f0">PAN of Trust</td><td style="color:#0f172a;border-top:1px solid #e2e8f0;font-weight:bold">{$d['ngo_pan']}</td></tr>
      <tr><td style="color:#64748b;border-top:1px solid #e2e8f0">80G Reg. No.</td><td style="color:#0f172a;border-top:1px solid #e2e8f0">{$d['reg_80g']}</td></tr>
    </table>

    <!-- 80G info -->
    <table width="100%" cellpadding="14" cellspacing="0" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-top:20px">
    <tr><td>
      <p style="margin:0 0 6px;color:#15803d;font-weight:bold;font-size:13px">📋 For Your Tax Return (ITR)</p>
      <p style="margin:0;color:#166534;font-size:12px;line-height:1.6">
        This receipt qualifies for <strong>50% deduction</strong> under Section 80G of Income Tax Act, 1961.<br>
        Quote Reg. No. <strong>{$d['reg_80g']}</strong> in your ITR.<br>
        The PDF receipt attached to this email is your official 80G document.
      </p>
    </td></tr>
    </table>

    <p style="color:#64748b;font-size:12px;margin-top:24px;line-height:1.6">
      Your official <strong>80G receipt is attached as a PDF</strong> to this email. Please save it for your tax records.<br>
      For any queries, contact us at <a href="mailto:{$d['ngo_email']}" style="color:#0f766e">{$d['ngo_email']}</a>
    </p>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#0f766e;padding:16px 32px;text-align:center">
    <p style="margin:0;color:rgba(255,255,255,.9);font-size:11px">{$d['ngo_name']}</p>
    <p style="margin:4px 0 0;color:rgba(255,255,255,.6);font-size:10px">PAN: {$d['ngo_pan']} &nbsp;|&nbsp; DARPAN: {$d['ngo_darpan']} &nbsp;|&nbsp; {$d['ngo_website']}</p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

    // ── MIME headers & body ───────────────────────────────────
    $pdfBase64 = base64_encode($pdfBytes);
    $pdfFilename = 'Receipt_' . $d['receipt_no'] . '_80G.pdf';

    $headers  = "From: " . NGO_NAME . " <" . NGO_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . NGO_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: multipart/alternative; boundary=\"alt_{$boundary}\"\r\n\r\n";

    // Plain text part
    $body .= "--alt_{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= "Dear {$d['donor_name']},\r\n\r\n";
    $body .= "Thank you for your donation of {$amtFormatted} to " . NGO_NAME . ".\r\n";
    $body .= "Receipt No: {$d['receipt_no']}\r\n";
    $body .= "Payment ID: {$d['payment_id']}\r\n";
    $body .= "80G Reg: " . NGO_80G_REG . "\r\n\r\n";
    $body .= "Your 80G receipt is attached as a PDF.\r\n\r\n";
    $body .= "Regards,\r\n" . NGO_NAME . "\r\n";

    // HTML part
    $body .= "--alt_{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n";
    $body .= "--alt_{$boundary}--\r\n\r\n";

    // PDF attachment
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
    $body .= chunk_split($pdfBase64) . "\r\n";
    $body .= "--{$boundary}--";

    return mail($to, $subject, $body, $headers);
}

/**
 * Main entry point — called from verify-payment.php
 * $donation = ['donor_name','donor_email','amount','payment_method',
 *              'razorpay_order_id','razorpay_payment_id','receipt_no','id']
 */
function sendDonationReceipt(array $donation): bool {
    $d = [
        'receipt_no'     => $donation['receipt_no']     ?? ('DON-' . str_pad($donation['id'] ?? 0, 4, '0', STR_PAD_LEFT)),
        'date'           => date('d F Y, h:i A'),
        'donor_name'     => $donation['donor_name']     ?? '',
        'donor_email'    => $donation['donor_email']    ?? '',
        'amount'         => $donation['amount']         ?? 0,
        'payment_id'     => $donation['razorpay_payment_id'] ?? '',
        'payment_method' => $donation['payment_method'] ?? 'Razorpay',
        'ngo_name'       => NGO_NAME,
        'ngo_pan'        => NGO_PAN,
        'reg_80g'        => NGO_80G_REG,
        'ngo_address'    => NGO_ADDRESS,
        'ngo_email'      => NGO_EMAIL,
        'ngo_website'    => NGO_WEBSITE,
        'ngo_darpan'     => NGO_DARPAN,
    ];

    try {
        $pdfBytes = generateReceiptPDF($d);
        $sent     = sendReceiptEmail($d, $pdfBytes);
        error_log('[AL Hind receipt] Email ' . ($sent ? 'sent' : 'FAILED') . ' to ' . $d['donor_email'] . ' receipt:' . $d['receipt_no']);
        return $sent;
    } catch (Throwable $e) {
        error_log('[AL Hind receipt] ERROR: ' . $e->getMessage());
        return false;
    }
}
