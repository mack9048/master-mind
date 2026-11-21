<?php
declare(strict_types=1);

/**
 * GET /api/ticket.php?code=MM26-XXXXXX&t=<signed token>
 * Downloads the PDF entry ticket. Only for paid bookings, and only with the
 * signed link (from the confirmation e-mail / WhatsApp / success screen).
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/ticket.php';

$code  = strtoupper(trim((string) ($_GET['code'] ?? '')));
$token = (string) ($_GET['t'] ?? '');

if ($code === '' || !hash_equals(ticket_token($code), $token)) {
    http_response_code(403);
    exit('Invalid ticket link.');
}

$st = db()->prepare('SELECT * FROM registrations WHERE reg_code = ? AND event_slug = ?');
$st->execute([$code, cfg('event.slug')]);
$r = $st->fetch();
if (!$r) {
    http_response_code(404);
    exit('Ticket not found.');
}
if ($r['payment_status'] !== 'paid') {
    http_response_code(402);
    exit('This booking is not paid yet. The ticket is available once the payment is confirmed.');
}

$pdf = ticket_pdf($r);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . ticket_filename($r) . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
