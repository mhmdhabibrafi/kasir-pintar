<?php

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_rupiah(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
