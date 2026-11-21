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

/** Build the entry ticket PDF (210 × 99 mm) for a booking row. */
function ticket_pdf(array $r): string
{
    $W = 210; $H = 99; $stub = 152;
    $bg = '#111113'; $panel = '#1b1b1f'; $muted = '#9a9aa3'; $yellow = '#f9c816'; $white = '#ffffff';
    $pdf = new MiniPdf($W, $H);

    // background + perforation between ticket and stub
    $pdf->rect(0, 0, $W, $H, $bg);
    $pdf->rect($stub, 0, $W - $stub, $H, $panel);
    $pdf->line($stub, 6, $stub, $H - 6, '#3a3a40', 0.4, [1.6, 1.6]);
    $pdf->circle($stub, 0, 4.5, '#ffffff');
    $pdf->circle($stub, $H, 4.5, '#ffffff');

    // top gradient bar (pink → yellow → blue, like the website)
    $pdf->gradientBar(0, 0, $stub, 2.2, ['#f4769d', '#ffc95c', '#3d8bff']);

    // series pill
    $pill = strtoupper(cfg('event.series') . '  ·  ' . cfg('event.tagline'));
    $pw = $pdf->textWidth($pill, 7, true, 0.8) + 8;
    $pdf->rect(10, 9, $pw, 6.4, $yellow, 3.2);
    $pdf->text(14, 13.5, $pill, 7, '#111111', true, 'L', 0.8);

    // title: FAVOUR YOUR BRAND (middle word in yellow)
    $words = explode(' ', strtoupper((string) cfg('event.title')));
    $x = 10;
    foreach ($words as $i => $word) {
        $x += $pdf->text($x, 31, $word, 25, $i === 1 && count($words) === 3 ? $yellow : $white, true, 'L', -0.4) + 2.6;
    }
    $pdf->text(10, 38.5, 'Branding Masterclass with ' . cfg('event.speaker'), 10, '#c9c9d0');

    // details
    $cols = [
        ['DATE', (string) cfg('event.date_label')],
        ['TIME', (string) cfg('event.time_label')],
        ['VENUE', (string) cfg('event.venue')],
    ];
    $cx = 10;
    $widths = [52, 30, 50];
    foreach ($cols as $i => [$label, $value]) {
        $pdf->text($cx, 50, $label, 6.5, $muted, true, 'L', 1);
        $pdf->fitText($cx, 56, $value, 10, $widths[$i] - 4, $white, true);
        $cx += $widths[$i];
    }
    $pdf->line(10, 62, $stub - 10, 62, '#2c2c32', 0.3);

    // attendee
    $pdf->text(10, 70, 'ATTENDEE', 6.5, $muted, true, 'L', 1);
    $pdf->fitText(10, 78.5, (string) $r['name'], 16, $stub - 22, $white, true);
    $contact = trim($r['phone'] . ($r['email'] ? '   ·   ' . $r['email'] : ''));
    $pdf->fitText(10, 84.5, $contact, 8.5, $stub - 22, '#c9c9d0');

    // footer
    $pdf->text(10, 93.5, 'MAC Media School  ·  school.macadz.com  ·  ' . cfg('event.phone'), 7, $muted);

    // stub
    $sx = $stub + ($W - $stub) / 2;
    $pdf->text($sx, 14, 'ENTRY TICKET', 7.5, $yellow, true, 'C', 1.4);
    $pdf->text($sx, 22, 'ADMIT ONE', 11, $white, true, 'C', 0.6);
    $pdf->rect($stub + 6, 29, $W - $stub - 12, 22, '#0d0d0f', 3);
    $pdf->text($sx, 36, 'REG ID', 6.5, $muted, true, 'C', 1);
    $pdf->fitText($sx, 45.5, (string) $r['reg_code'], 15, $W - $stub - 16, $yellow, true, 'C', 0.4);

    $paid = $r['payment_status'] === 'paid';
    $badge = $paid ? 'PAID  ·  Rs. ' . (int) $r['amount'] : strtoupper((string) $r['payment_status']);
    $bw = $pdf->textWidth($badge, 8, true, 0.6) + 10;
    $pdf->rect($sx - $bw / 2, 56, $bw, 7, $paid ? '#74c24b' : '#ef4f57', 3.5);
    $pdf->text($sx, 60.9, $badge, 8, $paid ? '#102010' : '#ffffff', true, 'C', 0.6);

    $ref = $r['razorpay_payment_id'] ?: $r['payment_ref'];
    if ($ref) {
        $pdf->text($sx, 70, 'PAYMENT REF', 6, $muted, true, 'C', 0.8);
        $pdf->fitText($sx, 75, (string) $ref, 7.5, $W - $stub - 10, '#c9c9d0', false, 'C');
    }
    $pdf->text($sx, 86, 'Show this ticket', 7, $muted, false, 'C');
    $pdf->text($sx, 90, 'at the entry desk', 7, $muted, false, 'C');

    return $pdf->output('Master Mind ticket ' . $r['reg_code']);
}
