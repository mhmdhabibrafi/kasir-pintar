<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
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

$query = 'SELECT transactions.id,
                 transactions.created_at,
                 payments.method,
                 transaction_items.' . $qtyColumn . ' AS qty,
                 transaction_items.price,
                 products.name AS product_name,
                 ' . $selectCost . '
          FROM transactions
          LEFT JOIN payments ON payments.transaction_id = transactions.id
          INNER JOIN transaction_items ON transaction_items.transaction_id = transactions.id
          INNER JOIN products ON products.id = transaction_items.product_id
          WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date';

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
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = sprintf(
    'laporan_transaksi_%s_%s.xls',
    str_replace('-', '', $filters['start_date']),
    str_replace('-', '', $filters['end_date'])
);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
fputcsv($output, ['Nomor', 'Tanggal', 'Nama Item', 'Harga Jual', 'Profit', 'Metode Pembayaran']);

foreach ($rows as $row) {
    $qty = (int) ($row['qty'] ?? 0);
    $price = (float) ($row['price'] ?? 0);
    $cost = (float) ($row['cost_price'] ?? 0);
    $sales = $qty * $price;
    $profit = $sales - ($qty * $cost);

    fputcsv($output, [
        (string) $row['id'],
        date('d/m/Y H:i', strtotime((string) $row['created_at'])),
        $row['product_name'] ?? '-',
        number_format($sales, 0, ',', '.'),
        number_format($profit, 0, ',', '.'),
        strtoupper((string) ($row['method'] ?? '-')),
    ]);
}

fclose($output);
exit;
