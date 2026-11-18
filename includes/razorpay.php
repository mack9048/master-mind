<?php
declare(strict_types=1);

/** Minimal Razorpay helpers (no SDK needed – uses cURL). */

function razorpay_enabled(): bool
{
    return cfg('payment.mode') === 'razorpay'
        && cfg('payment.razorpay_key') !== ''
        && cfg('payment.razorpay_secret') !== '';
}

/**
 * Create an order. Amount is in rupees; Razorpay expects paise.
 * @return array decoded order (contains "id")
 */
function razorpay_create_order(int $amountRupees, string $receipt, array $notes = []): array
{
    $payload = json_encode([
        'amount'   => $amountRupees * 100,
        'currency' => 'INR',
        'receipt'  => $receipt,
        'notes'    => $notes,
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERPWD        => cfg('payment.razorpay_key') . ':' . cfg('payment.razorpay_secret'),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Razorpay connection failed: ' . $err);
    }
    $data = json_decode((string) $body, true);
    if ($status >= 300 || empty($data['id'])) {
        throw new RuntimeException('Razorpay order failed: ' . ($data['error']['description'] ?? $body));
    }
    return $data;
}

function razorpay_verify_signature(string $orderId, string $paymentId, string $signature): bool
{
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) cfg('payment.razorpay_secret'));
    return hash_equals($expected, $signature);
}
