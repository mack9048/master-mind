<?php
declare(strict_types=1);

require_once __DIR__ . '/pdf.php';

/** Signed token so a ticket link can't be guessed from the Reg ID alone. */
function ticket_token(string $regCode): string
{
    return substr(sign('ticket|' . $regCode), 0, 32);
}

function ticket_url(string $regCode): string
{
    return base_url() . '/api/ticket.php?code=' . rawurlencode($regCode) . '&t=' . ticket_token($regCode);
}

function ticket_filename(array $r): string
{
    return 'MasterMind-Ticket-' . $r['reg_code'] . '.pdf';
}

/** Build the entry ticket PDF (210 × 85 mm, "cinema ticket" style) for a booking row. */
function ticket_pdf(array $r): string
{
    $W = 210; $H = 85; $cut = 150;                    // stub starts at $cut
    $purple = '#7b2cbf'; $stripe = '#8a3fd0';
    $yellow = '#f9c816'; $stripeY = '#ffd84a';
    $white = '#ffffff'; $soft = '#e9dcff'; $paper = '#ffffff';
    $pdf = new MiniPdf($W, $H);

    // ---- ticket body + stub with diagonal stripes
    $stripes = function (float $x, float $w, string $base, string $line) use ($pdf, $H): void {
        $pdf->clip($x, 0, $w, $H);
        $pdf->rect($x, 0, $w, $H, $base);
        for ($sx = $x - $H; $sx < $x + $w; $sx += 3.2) {
            $pdf->polygon([[$sx, $H], [$sx + 1.1, $H], [$sx + 1.1 + $H, 0], [$sx + $H, 0]], $line);
        }
        $pdf->endClip();
    };
    $stripes(0, $cut, $purple, $stripe);
    $stripes($cut, $W - $cut, $yellow, $stripeY);

    // ---- stamp-style scalloped edges + perforation
    for ($x = 2.2; $x < $W; $x += 4.4) {
        $pdf->circle($x, 0, 1.5, $paper);
        $pdf->circle($x, $H, 1.5, $paper);
    }
    for ($y = 2.2; $y < $H; $y += 4.4) {
        $pdf->circle(0, $y, 1.5, $paper);
        $pdf->circle($W, $y, 1.5, $paper);
    }
    for ($y = 5; $y < $H - 3; $y += 3) {
        $pdf->circle($cut, $y, 0.75, $paper);
    }
    $pdf->circle($cut, 0, 4, $paper);
    $pdf->circle($cut, $H, 4, $paper);

    // ---- body
    $pdf->dashedRect(7, 7, $cut - 14, $H - 14, $white, 0.4, [1.6, 1.3]);

    $pill = strtoupper(cfg('event.series') . ' · ' . cfg('event.tagline'));
    $pw = $pdf->textWidth($pill, 7, true, 0.8) + 8;
    $pdf->rect(13, 12, $pw, 6, $yellow, 3);
    $pdf->text(17, 16.3, $pill, 7, '#2a1050', true, 'L', 0.8);

    $pdf->text(13, 31, strtoupper((string) cfg('event.title')), 25, $white, true, 'L', 0.2);
    $pdf->text(13, 41.5, 'TICKET', 25, $yellow, true, 'L', 3);
    $pdf->text(13, 47.5, 'Branding Masterclass with ' . cfg('event.speaker'), 8.5, $soft);

    $lines = [
        strtoupper((string) cfg('event.date_label')) . '   ' . cfg('event.time_label'),
        strtoupper((string) cfg('event.venue')),
    ];
    $ly = 56.5;
    foreach ($lines as $line) {
        $pdf->star(15, $ly - 1.2, 1.6, $yellow);
        $pdf->fitText(19, $ly, $line, 8.5, 80, $yellow, true, 'L', 0.4);
        $ly += 6;
    }
    $pdf->text(13, 70.5, 'ADMIT', 6.5, $soft, true, 'L', 1.2);
    $pdf->fitText(25, 70.8, strtoupper((string) $r['name']), 11, 70, $white, true, 'L', 0.3);

    // "PAID" stamp
    $paid = $r['payment_status'] === 'paid';
    $sx = 122; $sy = 52;
    $pdf->circle($sx, $sy, 13, $paid ? '#22c55e' : '#ef4f57');
    $pdf->circle($sx, $sy, 11.6, $paid ? '#16a34a' : '#dc2626');
    $pdf->dashedRect($sx - 8.5, $sy - 3.8, 17, 9.6, $white, 0.3, [0.8, 0.8]);
    $pdf->text($sx, $sy + 1.4, $paid ? 'PAID' : strtoupper((string) $r['payment_status']), 10, $white, true, 'C', 0.8);
    $pdf->text($sx, $sy + 4.6, 'Rs. ' . (int) $r['amount'], 5.5, $white, true, 'C', 0.4);
    $pdf->star($sx, $sy - 7, 1.4, $yellow);

    // stars sprinkled top-right of body
    $pdf->star(118, 22, 2.4, $yellow);
    $pdf->star(131, 15, 1.5, $white);
    $pdf->star(138, 27, 1.2, $yellow);

    $pdf->text(13, 75.6, 'MAC Media School  ·  school.macadz.com  ·  ' . cfg('event.phone'), 5.8, $soft);

    // ---- stub: vertical barcode + reg id
    $code = (string) $r['reg_code'];
    $pdf->rect($cut + 8, 8, 22, $H - 16, $white, 2);
    $pdf->barcode128Vertical($cut + 11, 12, 16, $H - 24, $code, '#1a1a1a');
    $pdf->textUp($cut + 39, $H - 10, $code, 14, '#1a1a1a', true, 1.2);
    $pdf->textUp($cut + 47, $H - 10, 'ADMIT ONE  ·  ' . strtoupper((string) cfg('event.series')), 7, '#5b3a00', true, 1.4);
    $ref = $r['razorpay_payment_id'] ?: $r['payment_ref'];
    if ($ref) {
        $pdf->textUp($cut + 53, $H - 10, 'REF ' . $ref, 5.5, '#7a5600', false, 0.6);
    }

    return $pdf->output(cfg('event.series') . ' ticket ' . $code);
}
