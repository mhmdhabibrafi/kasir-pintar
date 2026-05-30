<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/helpers/store_operations_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';
require_once __DIR__ . '/../app/models/Product.php';

require_role(['bos']);

$pdo = db();
$user = current_user() ?? [];
$errors = [];
$success = '';

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
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
    'method' => $_GET['method'] ?? 'all',
];

if ($filters['start_date'] > $filters['end_date']) {
    [$filters['start_date'], $filters['end_date']] = [$filters['end_date'], $filters['start_date']];
}

if (!in_array($filters['method'], ['all', 'cash', 'qris'], true)) {
    $filters['method'] = 'all';
}

try {
    $statsStmt = $pdo->prepare(
        'SELECT COUNT(*) AS total_transactions
         FROM transactions
         WHERE DATE(created_at) = :today'
         . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND')
    );
    $statsStmt->execute(tenant_bind([':today' => $today], $pdo));
    $statsRow = $statsStmt->fetch() ?: ['total_transactions' => 0];

    $todayRevenueStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS total_sales
         FROM payments
         WHERE DATE(created_at) = :today'
         . tenant_where_clause($pdo, 'payments', 'payments', 'AND')
    );
    $todayRevenueStmt->execute(tenant_bind([':today' => $today], $pdo));
    $todaySales = (float) $todayRevenueStmt->fetchColumn();

    $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
    $cupsTenant = tenant_multi_where_clause($pdo, [
        'transactions' => 'transactions',
        'transaction_items' => 'transaction_items',
    ]);
    $cupsStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(transaction_items.' . $qtyColumn . '), 0)
         FROM transaction_items
         INNER JOIN transactions ON transactions.id = transaction_items.transaction_id
         WHERE DATE(transactions.created_at) = :today'
         . $cupsTenant['sql']
    );
    $cupsStmt->execute([':today' => $today] + $cupsTenant['params']);
    $todayCups = (int) $cupsStmt->fetchColumn();

    $monthRevenueStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS total_sales
         FROM payments
         WHERE DATE(created_at) BETWEEN :start_date AND :end_date'
         . tenant_where_clause($pdo, 'payments', 'payments', 'AND')
    );
    $monthRevenueStmt->execute(tenant_bind([':start_date' => $monthStart, ':end_date' => $monthEnd], $pdo));
    $monthSales = (float) $monthRevenueStmt->fetchColumn();

    $summary = Transaction::getPaymentSummary($pdo, $filters['start_date'], $filters['end_date']);
    $refundToday = refund_sum_by_date_range($today, $today);
    $refundMonth = refund_sum_by_date_range($monthStart, $monthEnd);
    $refundRange = refund_sum_by_date_range($filters['start_date'], $filters['end_date']);
    $refundCash = refund_sum_by_date_range($filters['start_date'], $filters['end_date'], 'cash');
    $refundQris = refund_sum_by_date_range($filters['start_date'], $filters['end_date'], 'qris');

    $todaySales = max(0.0, $todaySales - $refundToday);
    $monthSales = max(0.0, $monthSales - $refundMonth);
    $summary['cash'] = max(0.0, (float) $summary['cash'] - $refundCash);
    $summary['qris'] = max(0.0, (float) $summary['qris'] - $refundQris);

    $rangeCups = Transaction::getTotalCupByDateRange($pdo, $filters['start_date'], $filters['end_date']);
    $revenueComparison = Transaction::getRevenueComparison($pdo, $filters['end_date']);
    $topProducts = Transaction::getTopProducts($pdo, $filters['start_date'], $filters['end_date'], 5);
    $chartStart = date('Y-m-d', strtotime($filters['end_date'] . ' -6 days'));
    $revenueSeries = Transaction::getRevenueSeries($pdo, $chartStart, $filters['end_date']);

    $todayRefund = refund_sum_by_date_range($filters['end_date'], $filters['end_date']);
    $yesterday = date('Y-m-d', strtotime($filters['end_date'] . ' -1 day'));
    $yesterdayRefund = refund_sum_by_date_range($yesterday, $yesterday);
    $revenueComparison['today'] = max(0.0, (float) $revenueComparison['today'] - $todayRefund);
    $revenueComparison['yesterday'] = max(0.0, (float) $revenueComparison['yesterday'] - $yesterdayRefund);
    $revenueComparison['diff'] = $revenueComparison['today'] - $revenueComparison['yesterday'];
    $revenueComparison['pct'] = $revenueComparison['yesterday'] > 0
        ? ($revenueComparison['diff'] / $revenueComparison['yesterday']) * 100
        : ($revenueComparison['today'] > 0 ? 100.0 : 0.0);
    $revenueComparison['trend'] = $revenueComparison['diff'] >= 0 ? 'up' : 'down';

    $refundByDate = refund_group_by_date($chartStart, $filters['end_date']);
    foreach ($revenueSeries as $index => $row) {
        $dateKey = $row['date'] ?? null;
        if ($dateKey && isset($refundByDate[$dateKey])) {
            $revenueSeries[$index]['value'] = max(0.0, (float) $row['value'] - (float) $refundByDate[$dateKey]);
        }
    }
    $rangeCountStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM transactions WHERE DATE(created_at) BETWEEN :start_date AND :end_date'
        . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND')
    );
    $rangeCountStmt->execute(tenant_bind([':start_date' => $filters['start_date'], ':end_date' => $filters['end_date']], $pdo));
    $hasTransactions = (int) $rangeCountStmt->fetchColumn() > 0;

    $totalColumn = Transaction::totalColumn($pdo) ?? 'total_amount';
    $query = 'SELECT transactions.id, transactions.' . $totalColumn . ' AS total_amount, transactions.created_at,
                     users.name AS cashier, payments.method, payments.proof AS qris_proof
              FROM transactions
              INNER JOIN users ON users.id = transactions.user_id
              LEFT JOIN payments ON payments.transaction_id = transactions.id
              WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date'
              . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND');
    $params = [
        ':start_date' => $filters['start_date'],
        ':end_date' => $filters['end_date'],
    ];

    if ($filters['method'] !== 'all') {
        $query .= ' AND payments.method = :method';
        $params[':method'] = $filters['method'];
    }

    $query .= ' ORDER BY transactions.created_at DESC';

    $listStmt = $pdo->prepare($query);
    $listStmt->execute(tenant_bind($params, $pdo));
    $transactions = $listStmt->fetchAll();

    $rangeSummary = ['sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0];

    if (!empty($transactions)) {
        $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
        $costColumn = Product::costColumn($pdo);
        $transactionIds = array_column($transactions, 'id');
        $idParams = [];
        $placeholders = implode(',', array_map(static function (int $index) use (&$idParams, $transactionIds): string {
            $key = ':transaction_id_' . $index;
            $idParams[$key] = (int) $transactionIds[$index];
            return $key;
        }, array_keys($transactionIds)));
        $selectCost = $costColumn ? 'products.' . $costColumn . ' AS cost_price' : '0 AS cost_price';
        $itemsTenant = tenant_multi_where_clause($pdo, [
            'transaction_items' => 'transaction_items',
            'products' => 'products',
        ]);

        $itemsStmt = $pdo->prepare(
            'SELECT transaction_items.transaction_id,
                    transaction_items.product_id,
                    transaction_items.' . $qtyColumn . ' AS qty,
                    transaction_items.price,
                    products.name AS product_name,
                    ' . $selectCost . '
             FROM transaction_items
             INNER JOIN products ON products.id = transaction_items.product_id
             WHERE transaction_items.transaction_id IN (' . $placeholders . ')'
             . $itemsTenant['sql'] . '
             ORDER BY transaction_items.transaction_id'
        );
        $itemsStmt->execute($idParams + $itemsTenant['params']);
        $items = $itemsStmt->fetchAll();

        $itemsByTransaction = [];
        $totalsByTransaction = [];
        foreach ($items as $item) {
            $transactionId = (int) $item['transaction_id'];
            $qty = (int) $item['qty'];
            $price = (float) $item['price'];
            $costPrice = (float) ($item['cost_price'] ?? 0);
            $lineTotal = $qty * $price;
            $lineCost = $qty * $costPrice;

            $itemsByTransaction[$transactionId][] = [
                'name' => $item['product_name'],
                'qty' => $qty,
                'price' => $price,
                'total' => $lineTotal,
            ];

            if (!isset($totalsByTransaction[$transactionId])) {
                $totalsByTransaction[$transactionId] = ['sales' => 0.0, 'cost' => 0.0];
            }
            $totalsByTransaction[$transactionId]['sales'] += $lineTotal;
            $totalsByTransaction[$transactionId]['cost'] += $lineCost;
        }

        foreach ($transactions as $index => $row) {
            $transactionId = (int) $row['id'];
            $sales = isset($totalsByTransaction[$transactionId])
                ? (float) $totalsByTransaction[$transactionId]['sales']
                : (float) $row['total_amount'];
            $cost = isset($totalsByTransaction[$transactionId])
                ? (float) $totalsByTransaction[$transactionId]['cost']
                : 0.0;
            $profit = $sales - $cost;

            $transactions[$index]['items'] = $itemsByTransaction[$transactionId] ?? [];
            $transactions[$index]['total_sales'] = $sales;
            $transactions[$index]['total_cost'] = $cost;
            $transactions[$index]['total_profit'] = $profit;

            $rangeSummary['sales'] += $sales;
            $rangeSummary['cost'] += $cost;
            $rangeSummary['profit'] += $profit;
        }
    } else {
        $rangeSummary = ['sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0];
    }

    $rangeNetSales = max(0.0, (float) $summary['cash'] + (float) $summary['qris']);
    $rangeSales = $rangeNetSales;
    $operationProducts = Product::all($pdo);
    $operationsSnapshot = store_operations_snapshot($pdo, is_array($user) ? $user : [], $operationProducts);

    $stats = [
        'today_sales' => $todaySales,
        'range_sales' => $rangeSales,
        'month_sales' => $monthSales,
        'transactions_today' => (int) $statsRow['total_transactions'],
        'cups_today' => $todayCups,
        'cups_range' => $rangeCups,
        'revenue_comparison' => $revenueComparison,
        'revenue_series' => $revenueSeries,
        'top_products' => $topProducts,
        'has_transactions' => $hasTransactions,
    ];
} catch (Throwable $e) {
    $errors[] = 'Gagal memuat laporan. Silakan coba lagi.';
    $summary = ['cash' => 0, 'qris' => 0];
    $stats = ['today_sales' => 0, 'range_sales' => 0, 'month_sales' => 0, 'transactions_today' => 0, 'cups_range' => 0, 'revenue_series' => []];
    $operationsSnapshot = store_operations_snapshot($pdo, is_array($user) ? $user : [], []);
    $topProducts = [];
    $revenueComparison = ['today' => 0, 'yesterday' => 0, 'diff' => 0, 'pct' => 0, 'trend' => 'up'];
    $hasTransactions = false;
    $transactions = [];
    $rangeSummary = ['sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0];
}


$title = 'Dashboard Owner';
require_once __DIR__ . '/../app/views/bos/dashboard.php';
