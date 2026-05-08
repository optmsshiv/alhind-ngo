<?php
// ============================================================
//  send-receipt.php — 80G Donation Receipt Generator & Mailer
//  AL Hind Educational and Charitable Trust
//  v2 — Fixed: single page only, no blank pages
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
define('NGO_80G_VALID_FROM', '01/04/2026');
define('NGO_80G_VALID_TO',   '31/03/2027');

// ── Number to Indian words ────────────────────────────────────
function numberToWords(float $num): string {
    $num  = (int)$num;
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
             'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
             'Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    if ($num === 0) return 'Zero';
    $w = '';
    if ($num >= 10000000) { $w .= numberToWords((int)($num/10000000)).' Crore '; $num %= 10000000; }
    if ($num >= 100000)   { $w .= numberToWords((int)($num/100000)).' Lakh ';   $num %= 100000; }
    if ($num >= 1000)     { $w .= numberToWords((int)($num/1000)).' Thousand '; $num %= 1000; }
    if ($num >= 100)      { $w .= $ones[(int)($num/100)].' Hundred ';           $num %= 100; }
    if ($num >= 20)       { $w .= $tens[(int)($num/10)].' ';                    $num %= 10; }
    if ($num > 0)           $w .= $ones[$num].' ';
    return trim($w);
}

// ── Generate PDF ──────────────────────────────────────────────
function generateReceiptPDF(array $d): string {
    $tcpdfPath = __DIR__ . '/tcpdf/tcpdf.php';
    if (!file_exists($tcpdfPath)) throw new RuntimeException('TCPDF not found at '.$tcpdfPath);
    require_once $tcpdfPath;

    // ── Page setup — AutoPageBreak OFF so we control layout fully ──
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(NGO_NAME);
    $pdf->SetAuthor(NGO_NAME);
    $pdf->SetTitle('Donation Receipt - '.$d['receipt_no']);
    $pdf->SetSubject('80G Donation Receipt');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0); // ← KEY FIX: disable auto page break
    $pdf->AddPage();

    // ── Colours ───────────────────────────────────────────────
    $teal  = [15, 118, 110];
    $teal2 = [6,  78,  59];
    $dark  = [15,  23,  42];
    $gray  = [71,  85, 105];
    $light = [241,245, 249];
    $white = [255,255, 255];
    $gbg   = [240,253, 244]; // light green bg
    $gbdr  = [187,247, 208]; // green border

    // ══════════════════════════════════════════════════════════
    //  SECTION 1 — Header (y 0–38)
    // ══════════════════════════════════════════════════════════
    $pdf->SetFillColor(...$teal);
    $pdf->Rect(0, 0, 210, 38, 'F');

    $pdf->SetTextColor(...$white);
    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->SetXY(14, 7);
    $pdf->Cell(120, 8, NGO_NAME, 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetXY(134, 6);
    $pdf->Cell(62, 10, 'DONATION', 0, 0, 'R');

    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetXY(14, 16);
    $pdf->Cell(130, 5, NGO_ADDRESS, 0, 1, 'L');

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetXY(134, 17);
    $pdf->Cell(62, 7, 'RECEIPT', 0, 0, 'R');

    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetXY(14, 22);
    $pdf->Cell(0, 5, 'PAN: '.NGO_PAN.'   |   DARPAN: '.NGO_DARPAN.'   |   '.NGO_WEBSITE, 0, 1, 'L');

    // ══════════════════════════════════════════════════════════
    //  SECTION 2 — 80G strip (y 38–47)
    // ══════════════════════════════════════════════════════════
    $pdf->SetFillColor(...$teal2);
    $pdf->Rect(0, 38, 210, 9, 'F');
    $pdf->SetTextColor(...$white);
    $pdf->SetFont('helvetica', 'B', 7.5);
    $pdf->SetXY(0, 40);
    $pdf->Cell(210, 5,
        'Tax Exemption u/s 80G, Income Tax Act 1961   |   Reg: '.NGO_80G_REG.
        '   |   Valid: '.NGO_80G_VALID_FROM.' to '.NGO_80G_VALID_TO,
        0, 1, 'C');

    // ══════════════════════════════════════════════════════════
    //  SECTION 3 — Receipt meta bar (y 49–63)
    // ══════════════════════════════════════════════════════════
    $pdf->SetFillColor(...$light);
    $pdf->Rect(14, 49, 182, 14, 'F');
    $pdf->SetTextColor(...$dark);

    // Row 1
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetXY(18, 51);
    $pdf->Cell(38, 5, 'Receipt No:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->Cell(50, 5, $d['receipt_no'], 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->Cell(28, 5, 'Date:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->Cell(0, 5, $d['date'], 0, 1, 'L');

    // Row 2
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetXY(18, 57);
    $pdf->Cell(38, 5, 'Payment ID:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 5, $d['payment_id'], 0, 1, 'L');

    // ══════════════════════════════════════════════════════════
    //  SECTION 4 — Donor Details (y 67–103)
    // ══════════════════════════════════════════════════════════
    $pdf->SetTextColor(...$teal);
    $pdf->SetFont('helvetica', 'B', 9.5);
    $pdf->SetXY(14, 67);
    $pdf->Cell(0, 6, 'DONOR DETAILS', 0, 1, 'L');

    $pdf->SetDrawColor(...$teal);
    $pdf->Line(14, 74, 196, 74);

    $donorRows = [
        ['Donor Name',    $d['donor_name']],
        ['Email Address', $d['donor_email']],
        ['Payment Mode',  $d['payment_method']],
    ];
    $y = 76;
    foreach ($donorRows as [$lbl, $val]) {
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetXY(14, $y);
        $pdf->SetTextColor(...$gray);
        $pdf->Cell(48, 7, $lbl.':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor(...$dark);
        $pdf->Cell(0, 7, $val, 0, 1, 'L');
        $y += 7;
    }
    // $y is now ~97

    // ══════════════════════════════════════════════════════════
    //  SECTION 5 — Amount box (y 101–133)
    // ══════════════════════════════════════════════════════════
    $boxY = 101;
    $pdf->SetFillColor(...$teal);
    $pdf->RoundedRect(14, $boxY, 182, 32, 4, '1111', 'F');

    $pdf->SetTextColor(...$white);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(14, $boxY + 4);
    $pdf->Cell(182, 5, 'DONATION AMOUNT', 0, 1, 'C');

    $amtFormatted = 'Rs. '.number_format((float)$d['amount'], 2);
    $pdf->SetFont('helvetica', 'B', 26);
    $pdf->SetXY(14, $boxY + 9);
    $pdf->Cell(182, 12, $amtFormatted, 0, 1, 'C');

    $words = numberToWords((float)$d['amount']).' Rupees Only';
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetXY(14, $boxY + 22);
    $pdf->Cell(182, 5, '('.$words.')', 0, 1, 'C');

    // ══════════════════════════════════════════════════════════
    //  SECTION 6 — 80G Tax Info box (y 137–167)
    // ══════════════════════════════════════════════════════════
    $taxY = 137;
    $pdf->SetFillColor(...$gbg);
    $pdf->SetDrawColor(...$gbdr);
    $pdf->RoundedRect(14, $taxY, 182, 30, 3, '1111', 'FD');

    $pdf->SetTextColor(21, 128, 61); // green-700
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetXY(18, $taxY + 5);
    $pdf->Cell(0, 5, 'TAX EXEMPTION UNDER SECTION 80G', 0, 1, 'L');

    $pdf->SetTextColor(22, 101, 52); // green-800
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetXY(18, $taxY + 12);
    $pdf->MultiCell(174, 4.5,
        "This donation qualifies for 50% tax deduction under Section 80G of the Income Tax Act, 1961, ".
        "subject to 10% of your Adjusted Gross Total Income.\n".
        "Quote Reg. No. ".NGO_80G_REG." in your Income Tax Return (ITR). ".
        "PAN of Trust: ".NGO_PAN,
        0, 'L', false, 1, '', '', true, 0, false, true, 0);

    // ══════════════════════════════════════════════════════════
    //  SECTION 7 — Declaration (y 172–192)
    // ══════════════════════════════════════════════════════════
    $declY = 172;
    $pdf->SetTextColor(...$gray);
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetXY(14, $declY);
    $pdf->MultiCell(182, 4.5,
        "We hereby certify that the above donation was received by ".NGO_NAME.
        " and will be utilized solely for charitable and educational purposes. ".
        "The trust is registered under NITI Aayog NGO-DARPAN (ID: ".NGO_DARPAN."). ".
        "This is a computer-generated receipt and is valid without a physical signature.",
        0, 'L', false, 1, '', '', true, 0, false, true, 0);

    // ══════════════════════════════════════════════════════════
    //  SECTION 8 — Signature line (y 198–215)
    // ══════════════════════════════════════════════════════════
    $sigY = 198;
    $pdf->SetDrawColor(...$teal);
    $pdf->Line(124, $sigY + 12, 192, $sigY + 12);
    $pdf->SetTextColor(...$dark);
    $pdf->SetFont('helvetica', 'B', 7.5);
    $pdf->SetXY(114, $sigY + 14);
    $pdf->Cell(78, 4, 'Authorised Signatory', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(...$gray);
    $pdf->SetXY(114, $sigY + 19);
    $pdf->Cell(78, 4, NGO_NAME, 0, 1, 'C');

    // ══════════════════════════════════════════════════════════
    //  SECTION 9 — Footer band (y 262–282) — pinned to bottom
    // ══════════════════════════════════════════════════════════
    $pdf->SetFillColor(...$teal);
    $pdf->Rect(0, 262, 210, 35, 'F');

    $pdf->SetTextColor(...$white);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY(0, 266);
    $pdf->Cell(210, 5, NGO_NAME, 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetXY(0, 272);
    $pdf->Cell(210, 4, NGO_ADDRESS, 0, 1, 'C');

    $pdf->SetXY(0, 277);
    $pdf->Cell(210, 4, 'Email: '.NGO_EMAIL.'   |   '.NGO_WEBSITE.'   |   PAN: '.NGO_PAN, 0, 1, 'C');

    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetXY(0, 282);
    $pdf->Cell(210, 4, 'Thank you for your generous contribution. Your support transforms lives.', 0, 1, 'C');

    // ── Output as string (no file written) ───────────────────
    return $pdf->Output('receipt.pdf', 'S');
}

// ── Send receipt email ────────────────────────────────────────
function sendReceiptEmail(array $d, string $pdfBytes): bool {
    $to       = $d['donor_email'];
    $subject  = 'Donation Receipt '.$d['receipt_no'].' | '.NGO_NAME;
    $boundary = '==_ALHIND_'.md5(uniqid('', true));
    $amt      = 'Rs. '.number_format((float)$d['amount'], 2);

    $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:30px 10px">
<table width="580" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">
  <tr><td style="background:#0f766e;padding:24px 28px">
    <p style="margin:0;color:#fff;font-size:18px;font-weight:bold">{$d['ngo_name']}</p>
    <p style="margin:4px 0 0;color:rgba(255,255,255,.75);font-size:11px">{$d['ngo_address']}</p>
  </td></tr>
  <tr><td style="background:#064e3b;padding:7px 28px;text-align:center">
    <p style="margin:0;color:#fff;font-size:10px">80G Tax Exemption &nbsp;|&nbsp; Reg: {$d['reg_80g']} &nbsp;|&nbsp; PAN: {$d['ngo_pan']}</p>
  </td></tr>
  <tr><td style="padding:28px">
    <p style="color:#0f172a;font-size:20px;font-weight:bold;margin:0 0 4px">Thank you, {$d['donor_name']}! &#x1F64F;</p>
    <p style="color:#64748b;font-size:13px;margin:0 0 22px">Your donation has been received. The PDF receipt is attached for your tax records.</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f766e;border-radius:10px;margin-bottom:22px">
    <tr><td style="padding:18px;text-align:center">
      <p style="margin:0;color:rgba(255,255,255,.8);font-size:11px;text-transform:uppercase;letter-spacing:1px">Donation Amount</p>
      <p style="margin:6px 0 0;color:#fff;font-size:32px;font-weight:bold">{$amt}</p>
    </td></tr></table>
    <table width="100%" cellpadding="9" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;font-size:12px;margin-bottom:18px">
      <tr style="background:#f8fafc"><td style="color:#64748b;width:38%">Receipt No.</td><td style="color:#0f172a;font-weight:bold">{$d['receipt_no']}</td></tr>
      <tr><td style="color:#64748b;border-top:1px solid #e2e8f0">Date</td><td style="color:#0f172a;border-top:1px solid #e2e8f0">{$d['date']}</td></tr>
      <tr style="background:#f8fafc"><td style="color:#64748b;border-top:1px solid #e2e8f0">Payment ID</td><td style="color:#0f172a;border-top:1px solid #e2e8f0;font-size:11px">{$d['payment_id']}</td></tr>
      <tr><td style="color:#64748b;border-top:1px solid #e2e8f0">Payment Mode</td><td style="color:#0f172a;border-top:1px solid #e2e8f0">{$d['payment_method']}</td></tr>
      <tr style="background:#f8fafc"><td style="color:#64748b;border-top:1px solid #e2e8f0">Trust PAN</td><td style="color:#0f172a;font-weight:bold;border-top:1px solid #e2e8f0">{$d['ngo_pan']}</td></tr>
      <tr><td style="color:#64748b;border-top:1px solid #e2e8f0">80G Reg. No.</td><td style="color:#0f172a;border-top:1px solid #e2e8f0">{$d['reg_80g']}</td></tr>
    </table>
    <table width="100%" cellpadding="12" cellspacing="0" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-bottom:18px">
    <tr><td>
      <p style="margin:0 0 5px;color:#15803d;font-weight:bold;font-size:12px">&#x1F4CB; For Your ITR Filing</p>
      <p style="margin:0;color:#166534;font-size:11px;line-height:1.6">
        Claim <strong>50% deduction</strong> under Section 80G while filing your Income Tax Return.<br>
        Quote Reg. No. <strong>{$d['reg_80g']}</strong> in your ITR.<br>
        The attached PDF is your official 80G receipt — save it for your CA/records.
      </p>
    </td></tr></table>
    <p style="color:#94a3b8;font-size:11px;margin:0">
      Questions? Write to <a href="mailto:{$d['ngo_email']}" style="color:#0f766e">{$d['ngo_email']}</a>
    </p>
  </td></tr>
  <tr><td style="background:#0f766e;padding:14px 28px;text-align:center">
    <p style="margin:0;color:rgba(255,255,255,.9);font-size:10px">{$d['ngo_name']} &nbsp;|&nbsp; DARPAN: {$d['ngo_darpan']}</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>
HTML;

    $b64      = base64_encode($pdfBytes);
    $filename = 'Receipt_'.$d['receipt_no'].'_80G.pdf';

    $headers  = "From: ".NGO_NAME." <".NGO_EMAIL.">\r\n";
    $headers .= "Reply-To: ".NGO_EMAIL."\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: multipart/alternative; boundary=\"alt_{$boundary}\"\r\n\r\n";

    // Plain text
    $body .= "--alt_{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= "Dear {$d['donor_name']},\n\nThank you for donating {$amt} to ".NGO_NAME.".\n";
    $body .= "Receipt No: {$d['receipt_no']}\nPayment ID: {$d['payment_id']}\n80G Reg: ".NGO_80G_REG."\n\n";
    $body .= "Your 80G receipt PDF is attached.\n\nRegards,\n".NGO_NAME."\n";

    // HTML
    $body .= "--alt_{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html."\r\n";
    $body .= "--alt_{$boundary}--\r\n\r\n";

    // PDF attachment
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
    $body .= chunk_split($b64)."\r\n";
    $body .= "--{$boundary}--";

    return mail($to, $subject, $body, $headers);
}

// ── Main entry: called from verify-payment.php ────────────────
function sendDonationReceipt(array $donation): bool {
    $d = [
        'receipt_no'     => $donation['receipt_no'] ?? ('DON-'.str_pad($donation['id'] ?? 0, 4, '0', STR_PAD_LEFT)),
        'date'           => date('d F Y, h:i A'),
        'donor_name'     => $donation['donor_name']          ?? '',
        'donor_email'    => $donation['donor_email']         ?? '',
        'amount'         => $donation['amount']              ?? 0,
        'payment_id'     => $donation['razorpay_payment_id'] ?? '',
        'payment_method' => $donation['payment_method']      ?? 'Razorpay',
        'ngo_name'       => NGO_NAME,
        'ngo_pan'        => NGO_PAN,
        'reg_80g'        => NGO_80G_REG,
        'ngo_address'    => NGO_ADDRESS,
        'ngo_email'      => NGO_EMAIL,
        'ngo_website'    => NGO_WEBSITE,
        'ngo_darpan'     => NGO_DARPAN,
    ];
    try {
        $pdf  = generateReceiptPDF($d);
        $sent = sendReceiptEmail($d, $pdf);
        error_log('[AL Hind receipt] '.($sent?'Sent':'FAILED').' → '.$d['donor_email'].' '.$d['receipt_no']);
        return $sent;
    } catch (Throwable $e) {
        error_log('[AL Hind receipt] ERROR: '.$e->getMessage());
        return false;
    }
}
