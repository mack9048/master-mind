<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/razorpay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
if (!csrf_check($_POST['csrf'] ?? null)) {
    json_out(['ok' => false, 'error' => 'Your session expired. Please refresh the page.'], 419);
}

$action  = (string) ($_POST['action'] ?? '');
$regCode = strtoupper(trim((string) ($_POST['reg_code'] ?? '')));

// Only the browser that created the registration may update it
$id = $_SESSION['regs'][$regCode] ?? null;
if (!$id) {
    json_out(['ok' => false, 'error' => 'Registration not found in this session. Please contact us with your registration ID.'], 403);
}

$pdo = db();

if ($action === 'razorpay_verify') {
    $orderId   = (string) ($_POST['razorpay_order_id'] ?? '');
    $paymentId = (string) ($_POST['razorpay_payment_id'] ?? '');
    $signature = (string) ($_POST['razorpay_signature'] ?? '');

    $stmt = $pdo->prepare('SELECT razorpay_order_id FROM registrations WHERE id = ?');
    $stmt->execute([$id]);
    $storedOrder = (string) $stmt->fetchColumn();

    if ($storedOrder === '' || !hash_equals($storedOrder, $orderId) || !razorpay_verify_signature($orderId, $paymentId, $signature)) {
        json_out(['ok' => false, 'error' => 'Payment could not be verified. If money was deducted, contact us with ID ' . $regCode . '.'], 400);
    }

    $pdo->prepare("UPDATE registrations SET payment_status = 'paid', razorpay_payment_id = ? WHERE id = ?")
        ->execute([$paymentId, $id]);

    json_out(['ok' => true, 'status' => 'paid', 'reg_code' => $regCode]);
}

if ($action === 'razorpay_failed') {
    $pdo->prepare("UPDATE registrations SET payment_status = 'failed' WHERE id = ? AND payment_status = 'pending'")
        ->execute([$id]);
    json_out(['ok' => true, 'status' => 'failed']);
}

if ($action === 'upi_ref') {
    $ref = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['payment_ref'] ?? '')));
    if (strlen($ref) < 6 || strlen($ref) > 40) {
        json_out(['ok' => false, 'error' => 'Please enter the UPI transaction / UTR number (usually 12 digits).'], 422);
    }
    $pdo->prepare('UPDATE registrations SET payment_ref = ? WHERE id = ?')->execute([$ref, $id]);
    json_out(['ok' => true, 'status' => 'pending_verification', 'reg_code' => $regCode]);
}

json_out(['ok' => false, 'error' => 'Unknown action'], 400);
