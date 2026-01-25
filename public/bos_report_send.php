<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/telegram_helper.php';
require_once __DIR__ . '/../app/helpers/report_pdf_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';
require_once __DIR__ . '/../app/models/Product.php';

require_role(['bos']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validate($_POST['csrf_token'] ?? null)) {
    security_log('csrf_failed', ['endpoint' => 'bos_report_send']);
    header('Location: ' . base_url('bos.php?sent=0'));
    exit;
}
if (!telegram_is_configured()) {
    security_log('telegram_not_configured', ['endpoint' => 'bos_report_send']);
    header('Location: ' . base_url('bos.php?sent=0'));
    exit;
}

$pdo = db();
$today = date('Y-m-d');
$filters = [
    'start_date' => $_POST['start_date'] ?? $_GET['start_date'] ?? $today,
    'end_date' => $_POST['end_date'] ?? $_GET['end_date'] ?? $today,
    'method' => $_POST['method'] ?? $_GET['method'] ?? 'all',
];

if (!in_array($filters['method'], ['all', 'cash', 'qris'], true)) {
    $filters['method'] = 'all';
}

$qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';
$costColumn = Product::costColumn($pdo);
$selectCost = $costColumn ? 'products.' . $costColumn . ' AS cost_price' : '0 AS cost_price';

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

$summary = [
    'qty' => 0,
    'sales' => 0.0,
    'profit' => 0.0,
    'cash' => 0.0,
    'qris' => 0.0,
];

foreach ($rows as $row) {
    $qty = (int) ($row['qty'] ?? 0);
    $price = (float) ($row['price'] ?? 0);
    $cost = (float) ($row['cost_price'] ?? 0);
    $sales = $qty * $price;
    $profit = $sales - ($qty * $cost);
    $method = strtolower((string) ($row['method'] ?? ''));

    $summary['qty'] += $qty;
    $summary['sales'] += $sales;
    $summary['profit'] += $profit;
    if ($method === 'cash') {
        $summary['cash'] += $sales;
    }
    if ($method === 'qris') {
        $summary['qris'] += $sales;
    }
}

$refundTotal = refund_sum_by_date_range($filters['start_date'], $filters['end_date']);
$refundCash = refund_sum_by_date_range($filters['start_date'], $filters['end_date'], 'cash');
$refundQris = refund_sum_by_date_range($filters['start_date'], $filters['end_date'], 'qris');
$summary['refund'] = $refundTotal;
$summary['net_sales'] = max(0.0, $summary['sales'] - $refundTotal);
$summary['cash'] = max(0.0, $summary['cash'] - $refundCash);
$summary['qris'] = max(0.0, $summary['qris'] - $refundQris);

$pdf = build_bos_report_pdf($rows, $summary, $filters);
$tmp = tempnam(sys_get_temp_dir(), 'bos_report_');
$pdfPath = $tmp . '.pdf';
file_put_contents($pdfPath, $pdf);

$captionLines = [
    'KASIR PINTAR',
    'Laporan Transaksi',
    'Periode: ' . $filters['start_date'] . ' - ' . $filters['end_date'],
    'Metode: ' . strtoupper($filters['method']),
];

$sent = telegram_send_document($pdfPath, implode("\n", $captionLines));
if (!$sent) {
    security_log('telegram_send_failed', ['endpoint' => 'bos_report_send']);
}
@unlink($pdfPath);
if (is_file($tmp)) {
    @unlink($tmp);
}

$status = $sent ? '1' : '0';
$redirect = base_url('bos.php?sent=' . $status);
header('Location: ' . $redirect);
exit;

