<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/helpers/store_info_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';
require_once __DIR__ . '/../app/models/Product.php';

require_role(['bos', 'admin']);

$pdo = db();
$today = date('Y-m-d');
$filters = [
    'start_date' => $_GET['start_date'] ?? $today,
    'end_date' => $_GET['end_date'] ?? $today,
    'method' => $_GET['method'] ?? 'all',
];

if (!in_array($filters['method'], ['all', 'cash', 'qris'], true)) {
    $filters['method'] = 'all';
}

$qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
$costColumn = Product::costColumn($pdo);
$selectCost = $costColumn ? 'products.' . $costColumn . ' AS cost_price' : '0 AS cost_price';
$tenantFilters = tenant_multi_where_clause($pdo, [
    'transactions' => 'transactions',
    'transaction_items' => 'transaction_items',
    'products' => 'products',
]);

$query = 'SELECT transactions.id,
                 transactions.created_at,
                 users.name AS cashier,
                 payments.method,
                 transaction_items.' . $qtyColumn . ' AS qty,
                 transaction_items.price,
                 products.name AS product_name,
                 ' . $selectCost . '
          FROM transactions
          INNER JOIN users ON users.id = transactions.user_id
          LEFT JOIN payments ON payments.transaction_id = transactions.id
          INNER JOIN transaction_items ON transaction_items.transaction_id = transactions.id
          INNER JOIN products ON products.id = transaction_items.product_id
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

$query .= ' ORDER BY transactions.created_at DESC, transactions.id DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params + $tenantFilters['params']);
$rows = $stmt->fetchAll();
$storeInfo = store_info_get();
$user = current_user();
$printedAt = date('Y-m-d H:i:s');
$printedBy = trim((string) ($user['name'] ?? $user['username'] ?? '-'));

$safeText = static function ($value): string {
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    return preg_match('/^[=+\-@\t\r\n]/', $text) === 1 ? "'" . $text : $text;
};

$money = static function ($value): string {
    return number_format((float) $value, 0, '.', '');
};

$filename = sprintf(
    'laporan_transaksi_%s_%s.csv',
    str_replace('-', '', $filters['start_date']),
    str_replace('-', '', $filters['end_date'])
);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
fputcsv($output, ['Laporan', 'Laporan Transaksi']);
fputcsv($output, ['Store', $safeText($storeInfo['store_name'] ?? 'KASPINDO')]);
if (!empty($storeInfo['store_code'])) {
    fputcsv($output, ['Kode Store', $safeText($storeInfo['store_code'])]);
}
fputcsv($output, ['Periode', $filters['start_date'] . ' s/d ' . $filters['end_date']]);
fputcsv($output, ['Metode', strtoupper((string) $filters['method'])]);
fputcsv($output, ['Mata Uang', 'IDR']);
fputcsv($output, ['Dicetak Pada', $printedAt]);
fputcsv($output, ['Dicetak Oleh', $safeText($printedBy)]);
fputcsv($output, []);
fputcsv($output, ['DATA TRANSAKSI']);
fputcsv($output, ['Transaction ID', 'Tanggal Waktu', 'Kasir', 'Nama Item', 'Qty', 'Harga Satuan (IDR)', 'Harga Jual (IDR)', 'Modal (IDR)', 'Profit (IDR)', 'Metode Pembayaran']);

foreach ($rows as $row) {
    $qty = (int) ($row['qty'] ?? 0);
    $price = (float) ($row['price'] ?? 0);
    $cost = (float) ($row['cost_price'] ?? 0);
    $sales = $qty * $price;
    $profit = $sales - ($qty * $cost);

    fputcsv($output, [
        (string) $row['id'],
        date('Y-m-d H:i:s', strtotime((string) $row['created_at'])),
        $safeText($row['cashier'] ?? '-'),
        $safeText($row['product_name'] ?? '-'),
        (string) $qty,
        $money($price),
        $money($sales),
        $money($qty * $cost),
        $money($profit),
        $safeText(strtoupper((string) ($row['method'] ?? '-'))),
    ]);
}

fclose($output);
exit;
