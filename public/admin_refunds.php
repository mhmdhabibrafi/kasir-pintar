<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../app/helpers/shift_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';

require_role(['admin', 'bos']);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);
        $amount = max(0.0, (float) ($_POST['amount'] ?? 0));
        $method = (string) ($_POST['method'] ?? 'cash');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $restock = isset($_POST['restock']) ? true : false;

        if ($transactionId <= 0) {
            $errors[] = 'ID transaksi tidak valid.';
        }
        if ($amount <= 0) {
            $errors[] = 'Nominal refund wajib diisi.';
        }
        if (!in_array($method, ['cash', 'qris'], true)) {
            $errors[] = 'Metode refund tidak valid.';
        }
        if ($reason === '') {
            $errors[] = 'Alasan refund wajib diisi.';
        }

        $transaction = null;
        if (empty($errors)) {
            $totalColumn = Transaction::totalColumn($pdo) ?? 'total_amount';
            $stmt = $pdo->prepare(
                'SELECT id, user_id, ' . $totalColumn . ' AS total_amount, created_at
                 FROM transactions
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute([':id' => $transactionId]);
            $transaction = $stmt->fetch();
            if (!$transaction) {
                $errors[] = 'Transaksi tidak ditemukan.';
            }
        }

        if (empty($errors)) {
            $existingRefunds = refund_list(['transaction_id' => $transactionId]);
            $refundedTotal = 0.0;
            foreach ($existingRefunds as $refund) {
                $refundedTotal += (float) ($refund['amount'] ?? 0);
            }
            $transactionTotal = (float) ($transaction['total_amount'] ?? 0);
            $remaining = max(0.0, $transactionTotal - $refundedTotal);
            if ($amount > $remaining) {
                $errors[] = 'Nominal refund melebihi sisa transaksi.';
            }
            if ($restock && abs($amount - $remaining) > 1) {
                $errors[] = 'Restock hanya diperbolehkan untuk refund full transaksi.';
            }
        }

        if (empty($errors)) {
            $refundEntry = [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'method' => $method,
                'reason' => $reason,
                'restock' => $restock,
                'user_id' => (int) (current_user()['id'] ?? 0),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $activeShift = shift_get_active((int) (current_user()['id'] ?? 0));
            if ($activeShift) {
                $refundEntry['shift_id'] = $activeShift['shift_id'] ?? '';
            }

            if ($restock) {
                $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
                $itemsStmt = $pdo->prepare(
                    'SELECT transaction_items.product_id, transaction_items.' . $qtyColumn . ' AS qty
                     FROM transaction_items
                     WHERE transaction_items.transaction_id = :id'
                );
                $itemsStmt->execute([':id' => $transactionId]);
                $items = $itemsStmt->fetchAll();
                $refundEntry['items'] = $items;

                $inventoryItems = inventory_get_all();
                foreach ($items as $item) {
                    $productId = (int) ($item['product_id'] ?? 0);
                    $qty = (int) ($item['qty'] ?? 0);
                    $invItem = $inventoryItems[$productId] ?? null;
                    if (is_array($invItem) && array_key_exists('stock', $invItem) && $invItem['stock'] !== null) {
                        inventory_adjust($productId, $qty, 'refund', 'trx#' . $transactionId, (int) (current_user()['id'] ?? 0));
                    }
                }
            }

            refund_add($refundEntry);
            audit_log('refund_created', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'method' => $method,
                'restock' => $restock,
                'reason' => $reason,
            ]);
            $success = 'Refund berhasil dicatat.';
        }
    }
}

$refunds = refund_list($filters);
$title = 'Refund & Retur';

require_once __DIR__ . '/../app/views/admin/refunds.php';
