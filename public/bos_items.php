<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';

require_role(['bos']);

$pdo = db();
$errors = [];

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
    'method' => $_GET['method'] ?? 'all',
];

if ($filters['start_date'] > $filters['end_date']) {
    [$filters['start_date'], $filters['end_date']] = [$filters['end_date'], $filters['start_date']];
}

if (!in_array($filters['method'], ['all', 'cash', 'qris'], true)) {
    $filters['method'] = 'all';
}

try {
    $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
    $subtotalColumn = Transaction::itemSubtotalColumn($pdo);
    $totalSalesExpr = $subtotalColumn
        ? 'SUM(transaction_items.' . $subtotalColumn . ')'
        : 'SUM(transaction_items.' . $qtyColumn . ' * transaction_items.price)';
    $tenantFilters = tenant_multi_where_clause($pdo, [
        'transactions' => 'transactions',
        'transaction_items' => 'transaction_items',
        'products' => 'products',
    ]);

    $query = 'SELECT products.name AS product_name,
                     SUM(transaction_items.' . $qtyColumn . ') AS total_qty,
                     ' . $totalSalesExpr . ' AS total_sales
              FROM transaction_items
              INNER JOIN transactions ON transactions.id = transaction_items.transaction_id
              INNER JOIN products ON products.id = transaction_items.product_id
              LEFT JOIN payments ON payments.transaction_id = transactions.id
              WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date'
              . $tenantFilters['sql'];
    $params = [
        ':start_date' => $filters['start_date'],
        ':end_date' => $filters['end_date'],
    ];

    if ($filters['method'] !== 'all') {
        $query .= ' AND payments.method = :method';
        $params[':method'] = $filters['method'];
    }

    $query .= ' GROUP BY products.name ORDER BY total_sales DESC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params + $tenantFilters['params']);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = 'Gagal memuat laporan item.';
    $rows = [];
}

$totalQty = 0;
$totalSales = 0.0;
foreach ($rows as $row) {
    $totalQty += (int) ($row['total_qty'] ?? 0);
    $totalSales += (float) ($row['total_sales'] ?? 0);
}
$topItem = $rows[0] ?? null;
$itemReportSummary = [
    'unique_items' => count($rows),
    'total_qty' => $totalQty,
    'total_sales' => $totalSales,
    'average_sales_per_item' => !empty($rows) ? $totalSales / count($rows) : 0.0,
    'top_item_name' => (string) ($topItem['product_name'] ?? '-'),
    'top_item_qty' => (int) ($topItem['total_qty'] ?? 0),
    'top_item_sales' => (float) ($topItem['total_sales'] ?? 0),
];

$title = 'Laporan Item Terjual';

require_once __DIR__ . '/../app/views/bos/items.php';
