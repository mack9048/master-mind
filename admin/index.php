<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$slug = cfg('event.slug');

/* ---------- Auth ---------- */
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header('Location: ./');
    exit;
}

$loginError = '';
if (empty($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $_SESSION['login_tries'] = ($_SESSION['login_tries'] ?? 0) + 1;
        if ($_SESSION['login_tries'] > 8) {
            sleep(2);
        }
        $hash  = (string) cfg('admin.pass_hash', '');
        $plain = (string) cfg('admin.pass', '');
        $given = (string) ($_POST['pass'] ?? '');
        $passOk = $hash !== '' ? password_verify($given, $hash) : ($plain !== '' && hash_equals($plain, $given));
        if (csrf_check($_POST['csrf'] ?? null)
            && ($hash !== '' || $plain !== '')
            && hash_equals((string) cfg('admin.user'), (string) ($_POST['user'] ?? ''))
            && $passOk) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['login_tries'] = 0;
            header('Location: ./');
            exit;
        }
        $loginError = ($hash === '' && $plain === '') ? 'Admin password is not set in config.php.' : 'Wrong username or password.';
    }
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin · Master Mind</title><meta name="robots" content="noindex"><link rel="stylesheet" href="admin.css"></head>
<body class="login">
<form method="post" class="login__box">
  <img src="../assets/img/mastermind-logo.png" alt="Master Mind" width="160">
  <h1>Registrations</h1>
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

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['do'] ?? '') {
        case 'paid':
        case 'pending':
        case 'cancelled':
            $pdo->prepare('UPDATE registrations SET payment_status = ? WHERE id = ? AND event_slug = ?')->execute([$_POST['do'], $id, $slug]);
            break;
        case 'attended':
            $pdo->prepare('UPDATE registrations SET attended = 1 - attended WHERE id = ? AND event_slug = ?')->execute([$id, $slug]);
            break;
    }
    header('Location: ./?' . http_build_query(array_intersect_key($_GET, ['status' => 1, 'q' => 1])));
    exit;
}

/* ---------- Filters ---------- */
$status = $_GET['status'] ?? '';
$q      = trim((string) ($_GET['q'] ?? ''));
$where  = ['event_slug = ?'];
$args   = [$slug];
if (in_array($status, ['paid', 'pending', 'failed', 'cancelled'], true)) {
    $where[] = 'payment_status = ?';
    $args[]  = $status;
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR reg_code LIKE ? OR payment_ref LIKE ?)';
    array_push($args, ...array_fill(0, 5, '%' . $q . '%'));
}
$sql  = 'SELECT * FROM registrations WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

/* ---------- CSV export ---------- */
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mastermind-' . $slug . '-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, ['Reg ID', 'Name', 'Phone', 'Email', 'City', 'Profession', 'College/Company', 'Expectation', 'Amount', 'Mode', 'Status', 'UTR/Ref', 'Razorpay Payment', 'Attended', 'Registered At']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['reg_code'], $r['name'], $r['phone'], $r['email'], $r['city'], $r['profession'], $r['organization'], $r['expectation'], $r['amount'], $r['payment_mode'], $r['payment_status'], $r['payment_ref'], $r['razorpay_payment_id'], $r['attended'] ? 'Yes' : 'No', $r['created_at']]);
    }
    exit;
}

/* ---------- Stats ---------- */
$st = $pdo->prepare("SELECT payment_status, COUNT(*) c, SUM(amount) amt FROM registrations WHERE event_slug = ? GROUP BY payment_status");
$st->execute([$slug]);
$stats = ['paid' => ['c' => 0, 'amt' => 0], 'pending' => ['c' => 0, 'amt' => 0], 'failed' => ['c' => 0, 'amt' => 0], 'cancelled' => ['c' => 0, 'amt' => 0]];
foreach ($st as $s) {
    $stats[$s['payment_status']] = ['c' => (int) $s['c'], 'amt' => (float) $s['amt']];
}
$seats = (int) cfg('event.seats');
$csrf  = csrf_token();
$qs    = fn (array $extra = []) => '?' . http_build_query(array_filter(array_merge(['status' => $status, 'q' => $q], $extra), fn ($v) => $v !== '' && $v !== null));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registrations · Master Mind</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="top">
  <div><img src="../assets/img/mastermind-logo.png" alt="" width="110"><span><?= e(cfg('event.title')) ?> · <?= e(cfg('event.date_label')) ?></span></div>
  <nav><a href="../" target="_blank">View site</a><a href="?logout=1">Log out</a></nav>
</header>

<main class="main">
  <section class="stats">
    <div class="stat stat--paid"><span>Paid</span><b><?= $stats['paid']['c'] ?></b><small>₹<?= number_format($stats['paid']['amt']) ?> collected</small></div>
    <div class="stat stat--pending"><span>Pending</span><b><?= $stats['pending']['c'] ?></b><small>awaiting payment / verification</small></div>
    <div class="stat"><span>Failed / Cancelled</span><b><?= $stats['failed']['c'] + $stats['cancelled']['c'] ?></b><small>not holding a seat</small></div>
    <div class="stat"><span>Seats left</span><b><?= $seats > 0 ? max(0, $seats - $stats['paid']['c'] - $stats['pending']['c']) : '∞' ?></b><small><?= $seats > 0 ? 'of ' . $seats : 'unlimited' ?></small></div>
  </section>

  <form class="filters" method="get">
    <div class="tabs">
      <?php foreach (['' => 'All', 'paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $k => $label): ?>
        <a class="<?= $status === $k ? 'on' : '' ?>" href="<?= e('?' . http_build_query(array_filter(['status' => $k, 'q' => $q]))) ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, phone, email, ID, UTR">
    <button>Search</button>
    <a class="btn-csv" href="<?= e($qs(['export' => 1])) ?>">Export CSV</a>
  </form>

  <div class="table">
    <table>
      <thead><tr><th>Reg ID</th><th>Name</th><th>Contact</th><th>Profile</th><th>Payment</th><th>Registered</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="empty">No registrations yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= e($r['reg_code']) ?></code><?php if ($r['attended']): ?><br><span class="pill pill--att">attended</span><?php endif; ?></td>
          <td><strong><?= e($r['name']) ?></strong><?php if ($r['expectation']): ?><br><small title="<?= e($r['expectation']) ?>">“<?= e(mb_strimwidth($r['expectation'], 0, 60, '…')) ?>”</small><?php endif; ?></td>
          <td><a href="https://wa.me/<?= e(ltrim($r['phone'], '+')) ?>" target="_blank"><?= e($r['phone']) ?></a><br><small><?= e($r['email']) ?></small></td>
          <td><?= e($r['profession']) ?><br><small><?= e(trim($r['city'] . ($r['organization'] ? ' · ' . $r['organization'] : ''))) ?></small></td>
          <td><span class="pill pill--<?= e($r['payment_status']) ?>"><?= e($r['payment_status']) ?></span> <small>₹<?= (int) $r['amount'] ?> · <?= e($r['payment_mode']) ?></small>
            <?php if ($r['payment_ref']): ?><br><small>UTR: <code><?= e($r['payment_ref']) ?></code></small><?php endif; ?>
            <?php if ($r['razorpay_payment_id']): ?><br><small><code><?= e($r['razorpay_payment_id']) ?></code></small><?php endif; ?></td>
          <td><small><?= e(date('d M, h:i A', strtotime($r['created_at']))) ?></small></td>
          <td class="acts">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <?php if ($r['payment_status'] !== 'paid'): ?><button name="do" value="paid" class="a-paid">Mark paid</button><?php endif; ?>
              <?php if ($r['payment_status'] === 'paid'): ?><button name="do" value="pending">Mark pending</button><?php endif; ?>
              <?php if ($r['payment_status'] !== 'cancelled'): ?><button name="do" value="cancelled" onclick="return confirm('Cancel this registration?')">Cancel</button><?php endif; ?>
              <button name="do" value="attended"><?= $r['attended'] ? 'Undo attend' : 'Attended' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
