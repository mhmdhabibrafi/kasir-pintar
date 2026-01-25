<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Transaction.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/config/database.php';

require_role(['admin', 'bos']);

$pdo = db();
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
$salesStmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments');
$totalSales = (float) $salesStmt->fetchColumn();

$todayStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(created_at) = :today');
$todayStmt->execute([':today' => $today]);
$todaySales = (float) $todayStmt->fetchColumn();

$periodSalesStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(created_at) BETWEEN :start_date AND :end_date'
);
$periodSalesStmt->execute([':start_date' => $filters['start_date'], ':end_date' => $filters['end_date']]);
$periodSales = (float) $periodSalesStmt->fetchColumn();

$refundAll = 0.0;
$refundToday = refund_sum_by_date_range($today, $today);
$refundPeriod = refund_sum_by_date_range($filters['start_date'], $filters['end_date']);
foreach (refund_list() as $refund) {
    $refundAll += (float) ($refund['amount'] ?? 0);
}
$totalSales = max(0.0, $totalSales - $refundAll);
$todaySales = max(0.0, $todaySales - $refundToday);
$periodSales = max(0.0, $periodSales - $refundPeriod);

$qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
$cupsStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(transaction_items.' . $qtyColumn . '), 0)
     FROM transaction_items
     INNER JOIN transactions ON transactions.id = transaction_items.transaction_id
     WHERE DATE(transactions.created_at) = :today'
);
$cupsStmt->execute([':today' => $today]);
$todayCups = (int) $cupsStmt->fetchColumn();

$paymentSummary = Transaction::getPaymentSummary($pdo, $filters['start_date'], $filters['end_date']);
$revenueComparison = Transaction::getRevenueComparison($pdo, $filters['end_date']);
$topProducts = Transaction::getTopProducts($pdo, $filters['start_date'], $filters['end_date'], 5);
$rangeCups = Transaction::getTotalCupByDateRange($pdo, $filters['start_date'], $filters['end_date']);
$lastTransaction = Transaction::getLastTransaction($pdo, $filters['start_date'], $filters['end_date']);
$transactionCount = Transaction::getTransactionCountToday($pdo, $filters['end_date']);
$paymentPercentage = Transaction::getPaymentPercentage($pdo, $filters['start_date'], $filters['end_date']);
$lastTransactionTime = Transaction::getLastTransactionTime($pdo, $filters['start_date'], $filters['end_date']);

$refundCash = refund_sum_by_date_range($filters['start_date'], $filters['end_date'], 'cash');
$refundQris = refund_sum_by_date_range($filters['start_date'], $filters['end_date'], 'qris');
$paymentSummary['cash'] = max(0.0, (float) $paymentSummary['cash'] - $refundCash);
$paymentSummary['qris'] = max(0.0, (float) $paymentSummary['qris'] - $refundQris);
$paymentPercentage['cash_total'] = max(0.0, (float) $paymentPercentage['cash_total'] - $refundCash);
$paymentPercentage['qris_total'] = max(0.0, (float) $paymentPercentage['qris_total'] - $refundQris);
$grandTotalPct = max(1.0, $paymentPercentage['cash_total'] + $paymentPercentage['qris_total']);
$paymentPercentage['cash_pct'] = ($paymentPercentage['cash_total'] / $grandTotalPct) * 100;
$paymentPercentage['qris_pct'] = 100 - $paymentPercentage['cash_pct'];

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

$rangeCountStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM transactions WHERE DATE(created_at) BETWEEN :start_date AND :end_date'
);
$rangeCountStmt->execute([':start_date' => $filters['start_date'], ':end_date' => $filters['end_date']]);
$hasTransactions = (int) $rangeCountStmt->fetchColumn() > 0;

$hoursThreshold = 2;
$alertNoTransaction = true;
if (!empty($lastTransactionTime['last_time'])) {
    $lastTime = new DateTime((string) $lastTransactionTime['last_time']);
    $now = new DateTime('now');
    $hoursDiff = ($now->getTimestamp() - $lastTime->getTimestamp()) / 3600;
    $alertNoTransaction = $hoursDiff >= $hoursThreshold;
}
$alerts = [
    'no_transaction_over_hours' => $alertNoTransaction,
    'qris_zero_transaction' => ((float) $paymentPercentage['qris_total']) <= 0,
    'cash_zero_transaction' => ((float) $paymentPercentage['cash_total']) <= 0,
];
$activityPanel = [
    'last_time' => $lastTransaction['created_at'] ?? null,
    'last_cashier' => $lastTransaction['cashier'] ?? null,
    'transaction_count_today' => (int) ($transactionCount['count'] ?? 0),
];
$systemStatus = [
    'database_connected' => $pdo instanceof PDO,
    'last_transaction_time' => $lastTransactionTime['last_time'] ?? null,
];

$days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime('-' . $i . ' days'));
    $days[$date] = [
        'label' => date('d/m', strtotime($date)),
        'transactions' => 0,
        'revenue' => 0,
    ];
}

$startDate = array_key_first($days);

$trxStmt = $pdo->prepare(
    'SELECT DATE(created_at) AS day, COUNT(*) AS total
     FROM transactions
     WHERE DATE(created_at) >= :start_date
     GROUP BY DATE(created_at)'
);
$trxStmt->execute([':start_date' => $startDate]);
foreach ($trxStmt->fetchAll() as $row) {
    $day = $row['day'];
    if (isset($days[$day])) {
        $days[$day]['transactions'] = (int) $row['total'];
    }
}

$revStmt = $pdo->prepare(
    'SELECT DATE(created_at) AS day, COALESCE(SUM(amount), 0) AS total
     FROM payments
     WHERE DATE(created_at) >= :start_date
     GROUP BY DATE(created_at)'
);
$revStmt->execute([':start_date' => $startDate]);
foreach ($revStmt->fetchAll() as $row) {
    $day = $row['day'];
    if (isset($days[$day])) {
        $days[$day]['revenue'] = (float) $row['total'];
    }
}

$refundByDate = refund_group_by_date($startDate, $today);
foreach ($refundByDate as $day => $amount) {
    if (isset($days[$day])) {
        $days[$day]['revenue'] = max(0.0, $days[$day]['revenue'] - (float) $amount);
    }
}

$chartTransactions = [];
$chartRevenue = [];
foreach ($days as $day) {
    $chartTransactions[] = ['label' => $day['label'], 'value' => $day['transactions']];
    $chartRevenue[] = ['label' => $day['label'], 'value' => $day['revenue']];
}

$chartStart = date('Y-m-d', strtotime($filters['end_date'] . ' -6 days'));
$revenueSeries = Transaction::getRevenueSeries($pdo, $chartStart, $filters['end_date']);
$revenueSeries = $chartRevenue;

$stats = [
    'transactions' => Transaction::count($pdo),
    'total_sales' => $totalSales,
    'today_sales' => $todaySales,
    'period_sales' => $periodSales,
    'today_cups' => $rangeCups,
    'payment_summary' => $paymentSummary,
    'chart_transactions' => $chartTransactions,
    'chart_revenue' => $chartRevenue,
    'revenue_series' => $revenueSeries,
    'revenue_comparison' => $revenueComparison,
    'top_products' => $topProducts,
    'filters' => $filters,
    'has_transactions' => $hasTransactions,
    'activity_panel' => $activityPanel,
    'alerts' => $alerts,
    'payment_percentage' => $paymentPercentage,
    'system_status' => $systemStatus,
    'users' => User::count($pdo),
    'products' => Product::count($pdo),
    'categories' => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
];

$title = 'Admin Dashboard';
require_once __DIR__ . '/../app/views/admin/dashboard.php';
