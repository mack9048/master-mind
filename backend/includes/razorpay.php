<?php
declare(strict_types=1);

/**
 * Razorpay Standard Checkout helpers (plain cURL – no Composer needed on shared hosting).
 * Keys come from backend/.env or environment variables:
 *   RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET
 * The secret is only used here, on the server. It is never sent to the browser.
 */

function razorpay_key_id(): string
{
    return trim((string) cfg('razorpay.key_id', ''));
}

function razorpay_key_secret(): string
{
    return trim((string) cfg('razorpay.key_secret', ''));
}

function razorpay_enabled(): bool
{
    return razorpay_key_id() !== '' && razorpay_key_secret() !== '';
}

final class RazorpayError extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus)
    {
        parent::__construct($message);
    }
}

/**
 * POST https://api.razorpay.com/v1/orders
 * @param int $amountPaise amount in paise (min 100)
 * @return array{id:string,amount:int,currency:string}
 */
function razorpay_create_order(int $amountPaise, string $receipt, array $notes = []): array
{
    if ($amountPaise < 100) {
        throw new RazorpayError('Amount must be at least 100 paise.', 400);
    }
    // RAZORPAY_API_BASE is only for local testing against a mock server
    $base = rtrim((string) (getenv('RAZORPAY_API_BASE') ?: 'https://api.razorpay.com'), '/');
    $ch = curl_init($base . '/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'amount'   => $amountPaise,
            'currency' => 'INR',
            'receipt'  => substr($receipt, 0, 40),
            'notes'    => $notes,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => razorpay_key_id() . ':' . razorpay_key_secret(),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr   = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RazorpayError('Could not connect to Razorpay: ' . $cerr, 500);
    }
    $data = json_decode((string) $body, true) ?: [];
    if ($status === 401) {
        throw new RazorpayError('Razorpay authentication failed – check RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET.', 401);
    }
    if ($status >= 300 || empty($data['id'])) {
        throw new RazorpayError('Razorpay error: ' . ($data['error']['description'] ?? ('HTTP ' . $status)), 500);
    }
    return $data;
}

/** Where the keys were loaded from (for diagnostics – never returns the secret). */
function razorpay_key_source(): string
{
    if (getenv('RAZORPAY_KEY_ID') !== false && getenv('RAZORPAY_KEY_ID') !== '') {
        return is_file(dirname(__DIR__) . '/.env') ? '.env file' : 'environment variable';
    }
    return razorpay_key_id() !== '' ? 'config.php' : 'none';
}

/** Ask Razorpay whether the key pair is valid (GET /v1/orders?count=1). */
function razorpay_check_keys(): string
{
    if (!razorpay_enabled()) {
        return 'no keys';
    }
    $base = rtrim((string) (getenv('RAZORPAY_API_BASE') ?: 'https://api.razorpay.com'), '/');
    $ch = curl_init($base . '/v1/orders?count=1');
    curl_setopt_array($ch, [
        CURLOPT_USERPWD        => razorpay_key_id() . ':' . razorpay_key_secret(),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        return 'could not reach Razorpay (' . $err . ')';
    }
    if ($status === 200) {
        return 'OK – Razorpay accepted the keys';
    }
    if ($status === 401) {
        return 'REJECTED – Key ID and Key Secret do not match (regenerate test keys and paste both again)';
    }
    return 'unexpected answer from Razorpay (HTTP ' . $status . ')';
}

/** HMAC-SHA256(order_id + "|" + payment_id, KEY_SECRET) compared in constant time. */
function razorpay_signature_valid(string $orderId, string $paymentId, string $signature): bool
{
    if ($orderId === '' || $paymentId === '' || $signature === '' || razorpay_key_secret() === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, razorpay_key_secret());
    return hash_equals($expected, strtolower($signature));
}
