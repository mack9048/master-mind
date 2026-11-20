<?php
declare(strict_types=1);

// Public event info for the React frontend: live seat count + registration status.
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/razorpay.php';

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

// ?check=1 asks Razorpay whether the keys work (shows only OK / REJECTED, never the secret)
$keyCheck = isset($_GET['check']) ? razorpay_check_keys() : null;
if ($keyCheck !== null) {
    header('Cache-Control: no-store');
}

json_out(array_filter([
    'ok'        => true,
    'database'  => $dbStatus,
    'payment'   => razorpay_enabled()
        ? 'razorpay (' . (str_starts_with(razorpay_key_id(), 'rzp_live_') ? 'LIVE' : 'test') . ' mode)'
        : 'upi – Razorpay keys not found (add them to config.php or .env)',
    'razorpay_key_id'   => razorpay_key_id() ?: null,          // public key, safe to show
    'razorpay_keys_from'=> razorpay_key_source(),
    'slug'      => cfg('event.slug'),
    'title'     => cfg('event.title'),
    'starts_at' => cfg('event.starts_at'),
    'fee'       => (int) cfg('event.fee'),
    'seats'     => (int) cfg('event.seats'),
    'seats_left'=> seats_left(),
    'open'      => registration_open(),
    'razorpay_key_check' => $keyCheck,
], fn ($v) => $v !== null));
