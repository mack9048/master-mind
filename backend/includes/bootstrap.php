<?php
declare(strict_types=1);

/*
 * API requests always answer in JSON with the CORS header, even on errors,
 * so the website can show the real problem instead of "could not reach the server".
 * (The origin allow-list is still enforced in api_begin() before anything is saved.)
 */
$IS_API = str_contains(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/api/');
if ($IS_API) {
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Vary: Origin');
    }
    $apiFail = static function (string $msg, int $code = 500): void {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };
    set_exception_handler(static function (Throwable $e) use ($apiFail): void {
        error_log('[mastermind] ' . $e->getMessage());
        $apiFail($e instanceof PDOException
            ? 'Booking server: database connection failed. Please try again later.'
            : 'Booking server error. Please try again later.');
    });
    register_shutdown_function(static function () use ($apiFail): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && !headers_sent()) {
            error_log('[mastermind] fatal: ' . $err['message']);
            $apiFail('Booking server error. Please try again later.');
        }
    });
}

/*
 * Config: config.php on normal hosting (Hostinger / cPanel).
 * On Vercel (no config.php) the values come from Environment Variables, e.g.
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, UPI_ID, UPI_NAME, ADMIN_PASS, APP_SECRET, SEATS.
 */
/*
 * Optional backend/.env file (KEY=VALUE per line) – used for secrets such as
 * RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET. Real environment variables win.
 * The file is blocked from the web by .htaccess and ignored by Git.
 */
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
        }
    }
}

$configFile = dirname(__DIR__) . '/config.php';
$CONFIG = require (is_file($configFile) ? $configFile : dirname(__DIR__) . '/config.sample.php');

$envMap = [
    'DB_HOST' => ['db', 'host'], 'DB_PORT' => ['db', 'port'], 'DB_NAME' => ['db', 'name'],
    'DB_USER' => ['db', 'user'], 'DB_PASS' => ['db', 'pass'],
    'UPI_ID' => ['payment', 'upi_id'], 'UPI_NAME' => ['payment', 'upi_name'],
    'ADMIN_USER' => ['admin', 'user'], 'ADMIN_PASS' => ['admin', 'pass'],
    'SEATS' => ['event', 'seats'], 'APP_SECRET' => ['app_secret', null],
    'FRONTEND_URL' => ['frontend_url', null], 'ALLOWED_ORIGINS' => ['allowed_origins', null],
    'RAZORPAY_KEY_ID' => ['razorpay', 'key_id'], 'RAZORPAY_KEY_SECRET' => ['razorpay', 'key_secret'],
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
    $m = 'Missing configuration – create config.php (see config.sample.php) or set the DB_* environment variables.';
    if ($IS_API) {
        $apiFail('Booking server is not configured yet (config.php missing).');
        exit;
    }
    http_response_code(500);
    exit($m);
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

/** Turn a database error into a short, safe explanation (no passwords or SQL shown). */
function db_problem(Throwable $e): string
{
    $m = $e->getMessage();
    if (str_contains($m, "doesn't exist") || str_contains($m, '1146')) {
        return 'the registrations table is missing – run database.sql in phpMyAdmin';
    }
    if (str_contains($m, 'Unknown column') || str_contains($m, '1054')) {
        preg_match("/Unknown column '([^']+)'/", $m, $c);
        return 'the registrations table is outdated' . (!empty($c[1]) ? " (missing column {$c[1]})" : '') . ' – drop it and run database.sql again';
    }
    if (str_contains($m, 'Access denied') || str_contains($m, '1045') || str_contains($m, '1044')) {
        return 'database login failed – check db name / user / password in config.php';
    }
    if (str_contains($m, '2002') || str_contains($m, 'Connection refused') || str_contains($m, 'getaddrinfo')) {
        return 'database server not reachable – db host should be localhost';
    }
    if (str_contains($m, 'Data too long') || str_contains($m, 'Incorrect') || str_contains($m, 'truncated')) {
        return 'a value did not fit the table – run the latest database.sql';
    }
    return 'database error (' . ($e instanceof PDOException ? ($e->errorInfo[1] ?? $e->getCode()) : get_class($e)) . ')';
}

/**
 * Several people may book with the same phone / e-mail (e.g. a parent booking for two kids).
 * Older tables had UNIQUE(event_slug, phone) – replace it with a normal index, once.
 */
function allow_multi_bookings(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $idx = $pdo->query("SHOW INDEX FROM registrations WHERE Key_name = 'uq_event_phone'")->fetch();
    if ($idx) {
        $pdo->exec('ALTER TABLE registrations DROP INDEX uq_event_phone, ADD INDEX idx_event_phone (event_slug, phone)');
    }
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

function base_url(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/', 2)), '/');
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}

/** Origins (React frontend) allowed to call the API. */
function allowed_origins(): array
{
    $list = cfg('allowed_origins', []);
    if (is_string($list)) {
        $list = explode(',', $list);
    }
    $list[] = (string) cfg('frontend_url', '');
    return array_values(array_filter(array_map(fn ($o) => rtrim(trim((string) $o), '/'), $list)));
}

/**
 * Start a JSON API request: CORS headers, preflight, method + origin checks.
 * Browsers always send Origin on cross-site POSTs, so rejecting unknown origins
 * stops other websites from submitting forms to this API.
 */
function api_begin(string $method): void
{
    $origin  = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    $allowed = allowed_origins();
    $ok      = $origin === '' || in_array($origin, $allowed, true) || in_array('*', $allowed, true);

    if ($origin !== '' && $ok) {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code($ok ? 204 : 403);
        exit;
    }
    if (!$ok) {
        json_out(['ok' => false, 'error' => 'This website (' . $origin . ') is not allowed to use the booking server. Add it to allowed_origins in config.php.'], 403);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
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
