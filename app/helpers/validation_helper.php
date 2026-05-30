<?php

declare(strict_types=1);

function normalize_username(string $value): string
{
    return trim($value);
}

function is_valid_username(string $value): bool
{
    $normalized = normalize_username($value);
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/', $normalized) === 1;
}

function normalize_phone(string $value): string
{
    $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return $normalized;
}

function is_valid_phone(string $value): bool
{
    $normalized = normalize_phone($value);
    if ($normalized === '') {
        return false;
    }

    if (preg_match('/^[0-9+\-\s()]{8,25}$/', $normalized) !== 1) {
        return false;
    }

    $digits = preg_replace('/\D+/', '', $normalized) ?? '';
    $length = strlen($digits);

    return $length >= 8 && $length <= 15;
}
