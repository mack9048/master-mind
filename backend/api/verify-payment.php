<?php
declare(strict_types=1);

/**
 * POST /api/verify-payment.php
 * Body: reg_code, token, razorpay_order_id, razorpay_payment_id, razorpay_signature
 * Marks the booking paid ONLY when HMAC-SHA256(order_id|payment_id, KEY_SECRET)
 * equals razorpay_signature and the order belongs to this booking.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/razorpay.php';

api_begin('POST');

$regCode   = strtoupper(trim((string) ($_POST['reg_code'] ?? '')));
$token     = (string) ($_POST['token'] ?? '');
$orderId   = trim((string) ($_POST['razorpay_order_id'] ?? ''));
$paymentId = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
$signature = trim((string) ($_POST['razorpay_signature'] ?? ''));

if ($regCode === '' || $token === '' || $orderId === '' || $paymentId === '' || $signature === '') {
    json_out(['ok' => false, 'error' => 'Missing payment details.'], 400);
}
if (!reg_token_ok($regCode, $token)) {
    json_out(['ok' => false, 'error' => 'Registration not found.'], 403);
}

$pdo = db();
$st  = $pdo->prepare('SELECT * FROM registrations WHERE reg_code = ? AND event_slug = ?');
$st->execute([$regCode, cfg('event.slug')]);
$reg = $st->fetch();
if (!$reg) {
    json_out(['ok' => false, 'error' => 'Registration not found.'], 404);
}

// The order must be the one we created for this booking, and the signature must match
if (!hash_equals((string) $reg['razorpay_order_id'], $orderId) || !razorpay_signature_valid($orderId, $paymentId, $signature)) {
    error_log("[mastermind] verify-payment: signature mismatch for {$regCode} ({$orderId} / {$paymentId})");
    json_out(['ok' => false, 'error' => 'Payment could not be verified. If money was deducted, contact us with ID ' . $regCode . '.'], 400);
}

$pdo->prepare("UPDATE registrations
                  SET payment_status = 'paid', payment_mode = 'razorpay',
                      razorpay_payment_id = ?, payment_ref = ?, paid_at = COALESCE(paid_at, NOW())
                WHERE id = ?")
    ->execute([$paymentId, $paymentId, $reg['id']]);

json_out(['ok' => true, 'status' => 'paid', 'reg_code' => $regCode, 'payment_id' => $paymentId]);
