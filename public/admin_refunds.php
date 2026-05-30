<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/i18n_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../app/helpers/shift_helper.php';
require_once __DIR__ . '/../app/helpers/transaction_meta_helper.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';

$telegramHelperPath = __DIR__ . '/../app/helpers/telegram_helper.php';
if (is_file($telegramHelperPath)) {
    require_once $telegramHelperPath;
}

require_role(['admin', 'bos']);

$pdo = db();
$errors = [];
$success = '';
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

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
        $errors[] = __('error.invalid_request');
    } elseif (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mencatat refund.';
    } else {
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);
        $amount = max(0.0, (float) ($_POST['amount'] ?? 0));
        $method = (string) ($_POST['method'] ?? 'cash');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $restock = isset($_POST['restock']) ? true : false;
        $userId = (int) (current_user()['id'] ?? 0);

        if ($transactionId <= 0) {
            $errors[] = __('error.invalid_transaction_id');
        }
        if ($amount <= 0) {
            $errors[] = __('error.refund_amount_required');
        }
        if (!in_array($method, ['cash', 'qris'], true)) {
            $errors[] = __('error.invalid_refund_method');
        }
        if ($reason === '') {
            $errors[] = __('error.refund_reason_required');
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $totalColumn = Transaction::totalColumn($pdo) ?? 'total_amount';
                $stmt = $pdo->prepare(
                    'SELECT id, user_id, ' . $totalColumn . ' AS total_amount, created_at
                     FROM transactions
                     WHERE id = :id' . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND') . '
                     LIMIT 1
                     FOR UPDATE'
                );
                $stmt->execute(tenant_bind([':id' => $transactionId], $pdo));
                $transaction = $stmt->fetch();
                if (!$transaction) {
                    $errors[] = __('error.transaction_not_found');
                } else {
                    $sumStmt = $pdo->prepare(
                        'SELECT COALESCE(SUM(amount), 0)
                         FROM refunds
                         WHERE transaction_id = :transaction_id' . tenant_where_clause($pdo, 'refunds', 'refunds', 'AND')
                    );
                    $sumStmt->execute(tenant_bind([':transaction_id' => $transactionId], $pdo));
                    $refundedTotal = (float) $sumStmt->fetchColumn();

                    $transactionTotal = (float) ($transaction['total_amount'] ?? 0);
                    $remaining = max(0.0, $transactionTotal - $refundedTotal);
                    if ($amount > $remaining) {
                        $errors[] = __('error.refund_exceeds_remaining');
                    }
                    if ($restock && abs($amount - $remaining) > 1) {
                        $errors[] = __('error.restock_full_only');
                    }
                }

                if (empty($errors)) {
                    $refundEntry = [
                        'transaction_id' => $transactionId,
                        'amount' => $amount,
                        'method' => $method,
                        'reason' => $reason,
                        'restock' => $restock,
                        'user_id' => $userId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ];

                    $meta = transaction_meta_get($transactionId);
                    $shiftCode = '';
                    if (is_array($meta) && !empty($meta['shift_id'])) {
                        $shiftCode = (string) $meta['shift_id'];
                    }
                    if ($shiftCode === '') {
                        $activeShift = shift_get_active($userId);
                        if ($activeShift) {
                            $shiftCode = (string) ($activeShift['shift_id'] ?? '');
                        }
                    }
                    if ($shiftCode !== '') {
                        $refundEntry['shift_id'] = $shiftCode;
                    }

                    if ($restock) {
                        $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
                        $itemsStmt = $pdo->prepare(
                            'SELECT transaction_items.product_id, transaction_items.' . $qtyColumn . ' AS qty
                             FROM transaction_items
                             WHERE transaction_items.transaction_id = :id' . tenant_where_clause($pdo, 'transaction_items', 'transaction_items', 'AND')
                        );
                        $itemsStmt->execute(tenant_bind([':id' => $transactionId], $pdo));
                        $items = $itemsStmt->fetchAll();
                        $refundEntry['items'] = $items;

                        $inventoryItems = inventory_get_all();
                        foreach ($items as $item) {
                            $productId = (int) ($item['product_id'] ?? 0);
                            $qty = (int) ($item['qty'] ?? 0);
                            $invItem = $inventoryItems[$productId] ?? null;
                            if (is_array($invItem) && array_key_exists('stock', $invItem) && $invItem['stock'] !== null) {
                                inventory_adjust($productId, $qty, 'refund', 'trx#' . $transactionId, $userId);
                            }
                        }
                    }

                    refund_add($refundEntry);
                    $pdo->commit();

                    audit_log('refund_created', [
                        'transaction_id' => $transactionId,
                        'amount' => $amount,
                        'method' => $method,
                        'restock' => $restock,
                        'reason' => $reason,
                    ]);

                    if (function_exists('telegram_can_send') && telegram_can_send('refund')) {
                        $lines = [];
                        $lines[] = 'KASPINDO';
                        $lines[] = 'Refund Tercatat';
                        $lines[] = str_repeat('-', 30);
                        $lines[] = 'Transaksi : #' . $transactionId;
                        $lines[] = 'Nominal   : ' . format_rupiah($amount);
                        $lines[] = 'Metode    : ' . strtoupper($method);
                        $lines[] = 'Restock   : ' . ($restock ? 'Ya' : 'Tidak');
                        $lines[] = 'Alasan    : ' . $reason;
                        $lines[] = 'Petugas   : ' . ((string) (current_user()['name'] ?? '-'));
                        $lines[] = 'Waktu     : ' . date('d/m/Y H:i:s');
                        if (function_exists('telegram_send_message') && function_exists('telegram_escape')) {
                            telegram_send_message('<pre>' . telegram_escape(implode("\n", $lines)) . '</pre>', 'HTML');
                        }
                    }

                    $success = __('success.refund_saved');
                } else {
                    $pdo->rollBack();
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Gagal menyimpan refund. Silakan coba lagi.';
            }
        }
    }
}

$refunds = refund_list($filters);
$title = __('refund.page_title');

require_once __DIR__ . '/../app/views/admin/refunds.php';
