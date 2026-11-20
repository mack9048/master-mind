<?php
declare(strict_types=1);

/**
 * POST /api/create-order.php
 * Body: reg_code, token  (both returned by /api/register.php)
 * Creates a Razorpay order for the registration fee. The amount is taken from the
 * booking on the server – never from the browser.
 * Returns: { ok, order_id, amount, currency, key_id, name, description, prefill }
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/razorpay.php';

api_begin('POST');

if (!razorpay_enabled()) {
    json_out(['ok' => false, 'error' => 'Online payment is not configured on the server.'], 500);
}

$regCode = strtoupper(trim((string) ($_POST['reg_code'] ?? '')));
$token   = (string) ($_POST['token'] ?? '');
if ($regCode === '' || $token === '') {
    json_out(['ok' => false, 'error' => 'Missing registration details.'], 400);
}
if (!reg_token_ok($regCode, $token)) {
    json_out(['ok' => false, 'error' => 'Registration not found. Please register again.'], 403);
}

$pdo = db();
$st  = $pdo->prepare('SELECT * FROM registrations WHERE reg_code = ? AND event_slug = ?');
$st->execute([$regCode, cfg('event.slug')]);
$reg = $st->fetch();
if (!$reg) {
    json_out(['ok' => false, 'error' => 'Registration not found.'], 404);
}
if ($reg['payment_status'] === 'paid') {
    json_out(['ok' => false, 'already_paid' => true, 'error' => 'This registration is already paid. Your seat is confirmed.'], 409);
}
if ($reg['payment_status'] === 'cancelled') {
    json_out(['ok' => false, 'error' => 'This registration was cancelled. Please contact us.'], 409);
}

$amountPaise = (int) round(((float) $reg['amount'] ?: (float) cfg('event.fee')) * 100);

try {
    $order = razorpay_create_order($amountPaise, $regCode, [
        'reg_code' => $regCode,
        'event'    => (string) cfg('event.slug'),
        'name'     => $reg['name'],
        'phone'    => $reg['phone'],
    ]);
} catch (RazorpayError $e) {
    error_log('[mastermind] create-order: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => $e->httpStatus === 401
        ? 'Payment gateway authentication failed. Please contact us.'
        : 'Could not start the payment. Please try again in a moment.'], $e->httpStatus);
}

$pdo->prepare("UPDATE registrations SET razorpay_order_id = ?, payment_mode = 'razorpay', payment_status = 'pending' WHERE id = ?")
    ->execute([$order['id'], $reg['id']]);

json_out([
    'ok'          => true,
    'order_id'    => $order['id'],
    'amount'      => (int) $order['amount'],
    'currency'    => $order['currency'] ?? 'INR',
    'key_id'      => razorpay_key_id(),            // public key only
    'name'        => cfg('event.series') . ' · ' . cfg('event.tagline'),
    'description' => cfg('event.title') . ' — ' . cfg('event.date_label'),
    'prefill'     => ['name' => $reg['name'], 'email' => $reg['email'], 'contact' => $reg['phone']],
]);
