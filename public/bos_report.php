<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';
require_once __DIR__ . '/../app/models/Product.php';

require_role(['bos']);

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

    $summary['qty'] += $qty;
    $summary['sales'] += $sales;
    $summary['profit'] += $profit;
    $method = strtolower((string) ($row['method'] ?? ''));
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

$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
$logoUrl = base_url('assets/images/logo.jpg');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Transaksi</title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #00bf63;
            --bg: #f8fafc;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            color: var(--ink);
            background: var(--bg);
        }
        .report {
            max-width: 980px;
            margin: 24px auto;
            padding: 24px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 16px;
            margin-bottom: 16px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand img {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            object-position: center;
        }
        .brand-title {
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .meta {
            text-align: right;
            color: var(--muted);
            font-size: 13px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .summary-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            background: #fff;
        }
        .summary-card .label {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .summary-card .value {
            font-size: 16px;
            font-weight: 600;
        }
        .actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 12px;
        }
        .btn {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--ink);
            padding: 8px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            padding: 10px 8px;
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 10px 8px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            vertical-align: top;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #ecfeff;
            color: var(--accent);
            font-size: 11px;
            font-weight: 600;
        }
        .text-right {
            text-align: right;
        }
        .muted {
            color: var(--muted);
        }
        @media print {
            body {
                background: #fff;
            }
            .report {
                margin: 0;
                border: none;
                border-radius: 0;
                padding: 0;
            }
            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="report">
        <div class="report-header">
            <div class="brand">
                <img src="<?php echo e($logoUrl); ?>" alt="KASPINDO">
                <div>
                    <div class="brand-title">KASPINDO</div>
                    <div class="muted">Laporan Transaksi</div>
                </div>
            </div>
            <div class="meta">
                <div><?php echo e(date('d M Y')); ?></div>
                <div>Periode: <?php echo e($filters['start_date']); ?> - <?php echo e($filters['end_date']); ?></div>
                <div>Metode: <?php echo e(strtoupper($filters['method'])); ?></div>
            </div>
        </div>

        <div class="summary">
            <div class="summary-card">
                <div class="label">Total Qty</div>
                <div class="value"><?php echo e((string) $summary['qty']); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Penjualan</div>
                <div class="value"><?php echo e(format_rupiah((float) $summary['sales'])); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Refund</div>
                <div class="value"><?php echo e(format_rupiah((float) ($summary['refund'] ?? 0))); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Net Penjualan</div>
                <div class="value"><?php echo e(format_rupiah((float) ($summary['net_sales'] ?? 0))); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Profit</div>
                <div class="value"><?php echo e(format_rupiah((float) $summary['profit'])); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Cash</div>
                <div class="value"><?php echo e(format_rupiah((float) $summary['cash'])); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Total QRIS</div>
                <div class="value"><?php echo e(format_rupiah((float) $summary['qris'])); ?></div>
            </div>
        </div>

        <div class="actions">
            <button class="btn" type="button" onclick="window.print()">Simpan PDF</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Tanggal</th>
                    <th>Kasir</th>
                    <th>Nama Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Harga Satuan</th>
                    <th class="text-right">Harga Jual</th>
                    <th class="text-right">Modal</th>
                    <th class="text-right">Profit</th>
                    <th>Metode</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $qty = (int) ($row['qty'] ?? 0);
                        $price = (float) ($row['price'] ?? 0);
                        $cost = (float) ($row['cost_price'] ?? 0);
                        $sales = $qty * $price;
                        $profit = $sales - ($qty * $cost);
                        ?>
                        <tr>
                            <td class="fw-semibold">#<?php echo (int) $row['id']; ?></td>
                            <td>
                                <div><?php echo e(date('d/m/Y', strtotime((string) $row['created_at']))); ?></div>
                                <div class="muted"><?php echo e(date('H:i', strtotime((string) $row['created_at']))); ?></div>
                            </td>
                            <td><?php echo e($row['cashier'] ?? '-'); ?></td>
                            <td><?php echo e($row['product_name'] ?? '-'); ?></td>
                            <td class="text-right"><?php echo e((string) $qty); ?></td>
                            <td class="text-right"><?php echo e(format_rupiah((float) $price)); ?></td>
                            <td class="text-right"><?php echo e(format_rupiah((float) $sales)); ?></td>
                            <td class="text-right"><?php echo e(format_rupiah((float) ($qty * $cost))); ?></td>
                            <td class="text-right"><?php echo e(format_rupiah((float) $profit)); ?></td>
                            <td><span class="badge"><?php echo e(strtoupper((string) ($row['method'] ?? '-'))); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="muted">Belum ada transaksi.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($autoPrint): ?>
        <script>
            window.addEventListener('load', () => {
                window.print();
            });
        </script>
    <?php endif; ?>
</body>
</html>
