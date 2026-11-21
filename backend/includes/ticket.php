<?php
declare(strict_types=1);

require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/qr.php';

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

/** Build the entry ticket PDF (210 × 82 mm, concert-ticket style) for a booking row. */
function ticket_pdf(array $r): string
{
    $W = 210; $H = 82;
    $L = 4; $T = 5; $R = 206; $B = 77; $cut = 151;           // ticket box + stub line
    $navy = '#1d1850'; $page = '#2a2468';
    $purple = '#6a4cf0'; $purple2 = '#7d63ff'; $purple3 = '#8f7bff';
    $orange = '#ffb020'; $orange2 = '#ffc94d'; $red = '#ff5a4e'; $yellow = '#ffd23f';
    $white = '#ffffff'; $lav = '#e7e1ff';
    $pdf = new MiniPdf($W, $H);

    $pdf->rect(0, 0, $W, $H, $page);
    // subtle dotted pattern on the page background
    for ($y = 2; $y < $H; $y += 4) {
        for ($x = 2 + ($y % 8 ? 2 : 0); $x < $W; $x += 4) {
            $pdf->circle($x, $y, 0.22, '#332c78');
        }
    }

    // ---------- ticket body (purple, wavy shapes)
    $pdf->rect($L, $T, $R - $L, $B - $T, $navy, 3.2);                       // outline / shadow base
    $pdf->clip($L + 0.7, $T + 0.7, $cut - $L - 0.7, $B - $T - 1.4);
    $pdf->rect($L, $T, $cut - $L, $B - $T, $purple);
    $pdf->organicBlob(40, 8, 48, 18, 3, $purple2);
    $pdf->organicBlob(120, 82, 46, 22, 7, $purple2);
    $pdf->organicBlob(92, 40, 22, 30, 11, $purple3);
    $pdf->organicBlob(8, 80, 20, 16, 5, $red, $navy, 0.5);
    $pdf->organicBlob(150, 8, 16, 12, 9, $orange, $navy, 0.5);
    $pdf->organicBlob(-2, 30, 9, 14, 2, $orange, $navy, 0.5);
    $pdf->endClip();

    // ---------- stub (orange, wavy shapes)
    $pdf->clip($cut, $T + 0.7, $R - $cut - 0.7, $B - $T - 1.4);
    $pdf->rect($cut, $T, $R - $cut, $B - $T, $orange);
    $pdf->organicBlob(200, 12, 22, 16, 4, $orange2);
    $pdf->organicBlob(160, 74, 20, 14, 8, $orange2);
    $pdf->organicBlob(210, 70, 10, 12, 6, $red, $navy, 0.5);
    $pdf->endClip();

    // perforation + notches
    $pdf->line($cut, $T + 5, $cut, $B - 5, $navy, 0.5, [1.3, 1.3]);
    foreach ([$T, $B] as $ny) {
        $pdf->circle($cut, $ny, 4.2, $navy);
        $pdf->circle($cut, $ny, 3.5, $page);
    }

    // ---------- title block (chunky sticker letters)
    $pdf->stickerText(11, 26, 'MASTER', 27, $white, $navy, 1.1, 1.1, 'B', 'L', 0.4);
    $pdf->stickerText(11, 38.5, 'MIND', 27, $yellow, $navy, 1.1, 1.1, 'B', 'L', 0.6);
    $pw = $pdf->textWidth('meet the expert', 9, true) + 8;
    $pdf->rect(12.2, 43.6, $pw, 7, $navy, 3.5);
    $pdf->rect(11.4, 42.8, $pw, 7, $yellow, 3.5);
    $pdf->text(15.4, 47.8, 'meet the expert', 9, $navy, true, 'L', 0, true);
    // little flowers / sparkles
    foreach ([[66, 14, $yellow], [70.5, 18.5, $white], [9, 58, $white]] as [$fx, $fy, $fc]) {
        for ($k = 0; $k < 5; $k++) {
            $a = $k * 2 * M_PI / 5;
            $pdf->circle($fx + cos($a) * 1.3, $fy + sin($a) * 1.3, 0.9, $fc);
        }
        $pdf->circle($fx, $fy, 0.7, $red);
    }

    // microphone illustration
    $pdf->rotate(-32, 40, 64);
    $pdf->rect(37.6, 62, 4.8, 15, $navy, 2.2);          // handle outline
    $pdf->rect(38.2, 62.6, 3.6, 13.8, $yellow, 1.8);
    $pdf->rect(36.6, 60, 6.8, 3.2, $navy, 1);           // collar
    $pdf->rect(37.1, 60.4, 5.8, 2.4, $red, 0.8);
    $pdf->circle(40, 55, 6, $navy);                    // head
    $pdf->circle(40, 55, 5.3, $lav);
    for ($gy = -4; $gy <= 4; $gy += 1.6) {
        for ($gx = -4; $gx <= 4; $gx += 1.6) {
            if ($gx * $gx + $gy * $gy < 17) {
                $pdf->circle(40 + $gx, 55 + $gy, 0.35, '#8f84c9');
            }
        }
    }
    $pdf->endTransform();

    // ---------- event details
    $x0 = 78;
    $pdf->text($x0, 14.5, strtoupper((string) cfg('event.title')), 10.5, $white, true, 'L', 0.8);
    $pdf->fitText($x0, 19.5, 'BRANDING MASTERCLASS WITH ' . strtoupper((string) cfg('event.speaker')), 5.8, $cut - $x0 - 6, $lav, true, 'L', 0.5);
    $pdf->text($x0, 28, strtoupper((string) cfg('event.date_label')), 8.2, $white, true, 'L', 0.3);
    $pdf->text($x0, 33, 'AT ' . strtoupper((string) cfg('event.venue')), 6.5, $lav, true, 'L', 0.5);
    $pdf->text($x0, 42.5, 'STARTS AT:', 6.5, $lav, true, 'L', 0.6);
    $pdf->text($x0 + 17, 43, (string) cfg('event.time_label'), 15, $white, true, 'L', 0.2);

    // info boxes
    $bx = $x0; $by = 50; $bw = 64; $bh = 16;
    $pdf->strokeRect($bx, $by, $bw, $bh, $white, 0.45, 1.5);
    $pdf->line($bx + 44, $by, $bx + 44, $by + $bh, $white, 0.45);
    $pdf->text($bx + 3, $by + 5, 'ATTENDEE', 5.5, $lav, true, 'L', 0.8);
    $pdf->fitText($bx + 3, $by + 12, strtoupper((string) $r['name']), 9, 39, $white, true);
    $pdf->text($bx + 47, $by + 5, 'ENTRY', 5.5, $lav, true, 'L', 0.8);
    $pdf->text($bx + 47, $by + 12, 'ADMIT 1', 8, $white, true);

    // price badge
    $paid = $r['payment_status'] === 'paid';
    $px = 137; $py = 36;
    $pdf->circle($px + 0.7, $py + 0.7, 9, $navy);
    $pdf->circle($px, $py, 9, $navy);
    $pdf->circle($px, $py, 8.3, $paid ? $red : '#8a8aa0');
    $pdf->text($px, $py - 1.6, $paid ? 'PAID' : strtoupper((string) $r['payment_status']), 5.5, $white, true, 'C', 0.8);
    $pdf->stickerText($px, $py + 4.2, 'Rs.' . (int) $r['amount'], 9.5, $yellow, $navy, 0.6, 0, 'B', 'C');

    $pdf->fitText($x0, 72, 'MAC MEDIA SCHOOL  ·  SCHOOL.MACADZ.COM  ·  ' . cfg('event.phone'), 5.2, $cut - $x0 - 6, $lav, true, 'L', 0.4);

    // ---------- stub: white card with QR code (scanned at check-in)
    $code = (string) $r['reg_code'];
    $cx0 = $cut + 6; $cw = $R - $cut - 12;
    $pdf->rect($cx0 + 0.8, 10.3, $cw, 50, $navy, 3);
    $pdf->rect($cx0, 9.5, $cw, 50, $white, 3);
    $qs = 29;
    $pdf->qr($cx0 + ($cw - $qs) / 2, 14.5, $qs, qr_matrix($code), $navy);
    $pdf->fitText($cx0 + $cw / 2, 50, $code, 10.5, $cw - 6, $navy, true, 'C', 0.6);
    $pdf->text($cx0 + $cw / 2, 55.8, 'SCAN AT ENTRY', 6, '#6a6a8a', true, 'C', 1);
    $pdf->text($cut + ($R - $cut) / 2, 66, 'ADMIT ONE', 8, $navy, true, 'C', 1.4);
    $pdf->barcode128($cut + 10, 69, $R - $cut - 20, 4.5, $code, $navy);

    return $pdf->output(cfg('event.series') . ' ticket ' . $code);
}
