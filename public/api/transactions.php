<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/api_helper.php';
require_once __DIR__ . '/../../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../../app/helpers/shift_helper.php';
require_once __DIR__ . '/../../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../../app/helpers/transaction_meta_helper.php';
require_once __DIR__ . '/../../app/helpers/user_permission_helper.php';

$telegramHelperPath = __DIR__ . '/../../app/helpers/telegram_helper.php';
if (is_file($telegramHelperPath)) {
    require_once $telegramHelperPath;
}

require_once __DIR__ . '/../../app/models/Product.php';
require_once __DIR__ . '/../../app/models/Transaction.php';
require_once __DIR__ . '/../../app/models/Payment.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$auth = api_require_auth(['admin', 'bos', 'karyawan']);
$user = $auth['user'];
$pdo = db();

if ($method === 'GET') {
    if (!user_can($user, 'access_history')) {
        api_error('Akun ini tidak diizinkan melihat riwayat transaksi.', 403, 'history_access_denied');
    }
    $limit = (int) ($_GET['limit'] ?? 20);
    $limit = max(1, min(50, $limit));
    $totalColumn = Transaction::totalColumn($pdo) ?? 'total';

    $stmt = $pdo->prepare(
        'SELECT transactions.id,
                transactions.created_at,
                transactions.' . $totalColumn . ' AS total,
                COALESCE(payments.method, "") AS payment_method
         FROM transactions
         LEFT JOIN payments ON payments.transaction_id = transactions.id
         WHERE transactions.user_id = :user_id
         ' . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND') . '
         ORDER BY transactions.id DESC
         LIMIT ' . $limit
    );
    $stmt->execute(tenant_bind([':user_id' => (int) $user['id']], $pdo, $user));
    $rows = $stmt->fetchAll();

    $transactions = [];
    foreach ($rows as $row) {
        $transactions[] = [
            'id' => (int) $row['id'],
            'created_at' => (string) ($row['created_at'] ?? ''),
            'total' => (float) ($row['total'] ?? 0),
            'payment_method' => (string) ($row['payment_method'] ?? ''),
        ];
    }

    api_ok(['transactions' => $transactions]);
}

if ($method !== 'POST') {
    api_error('Method tidak diizinkan.', 405, 'method_not_allowed');
}

if (!user_can($user, 'access_cashier')) {
    api_error('Akun ini tidak diizinkan membuat transaksi.', 403, 'cashier_access_denied');
}

$body = api_json_body();
$rawItems = $body['items'] ?? [];
$note = trim((string) ($body['note'] ?? ''));
$paymentMethod = strtolower(trim((string) ($body['payment_method'] ?? 'cash')));

if (!in_array($paymentMethod, ['cash', 'qris'], true)) {
    api_error('Metode pembayaran tidak valid.', 422, 'validation_error');
}
if ($paymentMethod === 'cash' && !user_can($user, 'pay_cash')) {
    api_error('Akun ini tidak diizinkan memproses pembayaran cash.', 403, 'cash_payment_denied');
}
if ($paymentMethod === 'qris' && !user_can($user, 'pay_qris')) {
    api_error('Akun ini tidak diizinkan memproses pembayaran QRIS.', 403, 'qris_payment_denied');
}

if (!is_array($rawItems) || empty($rawItems)) {
    api_error('Items wajib diisi minimal satu produk.', 422, 'validation_error');
}

$selectedItems = [];
foreach ($rawItems as $item) {
    if (!is_array($item)) {
        continue;
    }
    $productId = (int) ($item['product_id'] ?? 0);
    $qty = (int) ($item['qty'] ?? 0);
    if ($productId <= 0 || $qty <= 0) {
        continue;
    }
    $selectedItems[$productId] = ($selectedItems[$productId] ?? 0) + $qty;
}

if (empty($selectedItems)) {
    api_error('Format items tidak valid.', 422, 'validation_error');
}

$activeShift = shift_get_active((int) $user['id']);
if (!$activeShift) {
    api_error('Shift belum dibuka. Buka shift terlebih dahulu.', 409, 'no_active_shift');
}

$products = Product::findByIds($pdo, array_keys($selectedItems));
if (count($products) !== count($selectedItems)) {
    api_error('Ada produk yang tidak ditemukan.', 422, 'validation_error');
}

$stockColumn = Product::stockColumn($pdo);
$inventoryItems = inventory_get_all();
$lineItems = [];
$subtotal = 0.0;

foreach ($selectedItems as $productId => $qty) {
    $product = $products[$productId];
    $price = (float) ($product['price'] ?? 0);
    $lineSubtotal = $price * $qty;
    $inventoryItem = $inventoryItems[$productId] ?? null;
    $inventoryTracked = is_array($inventoryItem) && array_key_exists('stock', $inventoryItem) && $inventoryItem['stock'] !== null;
    $availableStock = $inventoryTracked ? (int) ($inventoryItem['stock'] ?? 0) : (isset($product['stock']) ? (int) $product['stock'] : null);

    if ($availableStock !== null && $availableStock < $qty) {
        api_error('Stok tidak mencukupi untuk produk: ' . ($product['name'] ?? ('#' . $productId)), 409, 'stock_not_enough');
    }

    $lineItems[] = [
        'product_id' => $productId,
        'name' => (string) ($product['name'] ?? ''),
        'qty' => $qty,
        'price' => $price,
        'subtotal' => $lineSubtotal,
        'inventory_tracked' => $inventoryTracked,
    ];
    $subtotal += $lineSubtotal;
}

$grandTotal = round($subtotal, 2);

try {
    $pdo->beginTransaction();

    $transactionId = Transaction::create($pdo, (int) $user['id'], $grandTotal, $note);
    foreach ($lineItems as $item) {
        Transaction::addItem(
            $pdo,
            $transactionId,
            (int) $item['product_id'],
            (int) $item['qty'],
            (float) $item['price'],
            (float) $item['subtotal']
        );
        if (!empty($item['inventory_tracked'])) {
            if (!inventory_reduce((int) $item['product_id'], (int) $item['qty'], 'sale', 'trx#' . $transactionId, (int) $user['id'])) {
                throw new RuntimeException('Stok tidak mencukupi.');
            }
        } elseif ($stockColumn) {
            if (!Product::reduceStock($pdo, (int) $item['product_id'], (int) $item['qty'])) {
                throw new RuntimeException('Stok tidak mencukupi.');
            }
        }
    }

    Payment::create($pdo, $transactionId, $paymentMethod, $grandTotal, null);

    transaction_meta_set($transactionId, [
        'shift_id' => (string) ($activeShift['shift_id'] ?? ''),
        'payment_method' => $paymentMethod,
        'subtotal' => $subtotal,
        'grand_total' => $grandTotal,
        'total_before_rounding' => $grandTotal,
        'items' => $lineItems,
    ]);

    $pdo->commit();

    audit_log('api_transaction_created', [
        'transaction_id' => $transactionId,
        'user_id' => (int) $user['id'],
        'method' => $paymentMethod,
        'total' => $grandTotal,
    ]);
    if (function_exists('telegram_notify_transaction')) {
        telegram_notify_transaction([
            'transaction_id' => $transactionId,
            'cashier' => (string) ($user['name'] ?? '-'),
            'method' => $paymentMethod,
            'total' => $grandTotal,
            'shift_id' => (string) ($activeShift['shift_id'] ?? '-'),
        ]);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_error('Gagal menyimpan transaksi.', 500, 'transaction_failed');
}

api_ok([
    'message' => 'Transaksi berhasil disimpan.',
    'transaction' => [
        'id' => $transactionId,
        'total' => $grandTotal,
        'payment_method' => $paymentMethod,
        'item_count' => array_sum(array_column($lineItems, 'qty')),
        'created_at' => date('Y-m-d H:i:s'),
        'shift_id' => (string) ($activeShift['shift_id'] ?? ''),
        'receipt_url' => base_url('print_receipt.php?id=' . $transactionId . '&autoprint=1'),
        'receipt_bluetooth_url' => base_url('print_receipt.php?id=' . $transactionId . '&bluetooth=1'),
    ],
], 201);
