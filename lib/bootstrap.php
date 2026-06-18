<?php
$config = require __DIR__ . '/../config.php';

date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    if (!empty($config['security']['session_name'])) {
        session_name($config['security']['session_name']);
    }
    session_start();
}

function app_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config.php';
    }
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = app_config()['database'];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4');

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function json_response(bool $ok, string $message, array $extra = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_text($value, int $max = 500): string
{
    $value = trim((string)$value);
    $value = strip_tags($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return mb_substr($value, 0, $max);
}

function money($value): string
{
    return 'Rp' . number_format((float)$value, 0, ',', '.');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function current_admin(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, created_at FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

function app_base_path(): string
{
    $base = rtrim(app_config()['app']['base_url'] ?? '', '/');
    if ($base !== '') {
        return $base;
    }

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
        return '';
    }
    return rtrim($scriptDir, '/');
}

function app_url(string $path = ''): string
{
    $base = app_base_path();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base !== '' ? $base . '/' : '/';
    }

    return ($base !== '' ? $base : '') . '/' . $path;
}

function app_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function public_invoice_signature(array $order, ?int $expiresAt = null): string
{
    $secret = (string)(app_config()['security']['install_key'] ?? 'nusatechcare');
    $parts = [
        (string)($order['id'] ?? ''),
        (string)($order['ticket_code'] ?? ''),
        (string)($order['created_at'] ?? ''),
    ];

    // Backward compatible:
    // - expiresAt null = signature lama tetap valid.
    // - expiresAt berisi timestamp = link punya masa berlaku.
    if ($expiresAt !== null) {
        $parts[] = (string)$expiresAt;
    }

    return hash_hmac('sha256', implode('|', $parts), $secret);
}

function public_invoice_url(array $order, bool $absolute = true, int $validDays = 30): string
{
    $validDays = max(1, $validDays);
    $expiresAt = time() + ($validDays * 86400);
    $code = (string)($order['ticket_code'] ?? ($order['id'] ?? '0'));

    $path = app_url('invoice_public.php?code=' . urlencode($code) . '&exp=' . $expiresAt . '&sig=' . public_invoice_signature($order, $expiresAt));
    return $absolute ? app_origin() . $path : $path;
}

