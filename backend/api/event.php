<?php
declare(strict_types=1);

// Public event info for the React frontend: live seat count + registration status.
require dirname(__DIR__) . '/includes/bootstrap.php';

api_begin('GET');
header('Cache-Control: public, max-age=30');

try {
    // Touch every column the booking form writes, so an outdated table is reported too
    db()->query('SELECT id, reg_code, event_slug, name, email, phone, city, profession, organization, expectation,
                        amount, payment_mode, payment_status, payment_ref, attended, ip_address, user_agent, created_at
                 FROM registrations LIMIT 1');
    $dbStatus = 'connected';
} catch (Throwable $e) {
    error_log('[mastermind] event: ' . $e->getMessage());
    $dbStatus = 'PROBLEM: ' . db_problem($e);
}

json_out([
    'ok'        => true,
    'database'  => $dbStatus,
    'slug'      => cfg('event.slug'),
    'title'     => cfg('event.title'),
    'starts_at' => cfg('event.starts_at'),
    'fee'       => (int) cfg('event.fee'),
    'seats'     => (int) cfg('event.seats'),
    'seats_left'=> seats_left(),
    'open'      => registration_open(),
]);
