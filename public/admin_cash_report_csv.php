<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
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
$shiftTotals = $shiftData['totals'] ?? [];

$filename = sprintf('kas_harian_%s_%s.csv', $filters['start_date'], $filters['end_date']);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$out = fopen('php://output', 'w');

fputcsv($out, ['Rekap Kas Harian']);
fputcsv($out, ['Tanggal', 'Cash In', 'Cash Out', 'Sales Cash', 'Refund Cash', 'Net Cash', 'Sales QRIS', 'Refund QRIS', 'Net QRIS']);
foreach ($dailyRecap as $row) {
    $cashIn = (float) ($row['cash_in'] ?? 0);
    $cashOut = (float) ($row['cash_out'] ?? 0);
    $cashSales = (float) ($row['cash_sales'] ?? 0);
    $refundCash = (float) ($row['refund_cash'] ?? 0);
    $qrisSales = (float) ($row['qris_sales'] ?? 0);
    $refundQris = (float) ($row['refund_qris'] ?? 0);
    $netCash = $cashSales - $refundCash + $cashIn - $cashOut;
    $netQris = $qrisSales - $refundQris;
    fputcsv($out, [
        $row['date'] ?? '',
        $cashIn,
        $cashOut,
        $cashSales,
        $refundCash,
        $netCash,
        $qrisSales,
        $refundQris,
        $netQris,
    ]);
}
$netCashTotal = (float) ($dailyTotals['cash_sales'] ?? 0)
    - (float) ($dailyTotals['refund_cash'] ?? 0)
    + (float) ($dailyTotals['cash_in'] ?? 0)
    - (float) ($dailyTotals['cash_out'] ?? 0);
$netQrisTotal = (float) ($dailyTotals['qris_sales'] ?? 0) - (float) ($dailyTotals['refund_qris'] ?? 0);
fputcsv($out, [
    'Total',
    (float) ($dailyTotals['cash_in'] ?? 0),
    (float) ($dailyTotals['cash_out'] ?? 0),
    (float) ($dailyTotals['cash_sales'] ?? 0),
    (float) ($dailyTotals['refund_cash'] ?? 0),
    $netCashTotal,
    (float) ($dailyTotals['qris_sales'] ?? 0),
    (float) ($dailyTotals['refund_qris'] ?? 0),
    $netQrisTotal,
]);

fputcsv($out, []);
fputcsv($out, ['Detail Kas per Shift']);
fputcsv($out, ['Shift', 'Kasir', 'Mulai', 'Selesai', 'Kas Awal', 'Cash In', 'Cash Out', 'Sales Cash', 'Refund Cash', 'Expected Cash', 'Kas Akhir', 'Selisih', 'Sales QRIS']);
foreach ($shiftRows as $row) {
    fputcsv($out, [
        $row['shift_id'] ?? '',
        $row['user_name'] ?? '',
        $row['opened_at'] ?? '',
        $row['closed_at'] ?? '',
        (float) ($row['opening_cash'] ?? 0),
        (float) ($row['cash_in'] ?? 0),
        (float) ($row['cash_out'] ?? 0),
        (float) ($row['sales_cash'] ?? 0),
        (float) ($row['refund_cash'] ?? 0),
        (float) ($row['expected_cash'] ?? 0),
        $row['closing_cash'] === null ? '' : (float) $row['closing_cash'],
        $row['diff_cash'] === null ? '' : (float) $row['diff_cash'],
        (float) ($row['sales_qris'] ?? 0),
    ]);
}
if (!empty($shiftTotals)) {
    fputcsv($out, [
        'Total',
        '',
        '',
        '',
        (float) ($shiftTotals['opening_cash'] ?? 0),
        (float) ($shiftTotals['cash_in'] ?? 0),
        (float) ($shiftTotals['cash_out'] ?? 0),
        (float) ($shiftTotals['sales_cash'] ?? 0),
        (float) ($shiftTotals['refund_cash'] ?? 0),
        (float) ($shiftTotals['expected_cash'] ?? 0),
        (float) ($shiftTotals['closing_cash'] ?? 0),
        '',
        (float) ($shiftTotals['sales_qris'] ?? 0),
    ]);
}

fclose($out);
exit;
