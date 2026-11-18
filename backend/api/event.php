<?php
declare(strict_types=1);

// Public event info for the React frontend: live seat count + registration status.
require dirname(__DIR__) . '/includes/bootstrap.php';

api_begin('GET');
header('Cache-Control: public, max-age=30');

json_out([
    'ok'        => true,
    'slug'      => cfg('event.slug'),
    'title'     => cfg('event.title'),
    'starts_at' => cfg('event.starts_at'),
    'fee'       => (int) cfg('event.fee'),
    'seats'     => (int) cfg('event.seats'),
    'seats_left'=> seats_left(),
    'open'      => registration_open(),
]);
