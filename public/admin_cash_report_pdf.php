<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/cash_report_helper.php';
require_once __DIR__ . '/../app/helpers/report_pdf_helper.php';

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
$shiftTotals = $shiftData['totals'] ?? [];

$user = current_user();
$filters['printed_at'] = date('d/m/Y H:i');
$filters['printed_by'] = $user['name'] ?? '';
$filters['printed_role'] = $user['role'] ?? '';

$pdf = build_cash_report_pdf($dailyRecap, $dailyTotals, $shiftRows, $shiftTotals, $filters);
$filename = sprintf('kas_harian_%s_%s.pdf', $filters['start_date'], $filters['end_date']);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf));

echo $pdf;
