<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/cash_report_helper.php';

require_role(['admin', 'bos']);

$today = date('Y-m-d');
$normalizeDate = static function (?string $value, string $fallback): string {
    if ($value === null || $value === '') {
        return $fallback;
    }
    $parsed = DateTime::createFromFormat('Y-m-d', $value);
    if (!$parsed || $parsed->format('Y-m-d') !== $value) {
        return $fallback;
    }
    return $value;
};
$filters = [
    'start_date' => $normalizeDate($_GET['start_date'] ?? null, $today),
    'end_date' => $normalizeDate($_GET['end_date'] ?? null, $today),
];
if ($filters['start_date'] > $filters['end_date']) {
    [$filters['start_date'], $filters['end_date']] = [$filters['end_date'], $filters['start_date']];
}

$dailyRecap = cash_report_daily($filters['start_date'], $filters['end_date']);
$dailyTotals = cash_report_daily_totals($dailyRecap);
$shiftData = cash_report_shift_rows($filters['start_date'], $filters['end_date']);
$shiftRows = $shiftData['rows'] ?? [];
$shiftTotals = $shiftData['totals'] ?? [
    'opening_cash' => 0.0,
    'cash_in' => 0.0,
    'cash_out' => 0.0,
    'sales_cash' => 0.0,
    'refund_cash' => 0.0,
    'sales_qris' => 0.0,
    'expected_cash' => 0.0,
    'closing_cash' => 0.0,
];

$title = 'Kas Harian';
require_once __DIR__ . '/../app/views/admin/cash_report.php';
