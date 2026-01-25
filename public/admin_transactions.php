<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';
require_once __DIR__ . '/../app/models/Product.php';

require_role(['admin']);

$pdo = db();
$errors = [];
$success = '';

$today = date('Y-m-d');
$filters = [
    'start_date' => $_GET['start_date'] ?? $today,
    'end_date' => $_GET['end_date'] ?? $today,
    'method' => $_GET['method'] ?? 'all',
];

if (!in_array($filters['method'], ['all', 'cash', 'qris'], true)) {
    $filters['method'] = 'all';
}

$deleteTransactions = static function (PDO $pdo, array $ids): void {
    if (empty($ids)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM payments WHERE transaction_id IN ($placeholders)")->execute($ids);
    $pdo->prepare("DELETE FROM transaction_items WHERE transaction_id IN ($placeholders)")->execute($ids);
    $pdo->prepare("DELETE FROM transactions WHERE id IN ($placeholders)")->execute($ids);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    $action = $_POST['action'] ?? '';

    try {
        if (!empty($errors)) {
            // Skip destructive actions when CSRF or validation fails.
        } elseif ($action === 'delete_selected') {
            $ids = array_map('intval', $_POST['ids'] ?? []);
            $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));
            $reason = trim((string) ($_POST['void_reason'] ?? ''));

            if (empty($ids)) {
                $errors[] = 'Pilih transaksi yang ingin dihapus.';
            } elseif ($reason === '') {
                $errors[] = 'Alasan penghapusan wajib diisi.';
            } else {
                $pdo->beginTransaction();
                $deleteTransactions($pdo, $ids);
                $pdo->commit();
                $success = 'Transaksi terpilih berhasil dihapus.';
                audit_log('transaction_deleted', ['transaction_ids' => $ids, 'reason' => $reason]);
            }
        } elseif ($action === 'clear_all') {
            $reason = trim((string) ($_POST['void_reason'] ?? ''));
            if ($reason === '') {
                $errors[] = 'Alasan penghapusan wajib diisi.';
            } else {
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM payments');
            $pdo->exec('DELETE FROM transaction_items');
            $pdo->exec('DELETE FROM transactions');
            $pdo->commit();
            $success = 'Semua transaksi berhasil dibersihkan.';
            audit_log('transaction_cleared', ['reason' => $reason]);
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = 'Gagal menghapus transaksi. Silakan coba lagi.';
    }
}

try {
    $totalColumn = Transaction::totalColumn($pdo) ?? 'total_amount';
    $query = 'SELECT transactions.id, transactions.' . $totalColumn . ' AS total_amount, transactions.created_at,
                     users.name AS cashier, payments.method
              FROM transactions
              INNER JOIN users ON users.id = transactions.user_id
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

    $query .= ' ORDER BY transactions.created_at DESC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    $rangeSummary = ['sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0];

    if (!empty($transactions)) {
        $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
        $costColumn = Product::costColumn($pdo);
        $transactionIds = array_column($transactions, 'id');
        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $selectCost = $costColumn ? 'products.' . $costColumn . ' AS cost_price' : '0 AS cost_price';

        $itemsStmt = $pdo->prepare(
            'SELECT transaction_items.transaction_id,
                    transaction_items.' . $qtyColumn . ' AS qty,
                    transaction_items.price,
                    products.name AS product_name,
                    ' . $selectCost . '
             FROM transaction_items
             INNER JOIN products ON products.id = transaction_items.product_id
             WHERE transaction_items.transaction_id IN (' . $placeholders . ')
             ORDER BY transaction_items.transaction_id'
        );
        $itemsStmt->execute($transactionIds);
        $items = $itemsStmt->fetchAll();

        $itemsByTransaction = [];
        $totalsByTransaction = [];
        $itemCountByTransaction = [];

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
                'total' => $lineTotal,
            ];

            if (!isset($totalsByTransaction[$transactionId])) {
                $totalsByTransaction[$transactionId] = ['sales' => 0.0, 'cost' => 0.0];
            }
            $totalsByTransaction[$transactionId]['sales'] += $lineTotal;
            $totalsByTransaction[$transactionId]['cost'] += $lineCost;
            $itemCountByTransaction[$transactionId] = ($itemCountByTransaction[$transactionId] ?? 0) + $qty;
        }

        foreach ($transactions as $index => $row) {
            $transactionId = (int) $row['id'];
            $sales = $totalsByTransaction[$transactionId]['sales'] ?? (float) $row['total_amount'];
            $cost = $totalsByTransaction[$transactionId]['cost'] ?? 0.0;
            $profit = $sales - $cost;

            $transactions[$index]['items'] = $itemsByTransaction[$transactionId] ?? [];
            $transactions[$index]['item_count'] = $itemCountByTransaction[$transactionId] ?? 0;
            $transactions[$index]['total_sales'] = $sales;
            $transactions[$index]['total_cost'] = $cost;
            $transactions[$index]['total_profit'] = $profit;

            $rangeSummary['sales'] += $sales;
            $rangeSummary['cost'] += $cost;
            $rangeSummary['profit'] += $profit;
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Gagal memuat transaksi. Silakan coba lagi.';
    $transactions = [];
    $rangeSummary = ['sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0];
}

$refundTotal = refund_sum_by_date_range($filters['start_date'], $filters['end_date']);
$netSales = max(0.0, (float) $rangeSummary['sales'] - $refundTotal);

$title = 'Kelola Transaksi';
require_once __DIR__ . '/../app/views/admin/transactions.php';
