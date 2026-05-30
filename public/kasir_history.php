<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/transaction_meta_helper.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/helpers/user_permission_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';

require_role(['karyawan', 'bos', 'admin']);

$pdo = db();
$errors = [];
$user = current_user();
user_permission_guard($user, 'access_history', 'Admin menonaktifkan akses riwayat transaksi untuk akun ini.');
$canPrintReceipt = user_can($user, 'print_receipt');
$canBluetoothPrint = user_can($user, 'bluetooth_print');

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
    $totalColumn = Transaction::totalColumn($pdo) ?? 'total_amount';
    $query = 'SELECT transactions.id, transactions.' . $totalColumn . ' AS total_amount, transactions.created_at,
                     payments.method
              FROM transactions
              LEFT JOIN payments ON payments.transaction_id = transactions.id
              WHERE transactions.user_id = :user_id
                AND DATE(transactions.created_at) BETWEEN :start_date AND :end_date' . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND');
    $params = [
        ':user_id' => (int) ($user['id'] ?? 0),
        ':start_date' => $filters['start_date'],
        ':end_date' => $filters['end_date'],
    ];

    if ($filters['method'] !== 'all') {
        $query .= ' AND payments.method = :method';
        $params[':method'] = $filters['method'];
    }

    $query .= ' ORDER BY transactions.created_at DESC';

    $stmt = $pdo->prepare($query);
    $stmt->execute(tenant_bind($params, $pdo));
    $transactions = $stmt->fetchAll();

    if (!empty($transactions)) {
        $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
        $transactionIds = array_column($transactions, 'id');
        $metaMap = transaction_meta_bulk($transactionIds);
        $itemParams = [];
        $itemPlaceholders = [];
        foreach (array_values($transactionIds) as $index => $transactionId) {
            $key = ':trx_id_' . $index;
            $itemPlaceholders[] = $key;
            $itemParams[$key] = (int) $transactionId;
        }
        $placeholders = implode(',', $itemPlaceholders);

        $itemsStmt = $pdo->prepare(
            'SELECT transaction_items.transaction_id,
                    transaction_items.' . $qtyColumn . ' AS qty,
                    transaction_items.price,
                    products.name AS product_name
             FROM transaction_items
             INNER JOIN products ON products.id = transaction_items.product_id
             WHERE transaction_items.transaction_id IN (' . $placeholders . ')
             ' . tenant_where_clause($pdo, 'transaction_items', 'transaction_items', 'AND') . '
             ORDER BY transaction_items.transaction_id'
        );
        $itemsStmt->execute(tenant_bind($itemParams, $pdo));
        $items = $itemsStmt->fetchAll();

        $itemsByTransaction = [];
        foreach ($items as $item) {
            $transactionId = (int) $item['transaction_id'];
            $itemsByTransaction[$transactionId][] = [
                'name' => $item['product_name'],
                'qty' => (int) $item['qty'],
                'total' => (float) $item['qty'] * (float) $item['price'],
            ];
        }

        foreach ($transactions as $index => $row) {
            $transactionId = (int) $row['id'];
            $transactions[$index]['items'] = $itemsByTransaction[$transactionId] ?? [];
            if (isset($metaMap[(string) $transactionId]['grand_total'])) {
                $transactions[$index]['total_amount'] = (float) $metaMap[(string) $transactionId]['grand_total'];
            }
            $transactions[$index]['meta'] = $metaMap[(string) $transactionId] ?? null;
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Gagal memuat riwayat transaksi. Silakan coba lagi.';
    $transactions = [];
}

$title = 'Riwayat Transaksi';

require_once __DIR__ . '/../app/views/kasir/history.php';
