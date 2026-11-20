<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

api_begin('POST');

// Honeypot – real people never fill this hidden field
if (!empty($_POST['website'])) {
    json_out(['ok' => false, 'error' => 'Invalid submission.'], 400);
}

if (!registration_open()) {
    json_out(['ok' => false, 'error' => 'Registrations are closed for this edition.'], 410);
}

/* ---------- Validate ---------- */
$professions = ['Student', 'Designer', 'Marketer', 'Business Owner', 'Professional', 'Others',
                'Entrepreneur', 'Working Professional', 'Other'];

$name        = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')));
$email       = strtolower(trim((string) ($_POST['email'] ?? '')));
$phoneRaw    = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
$city        = trim((string) ($_POST['city'] ?? ''));
$profession  = trim((string) ($_POST['profession'] ?? ''));
$org         = trim((string) ($_POST['organization'] ?? ''));
$expectation = trim((string) ($_POST['expectation'] ?? ''));

// Normalise Indian numbers: allow 10 digits, or 91 / 0 prefixed
if (strlen($phoneRaw) === 12 && str_starts_with($phoneRaw, '91')) {
    $phoneRaw = substr($phoneRaw, 2);
} elseif (strlen($phoneRaw) === 11 && str_starts_with($phoneRaw, '0')) {
    $phoneRaw = substr($phoneRaw, 1);
}

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
    $errors['name'] = 'Please enter your full name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
    $errors['email'] = 'Please enter a valid email address.';
}
if (!preg_match('/^[6-9]\d{9}$/', $phoneRaw)) {
    $errors['phone'] = 'Please enter a valid 10-digit mobile number.';
}
if (mb_strlen($city) > 80) {
    $errors['city'] = 'Too long.';
}
if (!in_array($profession, $professions, true)) {
    $errors['profession'] = 'Please choose your current status.';
}
if (mb_strlen($org) > 160) {
    $errors['organization'] = 'Too long.';
}
if (mb_strlen($expectation) > 1000) {
    $errors['expectation'] = 'Please keep this under 1000 characters.';
}
if ($errors) {
    json_out(['ok' => false, 'error' => 'Please check the highlighted fields.', 'fields' => $errors], 422);
}

$phone  = '+91' . $phoneRaw;
$slug   = cfg('event.slug');
$fee    = (int) cfg('event.fee');
$mode   = 'upi';
$pdo    = db();

try {
    $pdo->beginTransaction();

    // Already registered with this number?
    $stmt = $pdo->prepare('SELECT * FROM registrations WHERE event_slug = ? AND phone = ? FOR UPDATE');
    $stmt->execute([$slug, $phone]);
    $existing = $stmt->fetch();

    if ($existing && $existing['payment_status'] === 'paid') {
        $pdo->rollBack();
        json_out([
            'ok'    => false,
            'error' => 'This mobile number is already registered and paid. Your registration ID is ' . $existing['reg_code'] . '.',
        ], 409);
    }

    // Capacity check (a returning "pending" person already holds a seat)
    $total = (int) cfg('event.seats', 0);
    if ($total > 0 && !($existing && $existing['payment_status'] === 'pending')) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_slug = ? AND payment_status IN ('paid','pending') FOR UPDATE");
        $c->execute([$slug]);
        if ((int) $c->fetchColumn() >= $total) {
            $pdo->rollBack();
            json_out(['ok' => false, 'error' => 'Sorry, all seats are booked. Call us to join the waitlist.'], 409);
        }
    }

    $fields = [
        'name'         => $name,
        'email'        => $email,
        'city'         => $city !== '' ? $city : null,
        'profession'   => $profession,
        'organization' => $org !== '' ? $org : null,
        'expectation'  => $expectation !== '' ? $expectation : null,
        'amount'       => $fee,
        'payment_mode' => $mode,
        'ip_address'   => client_ip(),
        'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ];

    if ($existing) {
        // Retry of an unpaid / failed registration – refresh details, keep the same ID
        $regCode = $existing['reg_code'];
        $id      = (int) $existing['id'];
        $sets    = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($fields)));
        $upd     = $pdo->prepare("UPDATE registrations SET $sets, payment_status = 'pending' WHERE id = :id");
        $upd->execute($fields + ['id' => $id]);
    } else {
        $regCode = new_reg_code();
        $cols    = array_merge(['reg_code', 'event_slug', 'phone'], array_keys($fields));
        $ins     = $pdo->prepare(
            'INSERT INTO registrations (' . implode(', ', $cols) . ') VALUES (:' . implode(', :', $cols) . ')'
        );
        $ins->execute(['reg_code' => $regCode, 'event_slug' => $slug, 'phone' => $phone] + $fields);
        $id = (int) $pdo->lastInsertId();
    }

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[mastermind] register: ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Could not save your booking (' . db_problem($ex) . '). Please call ' . cfg('event.phone') . ' to book.'], 500);
}

// Signed token proving this browser created the registration (used by api/payment.php)
$regToken = reg_token($regCode);

// UPI / manual mode – QR image uploaded by the institute, or generated from the UPI ID
$qrImage = null;
foreach (['upi-qr.png', 'upi-qr.jpg', 'upi-qr.jpeg', 'upi-qr.webp'] as $f) {
    if (is_file(dirname(__DIR__) . '/assets/img/' . $f)) {
        $qrImage = base_url() . '/assets/img/' . $f . '?v=' . filemtime(dirname(__DIR__) . '/assets/img/' . $f);
        break;
    }
}
$upiLink = sprintf(
    'upi://pay?pa=%s&pn=%s&am=%d&cu=INR&tn=%s',
    rawurlencode((string) cfg('payment.upi_id')),
    rawurlencode((string) cfg('payment.upi_name')),
    $fee,
    rawurlencode('Master Mind ' . $regCode)
);

json_out([
    'ok'       => true,
    'mode'     => 'upi',
    'reg_code' => $regCode,
    'token'    => $regToken,
    'upi'      => [
        'id'     => cfg('payment.upi_id'),
        'name'   => cfg('payment.upi_name'),
        'amount' => $fee,
        'link'   => cfg('payment.upi_id') ? $upiLink : null,
        'qr'     => $qrImage,
    ],
]);
