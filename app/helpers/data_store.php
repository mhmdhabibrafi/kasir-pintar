<?php

declare(strict_types=1);

function data_store_path(string $filename): string
{
    $dir = __DIR__ . '/../../storage/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir . '/' . $filename;
}

function data_store_read(string $filename, array $default = []): array
{
    $path = data_store_path($filename);
    if (!is_file($path)) {
        return $default;
    }
    $content = file_get_contents($path);
    if ($content === false || $content === '') {
        return $default;
    }
    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

function data_store_write(string $filename, array $data): void
{
    $path = data_store_path($filename);
    $tmp = $path . '.tmp';
    $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($payload === false) {
        $payload = json_encode(['error' => 'encode_failed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    file_put_contents($tmp, $payload, LOCK_EX);
    rename($tmp, $path);
}

function data_store_update(string $filename, callable $updater, array $default = []): array
{
    $data = data_store_read($filename, $default);
    $updated = $updater($data);
    if (!is_array($updated)) {
        $updated = $data;
    }
    data_store_write($filename, $updated);
    return $updated;
}

