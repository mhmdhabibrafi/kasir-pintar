<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv($name . '=' . $value);
            }
        }
    }
}

$rootEnv = dirname(__DIR__, 2) . '/.env';
load_env_file($rootEnv);

const BASE_URL = '';
const BASE_PATH = '/kaspindo';
const SESSION_IDLE_TIMEOUT = 1800;
const SESSION_ABSOLUTE_TIMEOUT = 28800;
const LOGIN_RATE_LIMIT_MAX_ATTEMPTS = 5;
const LOGIN_RATE_LIMIT_WINDOW = 60;
const LOGIN_RATE_LIMIT_LOCK = 30;

define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
define('TELEGRAM_CHAT_ID', getenv('TELEGRAM_CHAT_ID') ?: '');

function app_env_bool(string $name, bool $default = false): bool
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function app_request_is_https(): bool
{
    if (app_env_bool('APP_FORCE_HTTPS', false)) {
        return true;
    }

    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto !== '') {
        $firstProto = trim(explode(',', $forwardedProto)[0]);
        if ($firstProto === 'https') {
            return true;
        }
    }

    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
        return true;
    }

    if (strtolower((string) ($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '')) === 'on') {
        return true;
    }

    $cfVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
    if ($cfVisitor !== '') {
        $decoded = json_decode($cfVisitor, true);
        if (is_array($decoded) && strtolower((string) ($decoded['scheme'] ?? '')) === 'https') {
            return true;
        }
    }

    return false;
}

function app_request_scheme(): string
{
    return app_request_is_https() ? 'https' : 'http';
}

function app_base_path_from_request(): string
{
    $configured = trim((string) (getenv('APP_BASE_PATH') ?: ''));
    if ($configured !== '') {
        return '/' . trim($configured, '/');
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($basePath === '.' || $basePath === '/') {
        return '';
    }

    return $basePath;
}

function base_url(string $path = ''): string
{
    $configuredUrl = trim((string) (getenv('APP_URL') ?: ''));
    $baseUrl = rtrim($configuredUrl !== '' ? $configuredUrl : BASE_URL, '/');
    if ($baseUrl !== '') {
        if ($path === '') {
            return $baseUrl;
        }
        return $baseUrl . '/' . ltrim($path, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        $base = app_request_scheme() . '://' . $host . app_base_path_from_request();
        if ($path === '') {
            return $base;
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    $basePath = rtrim(BASE_PATH, '/');
    if ($path === '') {
        return $basePath === '' ? '/' : $basePath;
    }

    return $basePath . '/' . ltrim($path, '/');
}

function send_security_headers(bool $allowInlineAssets = true): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    if (app_request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if (PHP_SAPI !== 'cli') {
        $csp = $allowInlineAssets
            ? "default-src 'self'; base-uri 'self'; frame-ancestors 'self'; img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com https://fonts.googleapis.com data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://unpkg.com https://cdn.jsdelivr.net; connect-src 'self'; manifest-src 'self';"
            : "default-src 'self'; base-uri 'self'; frame-ancestors 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self';";
        header('Content-Security-Policy: ' . $csp);
    }
}
