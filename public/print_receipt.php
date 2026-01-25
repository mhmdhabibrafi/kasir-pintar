<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/transaction_meta_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';

require_role(['karyawan', 'bos', 'admin']);

$pdo = db();
$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$paper = ($_GET['paper'] ?? '80') === '58' ? '58mm' : '80mm';

$totalColumn = Transaction::totalColumn($pdo) ?? 'total_amount';
$qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';

$noteColumn = Transaction::noteColumn($pdo);
$noteSelect = $noteColumn ? 'transactions.' . $noteColumn . ' AS note' : 'NULL AS note';
$stmt = $pdo->prepare(
    'SELECT transactions.id, transactions.user_id, transactions.' . $totalColumn . ' AS total_amount,
            transactions.created_at, payments.method, ' . $noteSelect . '
     FROM transactions
     LEFT JOIN payments ON payments.transaction_id = transactions.id
     WHERE transactions.id = :id
     LIMIT 1'
);
$stmt->execute([':id' => $id]);
$transaction = $stmt->fetch();

$role = $user['role'] ?? '';
if (
    !$transaction
    || ($role === 'karyawan' && (int) $transaction['user_id'] !== (int) ($user['id'] ?? 0))
) {
    http_response_code(404);
    echo 'Struk tidak ditemukan.';
    exit;
}

$itemsStmt = $pdo->prepare(
    'SELECT products.name AS product_name,
            transaction_items.' . $qtyColumn . ' AS qty,
            transaction_items.price
     FROM transaction_items
     INNER JOIN products ON products.id = transaction_items.product_id
     WHERE transaction_items.transaction_id = :id'
);
$itemsStmt->execute([':id' => $id]);
$items = $itemsStmt->fetchAll();

$total = (float) $transaction['total_amount'];
$note = trim((string) ($transaction['note'] ?? ''));
$meta = transaction_meta_get($id);
if (is_array($meta) && isset($meta['grand_total'])) {
    $total = (float) $meta['grand_total'];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk #<?php echo (int) $transaction['id']; ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 16px;
        }
        .receipt {
            max-width: <?php echo e($paper); ?>;
            margin: 0 auto;
        }
        .receipt-logo {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            object-fit: contain;
            object-position: center;
            margin: 0 auto 6px;
            display: block;
        }
        .center {
            text-align: center;
        }
        .line {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }
        .item-name {
            flex: 1;
        }
        .muted {
            color: #555;
        }
        .ig {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 11px;
        }
        .ig svg {
            width: 14px;
            height: 14px;
        }
        .spaced {
            margin-top: 6px;
        }
        .item-row {
            margin: 4px 0;
        }
        @media print {
            @page {
                size: <?php echo e($paper); ?> auto;
                margin: 0;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center">
            <img class="receipt-logo" src="<?php echo e(base_url('assets/images/logo.jpg')); ?>" alt="KASIR PINTAR">
            <strong>KASIR PINTAR</strong><br>
            <span class="muted">Struk Pembayaran</span>
        </div>
        <div class="line"></div>
        <div class="row">
            <span>No. #<?php echo (int) $transaction['id']; ?></span>
            <span><?php echo e($transaction['created_at']); ?></span>
        </div>
        <div class="row">
            <span>Metode</span>
            <span><?php echo e(strtoupper($transaction['method'] ?? '-')); ?></span>
        </div>
        <div class="line"></div>
        <?php
            $receiptItems = [];
            if (is_array($meta) && !empty($meta['items'])) {
                foreach ($meta['items'] as $metaItem) {
                    $receiptItems[] = [
                        'name' => $metaItem['name'] ?? '-',
                        'qty' => (int) ($metaItem['qty'] ?? 0),
                        'price' => (float) ($metaItem['price'] ?? 0),
                        'discount' => (float) ($metaItem['discount'] ?? 0),
                        'total' => (float) ($metaItem['total'] ?? 0),
                    ];
                }
            } else {
                foreach ($items as $item) {
                    $qty = (int) $item['qty'];
                    $price = (float) $item['price'];
                    $receiptItems[] = [
                        'name' => $item['product_name'] ?? '-',
                        'qty' => $qty,
                        'price' => $price,
                        'discount' => 0.0,
                        'total' => $qty * $price,
                    ];
                }
            }
        ?>
        <?php foreach ($receiptItems as $item): ?>
            <div class="row item-row">
                <div class="item-name"><?php echo e($item['name']); ?></div>
                <div class="muted"><?php echo (int) $item['qty']; ?>x</div>
                <div><?php echo e(format_rupiah((float) $item['total'])); ?></div>
            </div>
            <?php if (!empty($item['discount'])): ?>
                <div class="row item-row muted">
                    <div class="item-name">Diskon Item</div>
                    <div></div>
                    <div>-<?php echo e(format_rupiah((float) $item['discount'])); ?></div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="line"></div>
        <?php if (is_array($meta)): ?>
            <?php if (!empty($meta['item_discount_total'])): ?>
                <div class="row">
                    <span>Diskon Item</span>
                    <span>-<?php echo e(format_rupiah((float) $meta['item_discount_total'])); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($meta['order_discount']['amount'])): ?>
                <div class="row">
                    <span>Diskon Order</span>
                    <span>-<?php echo e(format_rupiah((float) $meta['order_discount']['amount'])); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($meta['voucher']['amount'])): ?>
                <div class="row">
                    <span>Voucher <?php echo e($meta['voucher']['code'] ?? ''); ?></span>
                    <span>-<?php echo e(format_rupiah((float) $meta['voucher']['amount'])); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($meta['tax']['amount'])): ?>
                <div class="row">
                    <span>Pajak</span>
                    <span><?php echo e(format_rupiah((float) $meta['tax']['amount'])); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($meta['service']['amount'])): ?>
                <div class="row">
                    <span>Service</span>
                    <span><?php echo e(format_rupiah((float) $meta['service']['amount'])); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($meta['rounding']['amount'])): ?>
                <div class="row">
                    <span>Pembulatan</span>
                    <span><?php echo e(format_rupiah((float) $meta['rounding']['amount'])); ?></span>
                </div>
            <?php endif; ?>
            <div class="line"></div>
        <?php endif; ?>
        <div class="row">
            <strong>Total</strong>
            <strong><?php echo e(format_rupiah($total)); ?></strong>
        </div>
        <?php if ($note !== ''): ?>
            <div class="line"></div>
            <div class="muted">Catatan:</div>
            <div class="spaced"><?php echo e($note); ?></div>
        <?php endif; ?>
        <div class="line"></div>
        <div class="ig">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="4" y="4" width="16" height="16" rx="5" ry="5" fill="none" stroke="#000" stroke-width="1.6"/>
                <circle cx="12" cy="12" r="3.5" fill="none" stroke="#000" stroke-width="1.6"/>
                <circle cx="17.2" cy="6.8" r="1" fill="#000"/>
            </svg>
            <span>@kasirpintar</span>
        </div>
        <div class="center muted">Terima kasih!</div>
    </div>
    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>

