<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config.php – copy config.sample.php to config.php and fill in your database details.');
}
$CONFIG = require $configFile;

if (!empty($CONFIG['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
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

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
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
