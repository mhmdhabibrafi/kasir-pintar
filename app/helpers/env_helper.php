<?php

declare(strict_types=1);

function env_file_path(): string
{
    return dirname(__DIR__, 2) . '/.env';
}

function env_encode_value(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (!preg_match('/[\s#"\']/', $value)) {
        return $value;
    }

    $escaped = str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\"', '\r', '\n'], $value);
    return '"' . $escaped . '"';
}

function env_update_many(array $changes, ?string $path = null): void
{
    $path = $path ?? env_file_path();
    $lines = is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];
    $found = [];

    foreach ($lines as $index => $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '=') === false) {
            continue;
        }

        [$name] = explode('=', $trimmed, 2);
        $name = trim((string) $name);
        if ($name === '' || !array_key_exists($name, $changes)) {
            continue;
        }

        $lines[$index] = $name . '=' . env_encode_value((string) $changes[$name]);
        $found[$name] = true;
    }

    foreach ($changes as $name => $value) {
        if (!isset($found[$name])) {
            $lines[] = $name . '=' . env_encode_value((string) $value);
        }

        $_ENV[$name] = (string) $value;
        putenv($name . '=' . (string) $value);
    }

    $payload = implode(PHP_EOL, $lines);
    if ($payload !== '') {
        $payload .= PHP_EOL;
    }

    $written = file_put_contents($path, $payload, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('Gagal menyimpan file .env.');
    }
}

function env_mask_secret(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '-';
    }

    $length = strlen($value);
    if ($length <= 8) {
        return str_repeat('*', $length);
    }

    return substr($value, 0, 4) . str_repeat('*', max(4, $length - 8)) . substr($value, -4);
}
