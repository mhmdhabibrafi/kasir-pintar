<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/cash_report_helper.php';
require_once __DIR__ . '/../app/helpers/store_info_helper.php';

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
$storeInfo = store_info_get();
$user = current_user();
$printedAt = date('Y-m-d H:i:s');
$printedBy = trim((string) ($user['name'] ?? $user['username'] ?? '-'));

$safeText = static function ($value): string {
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    return preg_match('/^[=+\-@\t\r\n]/', $text) === 1 ? "'" . $text : $text;
};

$money = static function ($value): string {
    return number_format((float) $value, 0, '.', '');
};

$filename = sprintf('kas_harian_%s_%s.csv', $filters['start_date'], $filters['end_date']);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

fputcsv($out, ['Laporan', 'Rekap Kas Harian']);
fputcsv($out, ['Store', $safeText($storeInfo['store_name'] ?? 'KASPINDO')]);
if (!empty($storeInfo['store_code'])) {
    fputcsv($out, ['Kode Store', $safeText($storeInfo['store_code'])]);
}
fputcsv($out, ['Periode', $filters['start_date'] . ' s/d ' . $filters['end_date']]);
fputcsv($out, ['Mata Uang', 'IDR']);
fputcsv($out, ['Dicetak Pada', $printedAt]);
fputcsv($out, ['Dicetak Oleh', $safeText($printedBy)]);
fputcsv($out, []);

fputcsv($out, ['REKAP HARIAN']);
fputcsv($out, ['Tanggal', 'Cash In (IDR)', 'Cash Out (IDR)', 'Sales Cash (IDR)', 'Refund Cash (IDR)', 'Net Cash (IDR)', 'Sales QRIS (IDR)', 'Refund QRIS (IDR)', 'Net QRIS (IDR)']);
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
        $safeText($row['date'] ?? ''),
        $money($cashIn),
        $money($cashOut),
        $money($cashSales),
        $money($refundCash),
        $money($netCash),
        $money($qrisSales),
        $money($refundQris),
        $money($netQris),
    ]);
}
$netCashTotal = (float) ($dailyTotals['cash_sales'] ?? 0)
    - (float) ($dailyTotals['refund_cash'] ?? 0)
    + (float) ($dailyTotals['cash_in'] ?? 0)
    - (float) ($dailyTotals['cash_out'] ?? 0);
$netQrisTotal = (float) ($dailyTotals['qris_sales'] ?? 0) - (float) ($dailyTotals['refund_qris'] ?? 0);
fputcsv($out, [
    'Total',
    $money($dailyTotals['cash_in'] ?? 0),
    $money($dailyTotals['cash_out'] ?? 0),
    $money($dailyTotals['cash_sales'] ?? 0),
    $money($dailyTotals['refund_cash'] ?? 0),
    $money($netCashTotal),
    $money($dailyTotals['qris_sales'] ?? 0),
    $money($dailyTotals['refund_qris'] ?? 0),
    $money($netQrisTotal),
]);

fputcsv($out, []);
fputcsv($out, ['DETAIL SHIFT']);
fputcsv($out, ['Shift ID', 'Kasir', 'Mulai', 'Selesai', 'Status', 'Kas Awal (IDR)', 'Cash In (IDR)', 'Cash Out (IDR)', 'Sales Cash (IDR)', 'Refund Cash (IDR)', 'Expected Cash (IDR)', 'Kas Akhir (IDR)', 'Selisih (IDR)', 'Sales QRIS (IDR)']);
foreach ($shiftRows as $row) {
    fputcsv($out, [
        $safeText($row['shift_id'] ?? ''),
        $safeText($row['user_name'] ?? ''),
        $safeText($row['opened_at'] ?? ''),
        $safeText($row['closed_at'] ?? ''),
        $safeText($row['status'] ?? ''),
        $money($row['opening_cash'] ?? 0),
        $money($row['cash_in'] ?? 0),
        $money($row['cash_out'] ?? 0),
        $money($row['sales_cash'] ?? 0),
        $money($row['refund_cash'] ?? 0),
        $money($row['expected_cash'] ?? 0),
        $row['closing_cash'] === null ? '' : $money($row['closing_cash']),
        $row['diff_cash'] === null ? '' : $money($row['diff_cash']),
        $money($row['sales_qris'] ?? 0),
    ]);
}
if (!empty($shiftTotals)) {
    fputcsv($out, [
        'Total',
        '',
        '',
        '',
        '',
        $money($shiftTotals['opening_cash'] ?? 0),
        $money($shiftTotals['cash_in'] ?? 0),
        $money($shiftTotals['cash_out'] ?? 0),
        $money($shiftTotals['sales_cash'] ?? 0),
        $money($shiftTotals['refund_cash'] ?? 0),
        $money($shiftTotals['expected_cash'] ?? 0),
        $money($shiftTotals['closing_cash'] ?? 0),
        '',
        $money($shiftTotals['sales_qris'] ?? 0),
    ]);
}

fclose($out);
exit;
