<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/mailer.php';

$slug = cfg('event.slug');
csrf_token(); // sets the CSRF cookie before any output

/* =========================================================
   Auth
   ========================================================= */
if (isset($_GET['logout'])) {
    admin_logout();
    header('Location: ./');
    exit;
}

$loginError = '';
if (!is_admin()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        usleep(400000); // slow down password guessing
        $hash  = (string) cfg('admin.pass_hash', '');
        $plain = (string) cfg('admin.pass', '');
        $given = (string) ($_POST['pass'] ?? '');
        $passOk = $hash !== '' ? password_verify($given, $hash) : ($plain !== '' && hash_equals($plain, $given));
        if (csrf_check($_POST['csrf'] ?? null)
            && ($hash !== '' || $plain !== '')
            && hash_equals((string) cfg('admin.user'), (string) ($_POST['user'] ?? ''))
            && $passOk) {
            admin_login();
            header('Location: ./');
            exit;
        }
        $loginError = ($hash === '' && $plain === '') ? 'Admin password is not set in config.php.' : 'Wrong username or password.';
    }
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin · Master Mind</title><meta name="robots" content="noindex"><link rel="stylesheet" href="admin.css?v=2"></head>
<body class="login">
<form method="post" class="login__box">
  <img src="logo.png" alt="Master Mind" width="160">
  <h1>Bookings dashboard</h1>
  <?php if ($loginError): ?><p class="err"><?= e($loginError) ?></p><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>Username<input name="user" autocomplete="username" required></label>
  <label>Password<input name="pass" type="password" autocomplete="current-password" required></label>
  <button name="login" value="1">Sign in</button>
</form>
</body></html>
<?php
    exit;
}

$pdo = db();

/* =========================================================
   Auto-upgrade the table (adds columns used by the dashboard)
   ========================================================= */
$cols = $pdo->query("SHOW COLUMNS FROM registrations")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('admin_note', $cols, true)) {
    $pdo->exec("ALTER TABLE registrations ADD admin_note TEXT NULL AFTER attended");
}
if (!in_array('paid_at', $cols, true)) {
    $pdo->exec("ALTER TABLE registrations ADD paid_at DATETIME NULL AFTER payment_ref");
}
ensure_ticket_column($pdo);
allow_multi_bookings($pdo);
if (!in_array('checked_in_at', $cols, true)) {
    $pdo->exec("ALTER TABLE registrations ADD checked_in_at DATETIME NULL AFTER attended");
}

/**
 * Look up a booking from whatever was typed or scanned:
 * a Reg ID, a phone number, or a ticket link containing ?code=MM26-…
 */
function find_for_checkin(PDO $pdo, string $slug, string $input): ?array
{
    $input = trim($input);
    if (preg_match('/[?&]code=([A-Za-z0-9-]+)/', $input, $m)) {
        $input = $m[1];
    }
    $code = strtoupper($input);
    $st = $pdo->prepare('SELECT * FROM registrations WHERE event_slug = ? AND reg_code = ?');
    $st->execute([$slug, $code]);
    if ($r = $st->fetch()) {
        return $r;
    }
    // Phone number: may belong to several bookings (family / friends)
    $st = $pdo->prepare("SELECT * FROM registrations WHERE event_slug = ? AND phone = ? AND payment_status <> 'cancelled' ORDER BY id");
    $st->execute([$slug, norm_phone($code) ?? '-']);
    $rows = $st->fetchAll();
    if (count($rows) > 1) {
        // prefer the only paid & not-yet-arrived person; otherwise ask for the Reg ID
        $waiting = array_values(array_filter($rows, fn ($x) => $x['payment_status'] === 'paid' && !(int) $x['attended']));
        if (count($waiting) === 1) {
            return $waiting[0];
        }
        return ['__multiple' => $rows];
    }
    return $rows[0] ?? null;
}

/* ---------- Camera / scanner check-in (JSON) ---------- */
if (($_GET['api'] ?? '') === 'checkin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!csrf_check($_POST['csrf'] ?? null)) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired – reload the page.']);
        exit;
    }
    $input = (string) ($_POST['code'] ?? '');
    $r = find_for_checkin($pdo, $slug, $input);
    $out = ['input' => mb_substr($input, 0, 80)];
    if ($r && isset($r['__multiple'])) {
        $list = array_map(fn ($x) => $x['name'] . ' (' . $x['reg_code'] . ($x['attended'] ? ', in' : '') . ')', $r['__multiple']);
        $out += ['status' => 'multiple', 'message' => count($list) . ' bookings on this phone – scan the ticket or type the Reg ID', 'list' => $list];
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!$r) {
        $out += ['status' => 'notfound', 'message' => 'No booking found'];
    } else {
        $out += ['name' => $r['name'], 'reg_code' => $r['reg_code'], 'phone' => $r['phone'], 'profession' => $r['profession'], 'id' => (int) $r['id']];
        if (in_array($r['payment_status'], ['cancelled', 'failed'], true)) {
            $out += ['status' => 'cancelled', 'message' => 'Booking is ' . $r['payment_status']];
        } elseif ($r['payment_status'] !== 'paid') {
            $out += ['status' => 'unpaid', 'message' => 'NOT PAID – collect ₹' . (int) $r['amount'] . ' before entry'];
        } elseif ((int) $r['attended'] === 1) {
            $out += ['status' => 'already', 'message' => 'Already checked in' . ($r['checked_in_at'] ? ' at ' . date('h:i A', strtotime($r['checked_in_at'])) : '')];
        } else {
            $pdo->prepare('UPDATE registrations SET attended = 1, checked_in_at = NOW() WHERE id = ?')->execute([$r['id']]);
            $out += ['status' => 'ok', 'message' => 'Checked in ✓'];
        }
    }
    $c = $pdo->prepare("SELECT SUM(attended = 1) a, SUM(payment_status = 'paid') p FROM registrations WHERE event_slug = ?");
    $c->execute([$slug]);
    $cnt = $c->fetch();
    $out['checked_in'] = (int) $cnt['a'];
    $out['paid'] = (int) $cnt['p'];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- PDF ticket download (admin) ---------- */
if (isset($_GET['ticket'])) {
    $st = $pdo->prepare('SELECT * FROM registrations WHERE id = ? AND event_slug = ?');
    $st->execute([(int) $_GET['ticket'], $slug]);
    if ($t = $st->fetch()) {
        $pdf = ticket_pdf($t);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . ticket_filename($t) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
}

/* =========================================================
   Helpers
   ========================================================= */
$PROFESSIONS = ['Student', 'Designer', 'Marketer', 'Business Owner', 'Professional', 'Others'];
$STATUSES    = ['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled'];

function go(array $params = [], string $msg = ''): void
{
    if ($msg !== '') {
        $params['msg'] = $msg;
    }
    header('Location: ./' . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

function norm_phone(string $raw): ?string
{
    $d = preg_replace('/\D+/', '', $raw);
    if (strlen($d) === 12 && str_starts_with($d, '91')) {
        $d = substr($d, 2);
    } elseif (strlen($d) === 11 && str_starts_with($d, '0')) {
        $d = substr($d, 1);
    }
    return preg_match('/^[6-9]\d{9}$/', $d) ? '+91' . $d : null;
}

function wa_confirm_link(array $r): string
{
    $msg = "Hi {$r['name']}, your seat for " . cfg('event.series') . ' – ' . cfg('event.title')
        . " is confirmed ✅\n\nRegistration ID: {$r['reg_code']}\nDate: " . cfg('event.date_label')
        . "\nTime: " . cfg('event.time_label') . "\nVenue: " . cfg('event.venue')
        . "\n\n🎟️ Your entry ticket (PDF): " . ticket_url($r['reg_code'])
        . "\n\nPlease show the ticket or this ID at the venue. See you there!";
    return 'https://wa.me/' . ltrim($r['phone'], '+') . '?text=' . rawurlencode($msg);
}

function wa_reminder_link(array $r): string
{
    $msg = "Hi {$r['name']}, we received your registration ({$r['reg_code']}) for " . cfg('event.series') . ' – ' . cfg('event.title')
        . ". Your payment of ₹" . (int) $r['amount'] . " is still pending. Please pay to confirm your seat. Thank you!";
    return 'https://wa.me/' . ltrim($r['phone'], '+') . '?text=' . rawurlencode($msg);
}

function status_pill(array $r): string
{
    if ($r['payment_status'] === 'pending' && $r['payment_ref']) {
        return '<span class="pill pill--verify">To verify</span>';
    }
    $label = ['pending' => 'Unpaid', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled'][$r['payment_status']] ?? $r['payment_status'];
    return '<span class="pill pill--' . e($r['payment_status']) . '">' . e($label) . '</span>';
}

function find_reg(PDO $pdo, int $id, string $slug): ?array
{
    $st = $pdo->prepare('SELECT * FROM registrations WHERE id = ? AND event_slug = ?');
    $st->execute([$id, $slug]);
    return $st->fetch() ?: null;
}

/* =========================================================
   Actions (POST)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        go([], 'Session expired, please try again.');
    }
    $do   = (string) ($_POST['do'] ?? '');
    $id   = (int) ($_POST['id'] ?? 0);
    $back = (string) ($_POST['back'] ?? '');
    parse_str(ltrim($back, '?'), $backParams);
    $backParams = array_intersect_key($backParams, array_flip(['view', 'tab', 'q', 'id', 'p']));

    switch ($do) {
        case 'paid':
            $pdo->prepare("UPDATE registrations SET payment_status = 'paid', paid_at = COALESCE(paid_at, NOW()) WHERE id = ? AND event_slug = ?")->execute([$id, $slug]);
            auto_send_ticket($pdo, $id);
            go($backParams, 'Payment confirmed — seat confirmed.' . (mail_configured() ? ' Ticket e-mailed.' : ''));
        case 'send_ticket':
            $r = find_reg($pdo, $id, $slug);
            $res = $r ? send_ticket_email($pdo, $r) : ['ok' => false, 'error' => 'Booking not found.'];
            go($backParams, $res['ok'] ? "Ticket e-mailed to {$r['email']}." : $res['error']);
        case 'pending':
            $pdo->prepare("UPDATE registrations SET payment_status = 'pending', paid_at = NULL WHERE id = ? AND event_slug = ?")->execute([$id, $slug]);
            go($backParams, 'Marked as unpaid.');
        case 'reject':
            // UTR could not be found in the bank statement: keep booking, clear the reference
            $pdo->prepare("UPDATE registrations SET payment_ref = NULL, payment_status = 'pending' WHERE id = ? AND event_slug = ?")->execute([$id, $slug]);
            go($backParams, 'Payment reference rejected — booking is unpaid again.');
        case 'cancelled':
            $pdo->prepare("UPDATE registrations SET payment_status = 'cancelled' WHERE id = ? AND event_slug = ?")->execute([$id, $slug]);
            go($backParams, 'Booking cancelled — seat released.');
        case 'attended':
            $pdo->prepare('UPDATE registrations SET attended = 1 - attended, checked_in_at = IF(attended = 1, COALESCE(checked_in_at, NOW()), NULL) WHERE id = ? AND event_slug = ?')->execute([$id, $slug]);
            go($backParams, 'Attendance updated.');
        case 'delete':
            $pdo->prepare('DELETE FROM registrations WHERE id = ? AND event_slug = ?')->execute([$id, $slug]);
            go(($backParams['view'] ?? '') === 'bookings' ? $backParams : ['view' => 'bookings'], 'Booking deleted.');

        case 'checkin':
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $r = find_for_checkin($pdo, $slug, $code);
            if ($r && isset($r['__multiple'])) {
                go(['view' => 'bookings', 'q' => norm_phone($code) ?? $code], 'Several bookings use this phone number – check in each person by Reg ID.');
            }
            if (!$r) {
                go(['view' => 'checkin'], "No booking found for “{$code}”.");
            }
            if ($r['payment_status'] !== 'paid') {
                go(['view' => 'booking', 'id' => $r['id']], "{$r['name']} ({$r['reg_code']}) has NOT paid yet — verify payment before check-in.");
            }
            $pdo->prepare('UPDATE registrations SET attended = 1, checked_in_at = COALESCE(checked_in_at, NOW()) WHERE id = ?')->execute([$r['id']]);
            go(['view' => 'checkin'], "✓ Checked in: {$r['name']} ({$r['reg_code']})");

        case 'save':
        case 'create':
            $name  = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = norm_phone((string) ($_POST['phone'] ?? ''));
            $prof  = (string) ($_POST['profession'] ?? '');
            $status = (string) ($_POST['payment_status'] ?? 'pending');
            $err = [];
            if (mb_strlen($name) < 2) { $err[] = 'name'; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err[] = 'email'; }
            if (!$phone) { $err[] = 'phone'; }
            if (!isset($STATUSES[$status])) { $status = 'pending'; }
            if ($err) {
                go($do === 'create' ? ['view' => 'add'] : ['view' => 'booking', 'id' => $id], 'Please check: ' . implode(', ', $err) . '.');
            }
            $data = [
                'name'           => $name,
                'email'          => $email,
                'phone'          => $phone,
                'city'           => trim((string) ($_POST['city'] ?? '')) ?: null,
                'profession'     => $prof ?: null,
                'organization'   => trim((string) ($_POST['organization'] ?? '')) ?: null,
                'amount'         => max(0, (int) ($_POST['amount'] ?? cfg('event.fee'))),
                'payment_mode'   => in_array($_POST['payment_mode'] ?? '', ['razorpay', 'upi', 'cash', 'bank', 'free'], true) ? $_POST['payment_mode'] : 'upi',
                'payment_status' => $status,
                'payment_ref'    => trim((string) ($_POST['payment_ref'] ?? '')) ?: null,
                'admin_note'     => trim((string) ($_POST['admin_note'] ?? '')) ?: null,
                'attended'       => !empty($_POST['attended']) ? 1 : 0,
            ];
            try {
                if ($do === 'create') {
                    $data['reg_code']   = new_reg_code();
                    $data['event_slug'] = $slug;
                    $data['ip_address'] = 'admin';
                    $cols = array_keys($data);
                    $pdo->prepare('INSERT INTO registrations (' . implode(',', $cols) . ', paid_at) VALUES (:' . implode(', :', $cols) . ', ' . ($status === 'paid' ? 'NOW()' : 'NULL') . ')')->execute($data);
                    $newId = (int) $pdo->lastInsertId();
                    if ($status === 'paid') {
                        auto_send_ticket($pdo, $newId);
                    }
                    go(['view' => 'booking', 'id' => $newId], "Booking {$data['reg_code']} added.");
                }
                $before = find_reg($pdo, $id, $slug);
                $sets = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($data)));
                $paidAt = $status === 'paid' ? ', paid_at = COALESCE(paid_at, NOW())' : ', paid_at = NULL';
                $pdo->prepare("UPDATE registrations SET $sets $paidAt WHERE id = :id AND event_slug = :slug")
                    ->execute($data + ['id' => $id, 'slug' => $slug]);
                if ($status === 'paid' && $before && $before['payment_status'] !== 'paid') {
                    auto_send_ticket($pdo, $id);
                }
                go(['view' => 'booking', 'id' => $id], 'Booking saved.');
            } catch (PDOException $ex) {
                $dup = str_contains($ex->getMessage(), 'Duplicate');
                go($do === 'create' ? ['view' => 'add'] : ['view' => 'booking', 'id' => $id], $dup ? 'Could not save: duplicate Reg ID, please try again.' : 'Could not save: database error.');
            }
    }
    go();
}

/* =========================================================
   CSV export
   ========================================================= */
if (isset($_GET['export'])) {
    $st = $pdo->prepare('SELECT * FROM registrations WHERE event_slug = ? ORDER BY created_at');
    $st->execute([$slug]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mastermind-' . $slug . '-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, ['Reg ID', 'Name', 'Phone', 'Email', 'Status', 'City', 'Amount', 'Mode', 'Payment', 'UTR / Ref', 'Paid at', 'Attended', 'Note', 'Registered at']);
    foreach ($st as $r) {
        fputcsv($out, [$r['reg_code'], $r['name'], $r['phone'], $r['email'], $r['profession'], $r['city'], $r['amount'], $r['payment_mode'], $r['payment_status'], $r['payment_ref'], $r['paid_at'], $r['attended'] ? 'Yes' : 'No', $r['admin_note'], $r['created_at']]);
    }
    exit;
}

/* =========================================================
   Data for views
   ========================================================= */
$view = $_GET['view'] ?? 'dashboard';
$msg  = (string) ($_GET['msg'] ?? '');
$csrf = csrf_token();
$self = '?' . http_build_query(array_diff_key($_GET, ['msg' => 1]));

$st = $pdo->prepare("SELECT
    COUNT(*) total,
    SUM(payment_status = 'paid') paid,
    SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) revenue,
    SUM(payment_status = 'pending' AND payment_ref IS NOT NULL) to_verify,
    SUM(payment_status = 'pending' AND payment_ref IS NULL) unpaid,
    SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) due,
    SUM(payment_status IN ('cancelled','failed')) cancelled,
    SUM(attended = 1) attended,
    SUM(DATE(created_at) = CURDATE()) today
  FROM registrations WHERE event_slug = ?");
$st->execute([$slug]);
$S = array_map('intval', $st->fetch() ?: []);
$seats     = (int) cfg('event.seats');
$seatsLeft = $seats > 0 ? max(0, $seats - $S['paid'] - $S['to_verify'] - $S['unpaid']) : null;

/** Bookings list query by tab + search */
function list_bookings(PDO $pdo, string $slug, string $tab, string $q, int $limit, int $offset, ?int &$count = null): array
{
    $where = ['event_slug = ?'];
    $args  = [$slug];
    $map = [
        'verify'    => "payment_status = 'pending' AND payment_ref IS NOT NULL",
        'unpaid'    => "payment_status = 'pending' AND payment_ref IS NULL",
        'paid'      => "payment_status = 'paid'",
        'cancelled' => "payment_status IN ('cancelled','failed')",
        'attended'  => 'attended = 1',
    ];
    if (isset($map[$tab])) {
        $where[] = $map[$tab];
    }
    if ($q !== '') {
        $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR reg_code LIKE ? OR payment_ref LIKE ?)';
        array_push($args, ...array_fill(0, 5, '%' . $q . '%'));
    }
    $w = implode(' AND ', $where);
    $c = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE $w");
    $c->execute($args);
    $count = (int) $c->fetchColumn();
    $st = $pdo->prepare("SELECT * FROM registrations WHERE $w ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $st->execute($args);
    return $st->fetchAll();
}

/** One table row of actions, reused on several views */
function row_actions(array $r, string $csrf, string $back): string
{
    ob_start(); ?>
    <form method="post" class="acts">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
      <?php if ($r['payment_status'] === 'pending' && $r['payment_ref']): ?>
        <button name="do" value="paid" class="b-ok">✓ Verify</button>
        <button name="do" value="reject" class="b-ghost" onclick="return confirm('Reject this UTR? The booking becomes unpaid.')">Reject</button>
      <?php elseif ($r['payment_status'] === 'pending'): ?>
        <button name="do" value="paid" class="b-ok">Mark paid</button>
        <a class="b-ghost" href="<?= e(wa_reminder_link($r)) ?>" target="_blank">Remind</a>
      <?php elseif ($r['payment_status'] === 'paid'): ?>
        <a class="b-wa" href="<?= e(wa_confirm_link($r)) ?>" target="_blank" title="WhatsApp confirmation with ticket link">WhatsApp</a>
        <button name="do" value="send_ticket" class="b-ghost" title="E-mail the PDF ticket"><?= !empty($r['ticket_sent_at']) ? 'Resend ✉' : 'Send ✉' ?></button>
        <a class="b-ghost" href="?ticket=<?= (int) $r['id'] ?>" target="_blank" title="Open PDF ticket">Ticket</a>
      <?php endif; ?>
      <a class="b-ghost" href="?view=booking&id=<?= (int) $r['id'] ?>">Edit</a>
      <button name="do" value="delete" class="b-icon" title="Delete booking" onclick="return confirm('Delete <?= e(addslashes($r['name'])) ?> (<?= e($r['reg_code']) ?>) permanently?')">🗑</button>
    </form>
    <?php return (string) ob_get_clean();
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard · Master Mind</title>
<meta name="robots" content="noindex">
<link rel="icon" href="logo.png">
<link rel="stylesheet" href="admin.css?v=2">
</head>
<body>
<div class="shell">
  <aside class="side">
    <a href="./" class="side__logo"><img src="logo.png" alt="Master Mind"></a>
    <p class="side__event"><?= e(cfg('event.title')) ?><small><?= e(cfg('event.date_label')) ?></small></p>
    <nav class="side__nav">
      <a href="./" class="<?= $view === 'dashboard' ? 'on' : '' ?>"><i>▦</i>Dashboard</a>
      <a href="?view=bookings&tab=verify" class="<?= $view === 'bookings' && ($_GET['tab'] ?? '') === 'verify' ? 'on' : '' ?>"><i>⚑</i>Verify payments<?php if ($S['to_verify']): ?><b><?= $S['to_verify'] ?></b><?php endif; ?></a>
      <a href="?view=bookings" class="<?= $view === 'bookings' && ($_GET['tab'] ?? '') !== 'verify' ? 'on' : '' ?>"><i>☰</i>All bookings</a>
      <a href="?view=add" class="<?= $view === 'add' ? 'on' : '' ?>"><i>＋</i>Add booking</a>
      <a href="?view=checkin" class="<?= $view === 'checkin' ? 'on' : '' ?>"><i>✓</i>Check-in</a>
      <a href="?export=1"><i>⇩</i>Export CSV</a>
    </nav>
    <div class="side__foot">
      <a href="<?= e(cfg('frontend_url')) ?>" target="_blank">View website ↗</a>
      <a href="?logout=1">Log out</a>
    </div>
  </aside>

  <main class="main">
    <?php if ($msg !== ''): ?><div class="flash"><?= e($msg) ?></div><?php endif; ?>

<?php /* ================= DASHBOARD ================= */ if ($view === 'dashboard'): ?>
    <header class="head"><h1>Dashboard</h1><p>Overview of bookings and payments</p></header>

    <section class="stats">
      <div class="stat"><span>Total bookings</span><b><?= $S['total'] - $S['cancelled'] ?></b><small><?= $S['today'] ?> today</small></div>
      <div class="stat stat--paid"><span>Paid · confirmed</span><b><?= $S['paid'] ?></b><small>₹<?= number_format($S['revenue']) ?> collected</small></div>
      <a class="stat stat--verify" href="?view=bookings&tab=verify"><span>To verify</span><b><?= $S['to_verify'] ?></b><small>UTR submitted — check bank</small></a>
      <a class="stat stat--unpaid" href="?view=bookings&tab=unpaid"><span>Unpaid</span><b><?= $S['unpaid'] ?></b><small>₹<?= number_format($S['due']) ?> due</small></a>
      <div class="stat"><span>Seats left</span><b><?= $seatsLeft === null ? '∞' : $seatsLeft ?></b><small><?= $seats > 0 ? 'of ' . $seats : 'unlimited' ?></small></div>
      <div class="stat"><span>Checked in</span><b><?= $S['attended'] ?></b><small>of <?= $S['paid'] ?> paid</small></div>
    </section>

    <?php if ($seats > 0): $fill = $seats ? min(100, round(100 * ($S['paid'] + $S['to_verify'] + $S['unpaid']) / $seats)) : 0; $pf = $seats ? min(100, round(100 * $S['paid'] / $seats)) : 0; ?>
    <section class="card">
      <div class="card__head"><h2>Seat capacity</h2><span><?= $fill ?>% booked</span></div>
      <div class="cap"><span class="cap__paid" style="width:<?= $pf ?>%"></span><span class="cap__pend" style="width:<?= max(0, $fill - $pf) ?>%"></span></div>
      <p class="legend"><i class="lg lg--paid"></i>Paid <?= $S['paid'] ?> <i class="lg lg--pend"></i>Pending <?= $S['to_verify'] + $S['unpaid'] ?> <i class="lg"></i>Free <?= $seatsLeft ?></p>
    </section>
    <?php endif; ?>

    <div class="grid2">
      <section class="card">
        <div class="card__head"><h2>Payments to verify</h2><a href="?view=bookings&tab=verify">See all →</a></div>
        <?php $rows = list_bookings($pdo, $slug, 'verify', '', 8, 0); ?>
        <?php if (!$rows): ?><p class="empty">Nothing to verify. 🎉</p><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <div class="item">
            <div><strong><?= e($r['name']) ?></strong> <code><?= e($r['reg_code']) ?></code><br><small>UTR <code><?= e($r['payment_ref']) ?></code> · ₹<?= (int) $r['amount'] ?> · <?= e(date('d M, h:i A', strtotime($r['updated_at']))) ?></small></div>
            <?= row_actions($r, $csrf, $self) ?>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="card">
        <div class="card__head"><h2>Latest bookings</h2><a href="?view=bookings">See all →</a></div>
        <?php $rows = list_bookings($pdo, $slug, '', '', 8, 0); ?>
        <?php if (!$rows): ?><p class="empty">No bookings yet.</p><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <a class="item item--link" href="?view=booking&id=<?= (int) $r['id'] ?>">
            <div><strong><?= e($r['name']) ?></strong><br><small><?= e($r['phone']) ?> · <?= e($r['profession'] ?: '—') ?> · <?= e(date('d M, h:i A', strtotime($r['created_at']))) ?></small></div>
            <?= status_pill($r) ?>
          </a>
        <?php endforeach; ?>
      </section>
    </div>

    <?php
      $st = $pdo->prepare("SELECT COALESCE(profession,'—') p, COUNT(*) c FROM registrations WHERE event_slug = ? AND payment_status <> 'cancelled' GROUP BY p ORDER BY c DESC");
      $st->execute([$slug]); $byProf = $st->fetchAll();
      $st = $pdo->prepare("SELECT DATE(created_at) d, COUNT(*) c FROM registrations WHERE event_slug = ? AND created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY d");
      $st->execute([$slug]); $byDay = array_column($st->fetchAll(), 'c', 'd');
      $maxDay = max([1, ...array_map('intval', $byDay)]);
      $maxProf = max([1, ...array_map(fn ($x) => (int) $x['c'], $byProf)]);
    ?>
    <div class="grid2">
      <section class="card">
        <div class="card__head"><h2>Bookings · last 14 days</h2></div>
        <div class="bars">
          <?php for ($i = 13; $i >= 0; $i--): $d = date('Y-m-d', strtotime("-$i day")); $c = (int) ($byDay[$d] ?? 0); ?>
            <div class="bar" title="<?= e(date('d M', strtotime($d))) ?>: <?= $c ?>"><span style="height:<?= round(100 * $c / $maxDay) ?>%"></span><em><?= $c ?: '' ?></em><small><?= date('d', strtotime($d)) ?></small></div>
          <?php endfor; ?>
        </div>
      </section>
      <section class="card">
        <div class="card__head"><h2>Who is coming</h2></div>
        <?php if (!$byProf): ?><p class="empty">No data yet.</p><?php endif; ?>
        <?php foreach ($byProf as $x): ?>
          <div class="hbar"><span><?= e($x['p']) ?></span><div><i style="width:<?= round(100 * $x['c'] / $maxProf) ?>%"></i></div><b><?= (int) $x['c'] ?></b></div>
        <?php endforeach; ?>
      </section>
    </div>

<?php /* ================= BOOKINGS LIST ================= */ elseif ($view === 'bookings'):
    $tab = (string) ($_GET['tab'] ?? '');
    $q   = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['p'] ?? 1));
    $per = 50;
    $rows = list_bookings($pdo, $slug, $tab, $q, $per, ($page - 1) * $per, $count);
    $pages = max(1, (int) ceil($count / $per));
    $tabs = ['' => ['All', $S['total']], 'verify' => ['To verify', $S['to_verify']], 'unpaid' => ['Unpaid', $S['unpaid']], 'paid' => ['Paid', $S['paid']], 'cancelled' => ['Cancelled', $S['cancelled']], 'attended' => ['Checked in', $S['attended']]];
?>
    <header class="head head--row"><div><h1><?= $tab === 'verify' ? 'Verify payments' : 'Bookings' ?></h1>
      <p><?= $tab === 'verify' ? 'Match each UTR with your bank / UPI app, then press Verify.' : 'Create, edit, delete and send tickets for every booking.' ?></p></div>
      <a class="b-primary" href="?view=add">＋ Add booking</a></header>

    <form class="filters" method="get">
      <input type="hidden" name="view" value="bookings"><input type="hidden" name="tab" value="<?= e($tab) ?>">
      <div class="tabs">
        <?php foreach ($tabs as $k => [$label, $n]): ?>
          <a class="<?= $tab === $k ? 'on' : '' ?>" href="?<?= e(http_build_query(array_filter(['view' => 'bookings', 'tab' => $k, 'q' => $q]))) ?>"><?= $label ?> <b><?= $n ?></b></a>
        <?php endforeach; ?>
      </div>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, phone, email, Reg ID, UTR">
      <button>Search</button>
    </form>

    <div class="table">
      <table>
        <thead><tr><th>Reg ID</th><th>Name</th><th>Contact</th><th>Status</th><th>Payment</th><th>Booked</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">No bookings found.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><a href="?view=booking&id=<?= (int) $r['id'] ?>"><code><?= e($r['reg_code']) ?></code></a><?php if ($r['attended']): ?><br><span class="pill pill--att">checked in</span><?php endif; ?></td>
            <td><strong><?= e($r['name']) ?></strong><?php if ($r['admin_note']): ?><br><small title="<?= e($r['admin_note']) ?>">📝 <?= e(mb_strimwidth($r['admin_note'], 0, 40, '…')) ?></small><?php endif; ?></td>
            <td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a><br><small><?= e($r['email']) ?></small></td>
            <td><?= e($r['profession'] ?: '—') ?></td>
            <td><?= status_pill($r) ?><?php if (!empty($r['ticket_sent_at'])): ?> <span class="pill pill--sent" title="Ticket e-mailed <?= e($r['ticket_sent_at']) ?>">✉ sent</span><?php endif; ?><br><small>₹<?= (int) $r['amount'] ?> · <?= e($r['payment_mode']) ?><?= $r['payment_ref'] ? ' · <code>' . e($r['payment_ref']) . '</code>' : '' ?></small></td>
            <td><small><?= e(date('d M, h:i A', strtotime($r['created_at']))) ?></small></td>
            <td><?= row_actions($r, $csrf, $self) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
      <nav class="pager">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a class="<?= $i === $page ? 'on' : '' ?>" href="?<?= e(http_build_query(array_filter(['view' => 'bookings', 'tab' => $tab, 'q' => $q, 'p' => $i]))) ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>

<?php /* ================= SINGLE BOOKING ================= */ elseif ($view === 'booking'):
    $r = find_reg($pdo, (int) ($_GET['id'] ?? 0), $slug);
    if (!$r): ?>
      <header class="head"><h1>Booking not found</h1></header><p><a href="?view=bookings">← Back to bookings</a></p>
    <?php else: ?>
    <header class="head head--row">
      <div><p class="crumb"><a href="?view=bookings">Bookings</a> / <?= e($r['reg_code']) ?></p><h1><?= e($r['name']) ?> <?= status_pill($r) ?></h1>
        <p>Booked <?= e(date('d M Y, h:i A', strtotime($r['created_at']))) ?><?= $r['paid_at'] ? ' · Paid ' . e(date('d M, h:i A', strtotime($r['paid_at']))) : '' ?></p></div>
      <?= row_actions($r, $csrf, $self) ?>
    </header>

    <div class="grid2 grid2--wide">
      <form method="post" class="card form">
        <h2>Booking details</h2>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <div class="f2">
          <label>Full name<input name="name" value="<?= e($r['name']) ?>" required></label>
          <label>Phone<input name="phone" value="<?= e($r['phone']) ?>" required></label>
          <label>Email<input name="email" type="email" value="<?= e($r['email']) ?>" required></label>
          <label>Current status<select name="profession"><option value="">—</option><?php foreach (array_unique([...$PROFESSIONS, (string) $r['profession']]) as $p): if ($p === '') continue; ?><option <?= $p === $r['profession'] ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></label>
          <label>City<input name="city" value="<?= e($r['city']) ?>"></label>
          <label>College / Company<input name="organization" value="<?= e($r['organization']) ?>"></label>
        </div>
        <h2>Payment</h2>
        <div class="f2">
          <label>Payment status<select name="payment_status"><?php foreach ($STATUSES as $k => $label): ?><option value="<?= $k ?>" <?= $k === $r['payment_status'] ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
          <label>Amount (₹)<input name="amount" type="number" min="0" value="<?= (int) $r['amount'] ?>"></label>
          <label>Payment mode<select name="payment_mode"><?php foreach (['razorpay' => 'Razorpay (online)', 'upi' => 'UPI', 'cash' => 'Cash', 'bank' => 'Bank transfer', 'free' => 'Free / Complimentary'] as $k => $label): ?><option value="<?= $k ?>" <?= $k === $r['payment_mode'] ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
          <label>UTR / Reference<input name="payment_ref" value="<?= e($r['payment_ref']) ?>"></label>
        </div>
        <label>Internal note<textarea name="admin_note" rows="3" placeholder="Only visible to admins"><?= e($r['admin_note']) ?></textarea></label>
        <label class="check"><input type="checkbox" name="attended" value="1" <?= $r['attended'] ? 'checked' : '' ?>> Checked in at venue</label>
        <div class="form__foot"><button name="do" value="save" class="b-primary">Save changes</button></div>
      </form>

      <div class="stack">
        <section class="card">
          <h2>Ticket & confirmation</h2>
          <?php if ($r['payment_status'] === 'paid'): ?>
          <form method="post" class="qa">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
            <a class="b-ghost" href="?ticket=<?= (int) $r['id'] ?>" target="_blank">🎟 View / download PDF ticket</a>
            <button name="do" value="send_ticket" class="b-primary"><?= !empty($r['ticket_sent_at']) ? 'Resend ticket by e-mail' : 'E-mail ticket to ' . e($r['email']) ?></button>
            <p class="muted"><?= !empty($r['ticket_sent_at']) ? '✓ Ticket e-mailed ' . e(date('d M, h:i A', strtotime($r['ticket_sent_at']))) : 'Ticket not e-mailed yet.' ?><?= mail_configured() ? '' : ' (E-mail not set up in config.php)' ?></p>
          </form>
          <?php else: ?>
            <p class="muted">The PDF ticket becomes available once the booking is marked <b>Paid</b>.</p>
          <?php endif; ?>
          <h2 style="margin-top:18px">Quick actions</h2>
          <div class="qa">
            <a class="b-wa" href="<?= e(wa_confirm_link($r)) ?>" target="_blank">Send confirmation + ticket link on WhatsApp</a>
            <a class="b-ghost" href="<?= e(wa_reminder_link($r)) ?>" target="_blank">Send payment reminder</a>
            <a class="b-ghost" href="tel:<?= e($r['phone']) ?>">Call <?= e($r['phone']) ?></a>
            <a class="b-ghost" href="mailto:<?= e($r['email']) ?>">Email <?= e($r['email']) ?></a>
          </div>
        </section>
        <?php if ($r['expectation']): ?>
        <section class="card"><h2>Wants to learn</h2><p><?= nl2br(e($r['expectation'])) ?></p></section>
        <?php endif; ?>
        <section class="card">
          <h2>Danger zone</h2>
          <form method="post" class="qa">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
            <?php if ($r['payment_status'] !== 'cancelled'): ?><button name="do" value="cancelled" class="b-ghost" onclick="return confirm('Cancel this booking and release the seat?')">Cancel booking</button><?php endif; ?>
            <button name="do" value="delete" class="b-danger" onclick="return confirm('Delete this booking permanently?')">Delete permanently</button>
          </form>
          <p class="muted">IP <?= e($r['ip_address']) ?> · updated <?= e(date('d M, h:i A', strtotime($r['updated_at']))) ?></p>
        </section>
      </div>
    </div>
    <?php endif; ?>

<?php /* ================= ADD BOOKING ================= */ elseif ($view === 'add'): ?>
    <header class="head"><h1>Add booking</h1><p>For walk-ins, phone bookings, cash payments or complimentary seats.</p></header>
    <form method="post" class="card form form--narrow">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <div class="f2">
        <label>Full name<input name="name" required></label>
        <label>Phone<input name="phone" required placeholder="10-digit mobile"></label>
        <label>Email<input name="email" type="email" required></label>
        <label>Current status<select name="profession"><option value="">—</option><?php foreach ($PROFESSIONS as $p): ?><option><?= e($p) ?></option><?php endforeach; ?></select></label>
        <label>Payment status<select name="payment_status"><option value="paid">Paid</option><option value="pending">Unpaid</option></select></label>
        <label>Payment mode<select name="payment_mode"><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank">Bank transfer</option><option value="free">Free / Complimentary</option></select></label>
        <label>Amount (₹)<input name="amount" type="number" min="0" value="<?= (int) cfg('event.fee') ?>"></label>
        <label>UTR / Reference<input name="payment_ref"></label>
      </div>
      <label>Internal note<textarea name="admin_note" rows="2"></textarea></label>
      <div class="form__foot"><button name="do" value="create" class="b-primary">Add booking</button></div>
    </form>

<?php /* ================= CHECK-IN ================= */ elseif ($view === 'checkin'):
    $st = $pdo->prepare('SELECT name, reg_code, checked_in_at FROM registrations WHERE event_slug = ? AND attended = 1 ORDER BY checked_in_at DESC LIMIT 12');
    $st->execute([$slug]);
    $recent = $st->fetchAll();
?>
    <header class="head"><h1>Check-in</h1><p>Scan the QR code on the ticket with the camera, use a barcode scanner, or type the Reg ID / phone number.</p></header>

    <section class="scan card" id="scan" data-csrf="<?= e($csrf) ?>">
      <div class="scan__cam">
        <video id="scanVideo" playsinline muted></video>
        <div class="scan__frame" aria-hidden="true"></div>
        <div class="scan__idle" id="scanIdle">
          <button type="button" class="b-primary scan__start" id="scanStart">📷 Scan QR with camera</button>
          <p class="muted">Allow camera access when asked. Works on phones and laptops (HTTPS).</p>
        </div>
        <div class="scan__result" id="scanResult" hidden></div>
      </div>
      <div class="scan__side">
        <form class="checkin" id="manualForm" method="post">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input name="code" id="manualCode" placeholder="MM26-XXXXXX or 98XXXXXXXX" autocomplete="off" required>
          <button name="do" value="checkin" class="b-primary">Check in</button>
        </form>
        <div class="scan__controls">
          <button type="button" class="b-ghost" id="scanStop" hidden>Stop camera</button>
          <button type="button" class="b-ghost" id="scanFlip" hidden>Switch camera</button>
        </div>
        <section class="stats stats--3 scan__stats">
          <div class="stat stat--paid"><span>Checked in</span><b id="cntIn"><?= $S['attended'] ?></b></div>
          <div class="stat"><span>Paid, not arrived</span><b id="cntWait"><?= max(0, $S['paid'] - $S['attended']) ?></b></div>
          <div class="stat stat--unpaid"><span>Unpaid</span><b><?= $S['unpaid'] + $S['to_verify'] ?></b></div>
        </section>
        <h2 class="scan__h">Recent check-ins</h2>
        <ul class="scan__log" id="scanLog">
          <?php foreach ($recent as $x): ?>
            <li class="ok"><b><?= e($x['name']) ?></b><code><?= e($x['reg_code']) ?></code><small><?= $x['checked_in_at'] ? e(date('h:i A', strtotime($x['checked_in_at']))) : '' ?></small></li>
          <?php endforeach; ?>
          <?php if (!$recent): ?><li class="empty-li">No one checked in yet.</li><?php endif; ?>
        </ul>
      </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js" integrity="sha384-b5Ya4Bq3qCyz39m2ISh+4DxjAIljdeFwK/BsXLuj9gugaNwAcj/ia15fxNZL9Nlx" crossorigin="anonymous" defer></script>
    <script src="checkin.js?v=1" defer></script>
<?php endif; ?>
  </main>
</div>
</body>
</html>
