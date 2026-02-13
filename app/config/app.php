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

function base_url(string $path = ''): string
{
    $baseUrl = rtrim(BASE_URL, '/');
    if ($baseUrl !== '') {
        if ($path === '') {
            return $baseUrl;
        }
        return $baseUrl . '/' . ltrim($path, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        }
        $base = $scheme . '://' . $host . $basePath;
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
