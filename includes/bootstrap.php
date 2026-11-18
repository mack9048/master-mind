<?php
declare(strict_types=1);

/*
 * Config: config.php on normal hosting (Hostinger / cPanel).
 * On Vercel (no config.php) the values come from Environment Variables, e.g.
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, UPI_ID, UPI_NAME, ADMIN_PASS, APP_SECRET, SEATS.
 */
$configFile = dirname(__DIR__) . '/config.php';
$CONFIG = require (is_file($configFile) ? $configFile : dirname(__DIR__) . '/config.sample.php');

$envMap = [
    'DB_HOST' => ['db', 'host'], 'DB_PORT' => ['db', 'port'], 'DB_NAME' => ['db', 'name'],
    'DB_USER' => ['db', 'user'], 'DB_PASS' => ['db', 'pass'],
    'UPI_ID' => ['payment', 'upi_id'], 'UPI_NAME' => ['payment', 'upi_name'],
    'ADMIN_USER' => ['admin', 'user'], 'ADMIN_PASS' => ['admin', 'pass'],
    'SEATS' => ['event', 'seats'], 'APP_SECRET' => ['app_secret', null],
];
foreach ($envMap as $env => [$a, $b]) {
    $v = getenv($env);
    if ($v === false || $v === '') {
        continue;
    }
    if ($b === null) {
        $CONFIG[$a] = $v;
    } else {
        $CONFIG[$a][$b] = in_array($env, ['DB_PORT', 'SEATS'], true) ? (int) $v : $v;
    }
}
if (!is_file($configFile) && getenv('DB_HOST') === false) {
    http_response_code(500);
    exit('Missing configuration – create config.php (see config.sample.php) or set the DB_* environment variables.');
}

if (!empty($CONFIG['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

date_default_timezone_set('Asia/Kolkata');

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/**
 * Secret used to sign tokens/cookies. Works without PHP sessions, so the site
 * also runs on serverless hosting (Vercel) where every request may hit a new server.
 */
function app_secret(): string
{
    $s = (string) cfg('app_secret', '');
    if ($s === '') {
        // Fallback: derive from the DB credentials (stable, private)
        $s = hash('sha256', 'mastermind|' . cfg('db.pass') . '|' . cfg('db.user') . '|' . cfg('db.name'));
    }
    return $s;
}

function sign(string $data): string
{
    return hash_hmac('sha256', $data, app_secret());
}

function set_cookie(string $name, string $value, int $ttl, bool $httpOnly = true): void
{
    setcookie($name, $value, [
        'expires'  => $ttl ? time() + $ttl : 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => $httpOnly,
        'samesite' => 'Lax',
    ]);
}

function cfg(string $path, $default = null)
{
    global $CONFIG;
    $node = $CONFIG;
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $default;
        }
        $node = $node[$key];
    }
    return $node;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $d   = cfg('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $d['host'], $d['port'] ?? 3306, $d['name'], $d['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $d['user'], $d['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+05:30'");
    }
    return $pdo;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* CSRF: double-submit cookie (no server session needed) */
function csrf_token(): string
{
    static $token = null;
    if ($token !== null) {
        return $token;
    }
    $c = $_COOKIE['mm_csrf'] ?? '';
    if (is_string($c) && preg_match('/^[a-f0-9]{64}$/', $c)) {
        return $token = $c;
    }
    $token = bin2hex(random_bytes(32));
    set_cookie('mm_csrf', $token, 0);
    return $token;
}

function csrf_check(?string $token): bool
{
    $c = $_COOKIE['mm_csrf'] ?? '';
    return is_string($token) && is_string($c) && $c !== '' && hash_equals($c, $token);
}

/* Signed proof that this browser created registration $regCode */
function reg_token(string $regCode): string
{
    return sign('reg|' . $regCode);
}

function reg_token_ok(string $regCode, ?string $token): bool
{
    return is_string($token) && $regCode !== '' && hash_equals(reg_token($regCode), $token);
}

/* Admin login cookie: "<expiry>.<signature>" */
function admin_login(): void
{
    $exp = time() + 12 * 3600;
    set_cookie('mm_admin', $exp . '.' . sign('admin|' . $exp . '|' . cfg('admin.user') . '|' . cfg('admin.pass') . cfg('admin.pass_hash')), 12 * 3600);
}

function admin_logout(): void
{
    set_cookie('mm_admin', '', -3600);
}

function is_admin(): bool
{
    $c = (string) ($_COOKIE['mm_admin'] ?? '');
    if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', $c, $m) || (int) $m[1] < time()) {
        return false;
    }
    return hash_equals(sign('admin|' . $m[1] . '|' . cfg('admin.user') . '|' . cfg('admin.pass') . cfg('admin.pass_hash')), $m[2]);
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Seats already taken (paid + pending registrations count against capacity). */
function seats_taken(): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM registrations WHERE event_slug = ? AND payment_status IN ('paid','pending')");
    $stmt->execute([cfg('event.slug')]);
    return (int) $stmt->fetchColumn();
}

/** Remaining seats, or null when capacity is unlimited / DB unreachable. */
function seats_left(): ?int
{
    $total = (int) cfg('event.seats', 0);
    if ($total <= 0) {
        return null;
    }
    try {
        return max(0, $total - seats_taken());
    } catch (Throwable $e) {
        return null;
    }
}

function registration_open(): bool
{
    return time() < strtotime(cfg('event.starts_at'));
}

function new_reg_code(): string
{
    // e.g. MM26-7K4Q9X  (no 0/O/1/I to avoid confusion)
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'MM' . date('y') . '-' . $code;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}
