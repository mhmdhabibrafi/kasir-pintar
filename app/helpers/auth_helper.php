<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $secure = function_exists('app_request_is_https')
            ? app_request_is_https()
            : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $params = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }

    if (!empty($_SESSION['user'])) {
        $now = time();
        $createdAt = (int) ($_SESSION['created_at'] ?? $now);
        $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);
        $idleTimeout = defined('SESSION_IDLE_TIMEOUT') ? (int) SESSION_IDLE_TIMEOUT : 1800;
        $absoluteTimeout = defined('SESSION_ABSOLUTE_TIMEOUT') ? (int) SESSION_ABSOLUTE_TIMEOUT : 28800;

        if (($now - $createdAt) > $absoluteTimeout || ($now - $lastActivity) > $idleTimeout) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            return;
        }

        $_SESSION['created_at'] = $createdAt;
        $_SESSION['last_activity'] = $now;
    }
}

function login_user(array $user): void
{
    start_session();
    session_regenerate_id(true);
    $now = time();
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'role' => $user['role'],
        'store_id' => isset($user['store_id']) && (int) $user['store_id'] > 0 ? (int) $user['store_id'] : null,
    ];
    if (isset($user['permissions']) && is_array($user['permissions'])) {
        $_SESSION['user']['permissions'] = $user['permissions'];
    } elseif (array_key_exists('permissions_json', $user)) {
        $_SESSION['user']['permissions_json'] = $user['permissions_json'];
    }
    $_SESSION['created_at'] = $now;
    $_SESSION['last_activity'] = $now;
}

function current_user(): ?array
{
    start_session();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function role_redirect(string $role): string
{
    switch ($role) {
        case 'superadmin':
            return base_url('superadmin_store_requests.php?focus=overview');
        case 'admin':
            return base_url('admin.php');
        case 'bos':
            return base_url('bos.php');
        case 'karyawan':
            return base_url('kasir.php');
        default:
            return base_url('login.php');
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate(?string $token): bool
{
    start_session();
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals((string) $_SESSION['csrf_token'], (string) $token);
}

function log_event(string $filename, string $message, array $context = []): void
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $payload = array_merge(
        [
            'time' => date('Y-m-d H:i:s'),
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ],
        $context
    );
    $line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents($dir . '/' . $filename, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function audit_log(string $message, array $context = []): void
{
    $user = current_user();
    if ($user) {
        $context = array_merge(
            [
                'user_id' => $user['id'] ?? null,
                'role' => $user['role'] ?? null,
                'username' => $user['username'] ?? null,
            ],
            $context
        );
    }
    log_event('audit.log', $message, $context);
    $telegramHelperPath = __DIR__ . '/telegram_helper.php';
    if (!function_exists('telegram_notify_audit') && is_file($telegramHelperPath)) {
        require_once $telegramHelperPath;
    }
    if (function_exists('telegram_notify_audit')) {
        telegram_notify_audit($message, $context);
    }
}

function security_log(string $message, array $context = []): void
{
    log_event('security.log', $message, $context);
}

function login_rate_limit_key(string $username = ''): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 120);
    $name = strtolower(trim($username));
    return hash('sha256', $ip . '|' . $ua . '|' . $name);
}

function login_rate_limit_check(string $key): array
{
    $maxAttempts = defined('LOGIN_RATE_LIMIT_MAX_ATTEMPTS') ? (int) LOGIN_RATE_LIMIT_MAX_ATTEMPTS : 5;
    $window = defined('LOGIN_RATE_LIMIT_WINDOW') ? (int) LOGIN_RATE_LIMIT_WINDOW : 60;
    $lockSeconds = defined('LOGIN_RATE_LIMIT_LOCK') ? (int) LOGIN_RATE_LIMIT_LOCK : 30;
    $store = login_rate_limit_store_read();
    $now = time();
    $entry = $store[$key] ?? null;

    if ($entry && ($entry['lock_until'] ?? 0) > $now) {
        return ['allowed' => false, 'retry_after' => (int) ($entry['lock_until'] - $now)];
    }

    if ($entry && ($now - ($entry['first'] ?? $now)) > $window) {
        unset($store[$key]);
        login_rate_limit_store_write($store);
        return ['allowed' => true, 'retry_after' => 0];
    }

    $count = (int) ($entry['count'] ?? 0);
    if ($count >= $maxAttempts) {
        $store[$key]['lock_until'] = $now + $lockSeconds;
        login_rate_limit_store_write($store);
        return ['allowed' => false, 'retry_after' => $lockSeconds];
    }

    return ['allowed' => true, 'retry_after' => 0];
}

function login_rate_limit_record(string $key, bool $success): void
{
    $maxAttempts = defined('LOGIN_RATE_LIMIT_MAX_ATTEMPTS') ? (int) LOGIN_RATE_LIMIT_MAX_ATTEMPTS : 5;
    $window = defined('LOGIN_RATE_LIMIT_WINDOW') ? (int) LOGIN_RATE_LIMIT_WINDOW : 60;
    $lockSeconds = defined('LOGIN_RATE_LIMIT_LOCK') ? (int) LOGIN_RATE_LIMIT_LOCK : 30;
    $store = login_rate_limit_store_read();
    $now = time();

    if ($success) {
        unset($store[$key]);
        login_rate_limit_store_write($store);
        return;
    }

    $entry = $store[$key] ?? ['count' => 0, 'first' => $now, 'lock_until' => 0];
    if (($now - ($entry['first'] ?? $now)) > $window) {
        $entry = ['count' => 0, 'first' => $now, 'lock_until' => 0];
    }
    $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
    if ($entry['count'] >= $maxAttempts) {
        $entry['lock_until'] = $now + $lockSeconds;
    }
    $store[$key] = $entry;
    login_rate_limit_store_write($store);
}

function login_rate_limit_store_read(): array
{
    $path = __DIR__ . '/../../storage/cache/login_rate.json';
    if (!is_file($path)) {
        return [];
    }
    $content = file_get_contents($path);
    $data = json_decode($content ?: '', true);
    return is_array($data) ? $data : [];
}

function login_rate_limit_store_write(array $store): void
{
    $dir = __DIR__ . '/../../storage/cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir . '/login_rate.json';
    file_put_contents($path, json_encode($store, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
