<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_migration_helper.php';
require_once __DIR__ . '/shift_helper.php';
require_once __DIR__ . '/tenant_helper.php';

function cash_report_date_range(string $startDate, string $endDate): array
{
    $days = [];
    $start = DateTime::createFromFormat('Y-m-d', $startDate) ?: new DateTime($startDate);
    $end = DateTime::createFromFormat('Y-m-d', $endDate) ?: new DateTime($endDate);
    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }
    $cursor = clone $start;
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m-d');
        $days[$key] = [
            'date' => $key,
            'cash_in' => 0.0,
            'cash_out' => 0.0,
            'cash_sales' => 0.0,
            'qris_sales' => 0.0,
            'refund_cash' => 0.0,
            'refund_qris' => 0.0,
        ];
        $cursor->modify('+1 day');
    }
    return $days;
}

function cash_report_daily(string $startDate, string $endDate): array
{
    ensure_update_schema();
    $pdo = db();
    $days = cash_report_date_range($startDate, $endDate);

    $stmt = $pdo->prepare(
        'SELECT DATE(created_at) AS day, movement_type, COALESCE(SUM(amount), 0) AS total
         FROM shift_movements
         WHERE DATE(created_at) BETWEEN :start_date AND :end_date' . tenant_where_clause($pdo, 'shift_movements', 'shift_movements', 'AND') . '
         GROUP BY DATE(created_at), movement_type'
    );
    $stmt->execute(tenant_bind([':start_date' => $startDate, ':end_date' => $endDate], $pdo));
    foreach ($stmt->fetchAll() as $row) {
        $day = (string) $row['day'];
        if (!isset($days[$day])) {
            continue;
        }
        $type = (string) $row['movement_type'];
        $total = (float) $row['total'];
        if ($type === 'in') {
            $days[$day]['cash_in'] = $total;
        } elseif ($type === 'out') {
            $days[$day]['cash_out'] = $total;
        }
    }

    $trxStmt = $pdo->prepare(
        'SELECT DATE(created_at) AS day, payment_method, COALESCE(SUM(grand_total), 0) AS total
         FROM transaction_meta
         WHERE DATE(created_at) BETWEEN :start_date AND :end_date' . tenant_where_clause($pdo, 'transaction_meta', 'transaction_meta', 'AND') . '
         GROUP BY DATE(created_at), payment_method'
    );
    $trxStmt->execute(tenant_bind([':start_date' => $startDate, ':end_date' => $endDate], $pdo));
    foreach ($trxStmt->fetchAll() as $row) {
        $day = (string) $row['day'];
        if (!isset($days[$day])) {
            continue;
        }
        $method = strtolower((string) $row['payment_method']);
        $total = (float) $row['total'];
        if ($method === 'cash') {
            $days[$day]['cash_sales'] = $total;
        } elseif ($method === 'qris') {
            $days[$day]['qris_sales'] = $total;
        }
    }

    $refundStmt = $pdo->prepare(
        'SELECT DATE(created_at) AS day, method, COALESCE(SUM(amount), 0) AS total
         FROM refunds
         WHERE DATE(created_at) BETWEEN :start_date AND :end_date' . tenant_where_clause($pdo, 'refunds', 'refunds', 'AND') . '
         GROUP BY DATE(created_at), method'
    );
    $refundStmt->execute(tenant_bind([':start_date' => $startDate, ':end_date' => $endDate], $pdo));
    foreach ($refundStmt->fetchAll() as $row) {
        $day = (string) $row['day'];
        if (!isset($days[$day])) {
            continue;
        }
        $method = strtolower((string) $row['method']);
        $total = (float) $row['total'];
        if ($method === 'cash') {
            $days[$day]['refund_cash'] = $total;
        } elseif ($method === 'qris') {
            $days[$day]['refund_qris'] = $total;
        }
    }

    return array_values($days);
}

function cash_report_daily_totals(array $dailyRecap): array
{
    $totals = [
        'cash_in' => 0.0,
        'cash_out' => 0.0,
        'cash_sales' => 0.0,
        'qris_sales' => 0.0,
        'refund_cash' => 0.0,
        'refund_qris' => 0.0,
    ];
    foreach ($dailyRecap as $row) {
        $totals['cash_in'] += (float) ($row['cash_in'] ?? 0);
        $totals['cash_out'] += (float) ($row['cash_out'] ?? 0);
        $totals['cash_sales'] += (float) ($row['cash_sales'] ?? 0);
        $totals['qris_sales'] += (float) ($row['qris_sales'] ?? 0);
        $totals['refund_cash'] += (float) ($row['refund_cash'] ?? 0);
        $totals['refund_qris'] += (float) ($row['refund_qris'] ?? 0);
    }
    return $totals;
}

function shift_report_by_date_range(string $startDate, string $endDate): array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM shifts
         WHERE DATE(opened_at) BETWEEN :start_date AND :end_date' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND') . '
         ORDER BY opened_at ASC'
    );
    $stmt->execute(tenant_bind([':start_date' => $startDate, ':end_date' => $endDate], $pdo));
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return [];
    }
    $ids = array_map(static fn ($row) => (int) $row['id'], $rows);
    $movements = shift_movements_by_shift_ids($ids);
    $summaries = shift_summaries(array_map(static fn ($row) => (string) $row['shift_code'], $rows));
    $result = [];
    foreach ($rows as $row) {
        $shift = shift_map_row($row, $movements[(int) $row['id']] ?? []);
        $shift['summary'] = $summaries[(string) $shift['shift_id']] ?? shift_empty_summary();
        $result[] = $shift;
    }
    return $result;
}

function cash_report_shift_rows(string $startDate, string $endDate): array
{
    $shiftReports = shift_report_by_date_range($startDate, $endDate);
    $rows = [];
    $totals = [
        'opening_cash' => 0.0,
        'cash_in' => 0.0,
        'cash_out' => 0.0,
        'sales_cash' => 0.0,
        'refund_cash' => 0.0,
        'sales_qris' => 0.0,
        'expected_cash' => 0.0,
        'closing_cash' => 0.0,
    ];

    foreach ($shiftReports as $shift) {
        $movementTotals = shift_movement_totals($shift);
        $summary = $shift['summary'] ?? [];
        $opening = (float) ($shift['opening_cash'] ?? 0);
        $cashIn = (float) ($movementTotals['cash_in'] ?? 0);
        $cashOut = (float) ($movementTotals['cash_out'] ?? 0);
        $salesCash = (float) ($summary['sales_cash'] ?? 0);
        $refundCash = (float) ($summary['refund_cash'] ?? 0);
        $salesQris = (float) ($summary['sales_qris'] ?? 0);
        $expected = shift_expected_cash($shift, $summary);
        $closing = isset($shift['closing_cash']) ? (float) $shift['closing_cash'] : null;
        $diff = $closing !== null ? $closing - $expected : null;

        $rows[] = array_merge($shift, [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'sales_cash' => $salesCash,
            'refund_cash' => $refundCash,
            'sales_qris' => $salesQris,
            'expected_cash' => $expected,
            'closing_cash' => $closing,
            'diff_cash' => $diff,
        ]);

        $totals['opening_cash'] += $opening;
        $totals['cash_in'] += $cashIn;
        $totals['cash_out'] += $cashOut;
        $totals['sales_cash'] += $salesCash;
        $totals['refund_cash'] += $refundCash;
        $totals['sales_qris'] += $salesQris;
        $totals['expected_cash'] += $expected;
        if ($closing !== null) {
            $totals['closing_cash'] += $closing;
        }
    }

    return ['rows' => $rows, 'totals' => $totals];
}
