<?php
declare(strict_types=1);

// Public event info for the React frontend: live seat count + registration status.
require dirname(__DIR__) . '/includes/bootstrap.php';

api_begin('GET');
header('Cache-Control: public, max-age=30');

try {
    db()->query('SELECT 1 FROM registrations LIMIT 1');
    $dbStatus = 'connected';
} catch (Throwable $e) {
    error_log('[mastermind] event: ' . $e->getMessage());
    $dbStatus = $e instanceof PDOException && str_contains($e->getMessage(), "doesn't exist")
        ? 'connected, but the registrations table is missing – import database.sql'
        : 'NOT connected – check the db settings in config.php';
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
