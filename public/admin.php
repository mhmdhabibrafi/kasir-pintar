<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/helpers/store_operations_helper.php';
require_once __DIR__ . '/../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Customer.php';

require_role(['admin', 'bos']);

$currentUser = current_user() ?? [];
if (($currentUser['role'] ?? '') === 'bos') {
    header('Location: bos.php');
    exit;
}

$pdo = db();
$errors = [];
$today = date('Y-m-d');

$normalizeDate = static function (?string $value, string $fallback): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
    if (!$date || $date->format('Y-m-d') !== $raw) {
        return $fallback;
    }

    return $raw;
};

$filters = [
    'start_date' => $normalizeDate($_GET['start_date'] ?? null, $today),
    'end_date' => $normalizeDate($_GET['end_date'] ?? null, $today),
];

if ($filters['start_date'] > $filters['end_date']) {
    [$filters['start_date'], $filters['end_date']] = [$filters['end_date'], $filters['start_date']];
}

$dashboard = [
    'filters' => $filters,
    'period_days' => 1,
    'transactions_count' => 0,
    'today_transactions' => 0,
    'gross_sales' => 0.0,
    'refund_total' => 0.0,
    'net_sales' => 0.0,
    'gross_profit' => 0.0,
    'average_ticket' => 0.0,
    'daily_average' => 0.0,
    'product_total' => 0,
    'product_active' => 0,
    'product_inactive' => 0,
    'staff_total' => 0,
    'staff_active' => 0,
    'member_total' => 0,
    'member_active' => 0,
    'low_stock_count' => 0,
    'low_stock_items' => [],
    'payment_percentage' => [
        'cash_total' => 0.0,
        'qris_total' => 0.0,
        'cash_pct' => 0.0,
        'qris_pct' => 0.0,
    ],
    'revenue_series' => [],
    'revenue_comparison' => ['today' => 0.0, 'yesterday' => 0.0, 'diff' => 0.0, 'pct' => 0.0, 'trend' => 'up'],
    'top_products' => [],
    'last_transaction' => null,
    'operations' => [],
    'attention_items' => [],
];

try {
    $periodStart = new DateTimeImmutable($filters['start_date']);
    $periodEnd = new DateTimeImmutable($filters['end_date']);
    $periodDays = ((int) $periodStart->diff($periodEnd)->days) + 1;
    $dashboard['period_days'] = max(1, $periodDays);

    $products = Product::allForAdmin($pdo);
    $customers = Customer::all($pdo);
    $users = array_values(array_filter(
        User::all($pdo),
        static fn (array $row): bool => (string) ($row['role_name'] ?? '') !== 'superadmin'
    ));
    $operationsSnapshot = store_operations_snapshot($pdo, is_array($currentUser) ? $currentUser : [], $products);
    $lowStockItems = $operationsSnapshot['low_stock_items'] ?? [];

    $summary = Transaction::getPaymentSummary($pdo, $filters['start_date'], $filters['end_date']);
    $refundTotal = refund_sum_by_date_range($filters['start_date'], $filters['end_date']);
    $refundCash = refund_sum_by_date_range($filters['start_date'], $filters['end_date'], 'cash');
    $refundQris = refund_sum_by_date_range($filters['start_date'], $filters['end_date'], 'qris');

    $netCash = max(0.0, (float) $summary['cash'] - $refundCash);
    $netQris = max(0.0, (float) $summary['qris'] - $refundQris);
    $netSales = $netCash + $netQris;
    $paymentTotal = max(1.0, $netCash + $netQris);

    $transactionCountStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM transactions WHERE DATE(created_at) BETWEEN :start_date AND :end_date'
        . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND')
    );
    $transactionCountStmt->execute(tenant_bind([
        ':start_date' => $filters['start_date'],
        ':end_date' => $filters['end_date'],
    ], $pdo));
    $transactionCount = (int) $transactionCountStmt->fetchColumn();

    $todayTransactionCount = Transaction::getTransactionCountToday($pdo, $today);
    $lastTransaction = Transaction::getLastTransaction($pdo, $filters['start_date'], $filters['end_date']);

    $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
    $costColumn = Product::costColumn($pdo);
    $costExpression = $costColumn ? 'COALESCE(products.' . $costColumn . ', 0)' : '0';
    $profitTenant = tenant_multi_where_clause($pdo, [
        'transactions' => 'transactions',
        'transaction_items' => 'transaction_items',
        'products' => 'products',
    ]);
    $profitStmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(transaction_items.price * transaction_items.' . $qtyColumn . '), 0) AS gross_sales,
            COALESCE(SUM(' . $costExpression . ' * transaction_items.' . $qtyColumn . '), 0) AS total_cost
         FROM transaction_items
         INNER JOIN transactions ON transactions.id = transaction_items.transaction_id
         INNER JOIN products ON products.id = transaction_items.product_id
         WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date'
         . $profitTenant['sql']
    );
    $profitStmt->execute([
        ':start_date' => $filters['start_date'],
        ':end_date' => $filters['end_date'],
    ] + $profitTenant['params']);
    $profitRow = $profitStmt->fetch() ?: ['gross_sales' => 0, 'total_cost' => 0];
    $grossSales = (float) ($profitRow['gross_sales'] ?? 0);
    $grossProfit = $grossSales - (float) ($profitRow['total_cost'] ?? 0);

    $revenueComparison = Transaction::getRevenueComparison($pdo, $filters['end_date']);
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

    $chartStart = date('Y-m-d', strtotime($filters['end_date'] . ' -6 days'));
    if ($chartStart < $filters['start_date']) {
        $chartStart = $filters['start_date'];
    }
    $revenueSeries = Transaction::getRevenueSeries($pdo, $chartStart, $filters['end_date']);
    $refundByDate = refund_group_by_date($chartStart, $filters['end_date']);
    foreach ($revenueSeries as $index => $row) {
        $dateKey = (string) ($row['date'] ?? '');
        if ($dateKey !== '' && isset($refundByDate[$dateKey])) {
            $revenueSeries[$index]['value'] = max(0.0, (float) $row['value'] - (float) $refundByDate[$dateKey]);
        }
    }

    $activeColumn = Product::activeColumn($pdo);
    $activeProductCount = 0;
    foreach ($products as $product) {
        $isActive = !$activeColumn || (int) ($product['is_active'] ?? 1) === 1;
        if ($isActive) {
            $activeProductCount++;
        }
    }

    $userActiveColumn = User::activeColumn($pdo);
    $activeStaffCount = 0;
    foreach ($users as $userRow) {
        $isActive = !$userActiveColumn || (int) ($userRow[$userActiveColumn] ?? 1) === 1;
        if ($isActive) {
            $activeStaffCount++;
        }
    }

    $activeMemberCount = 0;
    foreach ($customers as $customer) {
        if (!empty($customer['is_active'])) {
            $activeMemberCount++;
        }
    }

    $topProducts = Transaction::getTopProducts($pdo, $filters['start_date'], $filters['end_date'], 5);

    $attentionItems = [];
    if (!empty($operationsSnapshot['profile_needs_attention'])) {
        $attentionItems[] = [
            'title' => 'Profil toko belum lengkap',
            'detail' => 'Lengkapi identitas outlet agar struk, laporan, dan tampilan admin lebih profesional.',
            'url' => base_url('admin_store.php'),
            'action' => 'Lengkapi Profil',
        ];
    }
    if (count($lowStockItems) > 0) {
        $attentionItems[] = [
            'title' => 'Ada stok kritis yang perlu ditindak',
            'detail' => count($lowStockItems) . ' produk sudah menyentuh batas minimum stok.',
            'url' => base_url('admin_inventory.php?stock_status=critical'),
            'action' => 'Buka Inventori',
        ];
    }
    if ($activeStaffCount <= 0) {
        $attentionItems[] = [
            'title' => 'Belum ada user aktif',
            'detail' => 'Sistem kasir butuh minimal satu user aktif untuk operasional harian.',
            'url' => base_url('admin_users.php'),
            'action' => 'Kelola User',
        ];
    }
    if ($transactionCount <= 0) {
        $attentionItems[] = [
            'title' => 'Belum ada transaksi pada periode ini',
            'detail' => 'Pastikan kasir aktif, produk siap jual, dan metode pembayaran berjalan normal.',
            'url' => base_url('kasir.php'),
            'action' => 'Buka POS',
        ];
    }

    $dashboard = [
        'filters' => $filters,
        'period_days' => max(1, $periodDays),
        'transactions_count' => $transactionCount,
        'today_transactions' => (int) ($todayTransactionCount['count'] ?? 0),
        'gross_sales' => $grossSales,
        'refund_total' => $refundTotal,
        'net_sales' => $netSales,
        'gross_profit' => $grossProfit,
        'average_ticket' => $transactionCount > 0 ? ($netSales / $transactionCount) : 0.0,
        'daily_average' => $periodDays > 0 ? ($netSales / $periodDays) : 0.0,
        'product_total' => count($products),
        'product_active' => $activeProductCount,
        'product_inactive' => max(0, count($products) - $activeProductCount),
        'staff_total' => count($users),
        'staff_active' => $activeStaffCount,
        'member_total' => count($customers),
        'member_active' => $activeMemberCount,
        'low_stock_count' => count($lowStockItems),
        'low_stock_items' => array_slice($lowStockItems, 0, 5),
        'payment_percentage' => [
            'cash_total' => $netCash,
            'qris_total' => $netQris,
            'cash_pct' => ($netCash / $paymentTotal) * 100,
            'qris_pct' => ($netQris / $paymentTotal) * 100,
        ],
        'revenue_series' => $revenueSeries,
        'revenue_comparison' => $revenueComparison,
        'top_products' => $topProducts,
        'last_transaction' => $lastTransaction ?: null,
        'operations' => $operationsSnapshot,
        'attention_items' => $attentionItems,
    ];
} catch (Throwable $e) {
    error_log('[admin_dashboard] ' . $e->getMessage());
    $errors[] = 'Dashboard operasional belum bisa dimuat penuh. Periksa koneksi database dan struktur data.';
}

$title = 'Dashboard Operasional';

require_once __DIR__ . '/../app/views/admin/dashboard.php';
