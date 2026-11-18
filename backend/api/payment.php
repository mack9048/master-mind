<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

api_begin('POST');

$action  = (string) ($_POST['action'] ?? '');
$regCode = strtoupper(trim((string) ($_POST['reg_code'] ?? '')));

// Only the browser that created the registration may update it
if (!reg_token_ok($regCode, $_POST['token'] ?? null)) {
    json_out(['ok' => false, 'error' => 'Registration not found. Please contact us with your registration ID.'], 403);
}

$pdo = db();
$find = $pdo->prepare('SELECT id FROM registrations WHERE reg_code = ?');
$find->execute([$regCode]);
$id = (int) $find->fetchColumn();
if (!$id) {
    json_out(['ok' => false, 'error' => 'Registration not found.'], 404);
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
