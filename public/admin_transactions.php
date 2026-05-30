<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/helpers/transaction_meta_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Customer.php';

require_role(['admin']);

$pdo = db();
$currentUser = current_user() ?? [];
if (function_exists('ensure_update_schema')) {
    ensure_update_schema();
}
$errors = [];
$success = '';

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
    'method' => (string) ($_GET['method'] ?? 'all'),
    'q' => trim((string) ($_GET['q'] ?? '')),
];

if ($filters['start_date'] > $filters['end_date']) {
    [$filters['start_date'], $filters['end_date']] = [$filters['end_date'], $filters['start_date']];
}

if (!in_array($filters['method'], ['all', 'cash', 'qris'], true)) {
    $filters['method'] = 'all';
}

$deleteTransactions = static function (PDO $pdo, array $ids, int $actorId, string $reason = ''): void {
    if (empty($ids)) {
        return;
    }

    $paramsById = [];
    $placeholdersList = [];
    foreach (array_values($ids) as $index => $id) {
        $key = ':trx_id_' . $index;
        $placeholdersList[] = $key;
        $paramsById[$key] = (int) $id;
    }
    $placeholders = implode(',', $placeholdersList);
    $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'qty';
    $stockColumn = Product::stockColumn($pdo);

    $soldQtyStmt = $pdo->prepare(
        "SELECT product_id, COALESCE(SUM($qtyColumn), 0) AS total_qty
         FROM transaction_items
         WHERE transaction_id IN ($placeholders)
           " . tenant_where_clause($pdo, 'transaction_items', 'transaction_items', 'AND') . "
         GROUP BY product_id"
    );
    $soldQtyStmt->execute(tenant_bind($paramsById, $pdo));
    $soldQtyByProduct = [];
    foreach ($soldQtyStmt->fetchAll() as $row) {
        $soldQtyByProduct[(int) ($row['product_id'] ?? 0)] = (int) ($row['total_qty'] ?? 0);
    }

    $refundIdsStmt = $pdo->prepare("SELECT id FROM refunds WHERE transaction_id IN ($placeholders)" . tenant_where_clause($pdo, 'refunds', 'refunds', 'AND'));
    $refundIdsStmt->execute(tenant_bind($paramsById, $pdo));
    $refundIds = array_map('intval', $refundIdsStmt->fetchAll(PDO::FETCH_COLUMN));

    $restockedQtyByProduct = [];
    if (!empty($refundIds)) {
        $refundParams = [];
        $refundPlaceholdersList = [];
        foreach (array_values($refundIds) as $index => $refundId) {
            $key = ':refund_id_' . $index;
            $refundPlaceholdersList[] = $key;
            $refundParams[$key] = (int) $refundId;
        }
        $refundPlaceholders = implode(',', $refundPlaceholdersList);
        $restockedStmt = $pdo->prepare(
            "SELECT refund_items.product_id, COALESCE(SUM(refund_items.qty), 0) AS total_qty
             FROM refund_items
             INNER JOIN refunds ON refunds.id = refund_items.refund_id
             WHERE refund_items.refund_id IN ($refundPlaceholders)
               AND refunds.restock = 1
               " . tenant_where_clause($pdo, 'refund_items', 'refund_items', 'AND') . "
             GROUP BY refund_items.product_id"
        );
        $restockedStmt->execute(tenant_bind($refundParams, $pdo));
        foreach ($restockedStmt->fetchAll() as $row) {
            $restockedQtyByProduct[(int) ($row['product_id'] ?? 0)] = (int) ($row['total_qty'] ?? 0);
        }
    }

    foreach ($soldQtyByProduct as $productId => $soldQty) {
        $restockedQty = $restockedQtyByProduct[$productId] ?? 0;
        $restoreQty = max(0, $soldQty - $restockedQty);
        if ($restoreQty <= 0) {
            continue;
        }

        $inventoryItem = inventory_get_item($productId, $pdo);
        $trackedInventory = is_array($inventoryItem)
            && array_key_exists('stock', $inventoryItem)
            && $inventoryItem['stock'] !== null;

        if ($trackedInventory) {
            inventory_adjust(
                $productId,
                $restoreQty,
                'transaction_delete_restore',
                'trx#' . implode(',', $ids),
                $actorId,
                $reason !== '' ? $reason : 'restore stok setelah hapus transaksi',
                $pdo
            );
        } elseif ($stockColumn) {
            Product::increaseStock($pdo, $productId, $restoreQty);
        }
    }

    foreach ($ids as $transactionId) {
        Customer::deleteTransactionLogs($pdo, (int) $transactionId);
    }

    if (!empty($refundIds)) {
        $refundParams = [];
        $refundPlaceholdersList = [];
        foreach (array_values($refundIds) as $index => $refundId) {
            $key = ':refund_delete_id_' . $index;
            $refundPlaceholdersList[] = $key;
            $refundParams[$key] = (int) $refundId;
        }
        $refundPlaceholders = implode(',', $refundPlaceholdersList);
        $pdo->prepare("DELETE FROM refund_items WHERE refund_id IN ($refundPlaceholders)" . tenant_where_clause($pdo, 'refund_items', 'refund_items', 'AND'))->execute(tenant_bind($refundParams, $pdo));
    }

    $pdo->prepare("DELETE FROM refunds WHERE transaction_id IN ($placeholders)" . tenant_where_clause($pdo, 'refunds', 'refunds', 'AND'))->execute(tenant_bind($paramsById, $pdo));
    $pdo->prepare("DELETE FROM transaction_meta WHERE transaction_id IN ($placeholders)" . tenant_where_clause($pdo, 'transaction_meta', 'transaction_meta', 'AND'))->execute(tenant_bind($paramsById, $pdo));
    $pdo->prepare("DELETE FROM payments WHERE transaction_id IN ($placeholders)" . tenant_where_clause($pdo, 'payments', 'payments', 'AND'))->execute(tenant_bind($paramsById, $pdo));
    $pdo->prepare("DELETE FROM transaction_items WHERE transaction_id IN ($placeholders)" . tenant_where_clause($pdo, 'transaction_items', 'transaction_items', 'AND'))->execute(tenant_bind($paramsById, $pdo));
    $pdo->prepare("DELETE FROM transactions WHERE id IN ($placeholders)" . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND'))->execute(tenant_bind($paramsById, $pdo));
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
                $deleteTransactions($pdo, $ids, (int) ($currentUser['id'] ?? 0), $reason);
                $pdo->commit();
                $success = 'Transaksi terpilih berhasil dihapus.';
                audit_log('transaction_deleted', ['transaction_ids' => $ids, 'reason' => $reason]);
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[admin_transactions] delete failed: ' . $e->getMessage());
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
              WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date' . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND');
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
    $stmt->execute(tenant_bind($params, $pdo));
    $transactions = $stmt->fetchAll();

    if (!empty($transactions)) {
        $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
        $costColumn = Product::costColumn($pdo);
        $transactionIds = array_column($transactions, 'id');
        $metaMap = transaction_meta_bulk($transactionIds);
        $itemParams = [];
        $itemPlaceholders = [];
        foreach (array_values($transactionIds) as $index => $transactionId) {
            $key = ':item_trx_id_' . $index;
            $itemPlaceholders[] = $key;
            $itemParams[$key] = (int) $transactionId;
        }
        $placeholders = implode(',', $itemPlaceholders);
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
             ' . tenant_where_clause($pdo, 'transaction_items', 'transaction_items', 'AND') . '
             ORDER BY transaction_items.transaction_id'
        );
        $itemsStmt->execute(tenant_bind($itemParams, $pdo));
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
            $transactions[$index]['meta'] = $metaMap[(string) $transactionId] ?? null;
        }
    }

    if ($filters['q'] !== '') {
        $needle = strtolower($filters['q']);
        $transactions = array_values(array_filter(
            $transactions,
            static function (array $row) use ($needle): bool {
                $itemNames = array_map(
                    static fn (array $item): string => (string) ($item['name'] ?? ''),
                    $row['items'] ?? []
                );
                $haystack = strtolower(implode(' ', array_filter([
                    '#' . (string) ($row['id'] ?? ''),
                    (string) ($row['cashier'] ?? ''),
                    (string) (($row['meta']['customer']['name'] ?? '')),
                    implode(' ', $itemNames),
                    (string) ($row['method'] ?? ''),
                ])));

                return str_contains($haystack, $needle);
            }
        ));
    }

    $rangeSummary = ['sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0];
    foreach ($transactions as $row) {
        $rangeSummary['sales'] += (float) ($row['total_sales'] ?? 0);
        $rangeSummary['cost'] += (float) ($row['total_cost'] ?? 0);
        $rangeSummary['profit'] += (float) ($row['total_profit'] ?? 0);
    }
} catch (Throwable $e) {
    $errors[] = 'Gagal memuat transaksi. Silakan coba lagi.';
    $transactions = [];
    $rangeSummary = ['sales' => 0.0, 'cost' => 0.0, 'profit' => 0.0];
}

$refundTotal = refund_sum_by_date_range($filters['start_date'], $filters['end_date']);
$netSales = max(0.0, (float) $rangeSummary['sales'] - $refundTotal);
$transactionSummary = [
    'visible_total' => count($transactions),
    'gross_sales' => (float) ($rangeSummary['sales'] ?? 0),
    'refund_total' => (float) $refundTotal,
    'net_sales' => (float) $netSales,
    'profit' => (float) ($rangeSummary['profit'] ?? 0),
    'average_ticket' => count($transactions) > 0 ? ($netSales / count($transactions)) : 0.0,
];

$title = 'Kelola Transaksi';
require_once __DIR__ . '/../app/views/admin/transactions.php';
