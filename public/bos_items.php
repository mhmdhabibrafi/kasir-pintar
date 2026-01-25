<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';

require_role(['bos']);

$pdo = db();
$errors = [];

$today = date('Y-m-d');
$filters = [
    'start_date' => $_GET['start_date'] ?? $today,
    'end_date' => $_GET['end_date'] ?? $today,
    'method' => $_GET['method'] ?? 'all',
];

if (!in_array($filters['method'], ['all', 'cash', 'qris'], true)) {
    $filters['method'] = 'all';
}

try {
    $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
    $subtotalColumn = Transaction::itemSubtotalColumn($pdo);
    $totalSalesExpr = $subtotalColumn
        ? 'SUM(transaction_items.' . $subtotalColumn . ')'
        : 'SUM(transaction_items.' . $qtyColumn . ' * transaction_items.price)';

    $query = 'SELECT products.name AS product_name,
                     SUM(transaction_items.' . $qtyColumn . ') AS total_qty,
                     ' . $totalSalesExpr . ' AS total_sales
              FROM transaction_items
              INNER JOIN transactions ON transactions.id = transaction_items.transaction_id
              INNER JOIN products ON products.id = transaction_items.product_id
              LEFT JOIN payments ON payments.transaction_id = transactions.id
              WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date';
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
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = 'Gagal memuat laporan item.';
    $rows = [];
}

$title = 'Laporan Item Terjual';

require_once __DIR__ . '/../app/views/bos/items.php';
