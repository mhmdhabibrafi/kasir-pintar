<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/store_info_helper.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/helpers/transaction_meta_helper.php';
require_once __DIR__ . '/../app/helpers/user_permission_helper.php';
require_once __DIR__ . '/../app/models/Transaction.php';

require_role(['karyawan', 'bos', 'admin']);

$pdo = db();
$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$bluetoothMode = ($_GET['bluetooth'] ?? '0') === '1';
user_permission_guard(
    $user,
    $bluetoothMode ? 'bluetooth_print' : 'print_receipt',
    $bluetoothMode
        ? 'Admin menonaktifkan cetak struk Bluetooth untuk akun ini.'
        : 'Admin menonaktifkan akses cetak struk untuk akun ini.'
);
$requestedPaper = (string) ($_GET['paper'] ?? '');
$paper = $requestedPaper === '58' || ($requestedPaper === '' && $bluetoothMode) ? '58mm' : '80mm';
$autoPrint = ($_GET['autoprint'] ?? '0') === '1';

$totalColumn = Transaction::totalColumn($pdo) ?? 'total_amount';
$qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'quantity';

$noteColumn = Transaction::noteColumn($pdo);
$noteSelect = $noteColumn ? 'transactions.' . $noteColumn . ' AS note' : 'NULL AS note';
$stmt = $pdo->prepare(
    'SELECT transactions.id, transactions.user_id, transactions.' . $totalColumn . ' AS total_amount,
            transactions.created_at, payments.method, users.name AS cashier_name, ' . $noteSelect . '
     FROM transactions
     LEFT JOIN payments ON payments.transaction_id = transactions.id
     LEFT JOIN users ON users.id = transactions.user_id
     WHERE transactions.id = :id' . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND') . '
     LIMIT 1'
);
$stmt->execute(tenant_bind([':id' => $id], $pdo));
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
     WHERE transaction_items.transaction_id = :id' . tenant_where_clause($pdo, 'transaction_items', 'transaction_items', 'AND')
);
$itemsStmt->execute(tenant_bind([':id' => $id], $pdo));
$items = $itemsStmt->fetchAll();

$total = (float) $transaction['total_amount'];
$note = trim((string) ($transaction['note'] ?? ''));
$meta = transaction_meta_get($id);
$customerMeta = is_array($meta) ? ($meta['customer'] ?? null) : null;
if (is_array($meta) && isset($meta['grand_total'])) {
    $total = (float) $meta['grand_total'];
}

$storeInfo = store_info_get();
$brandName = 'KASPINDO';
$storeName = (string) ($storeInfo['store_name'] ?? 'KASPINDO');
$storeTagline = (string) ($storeInfo['store_tagline'] ?? 'Struk Pembayaran');
$storeAddress = store_info_compose_address($storeInfo);
$storePhone = trim((string) ($storeInfo['store_phone'] ?? ''));
$storeWhatsapp = trim((string) ($storeInfo['store_whatsapp'] ?? ''));
$storeEmail = trim((string) ($storeInfo['store_email'] ?? ''));
$storeInstagram = trim((string) ($storeInfo['store_instagram'] ?? ''));
$businessHours = trim((string) ($storeInfo['business_hours'] ?? ''));
$receiptFooter = trim((string) ($storeInfo['receipt_footer'] ?? 'Terima kasih!'));
$storeInformationLines = [];
if ($storeAddress !== '') {
    $storeInformationLines[] = $storeAddress;
}
if ($storePhone !== '') {
    $storeInformationLines[] = 'No. Telp ' . $storePhone;
}
if ($storeWhatsapp !== '' && $storeWhatsapp !== $storePhone) {
    $storeInformationLines[] = $storeWhatsapp;
}
if ($storeEmail !== '') {
    $storeInformationLines[] = $storeEmail;
}
if ($storeInstagram !== '') {
    $storeInformationLines[] = $storeInstagram;
}
if ($businessHours !== '') {
    $storeInformationLines[] = 'Jam: ' . $businessHours;
}
$paymentMethodLabel = strtoupper((string) ($transaction['method'] ?? '-'));
$paymentMethodText = strtolower((string) ($transaction['method'] ?? '')) === 'cash' ? 'Cash' : $paymentMethodLabel;
$createdTimestamp = strtotime((string) ($transaction['created_at'] ?? '')) ?: time();
$receiptDate = date('Y-m-d', $createdTimestamp);
$receiptTime = date('H:i:s', $createdTimestamp);
$cashierName = trim((string) ($transaction['cashier_name'] ?? ''));
$cashierName = $cashierName !== '' ? $cashierName : (string) ($user['name'] ?? '-');
$paymentDetailRows = [];
if (is_array($meta) && (($meta['payment_method'] ?? '') === 'cash')) {
    $paymentDetailRows[] = ['label' => 'Tunai', 'value' => format_rupiah((float) ($meta['cash_received'] ?? 0))];
    $paymentDetailRows[] = ['label' => 'Kembalian', 'value' => format_rupiah((float) ($meta['cash_change'] ?? 0))];
} elseif (is_array($meta) && (($meta['payment_method'] ?? '') === 'qris')) {
    $paymentDetailRows[] = ['label' => 'Status QRIS', 'value' => 'Terverifikasi'];
}

$summaryRows = [];
if (is_array($meta)) {
    if (!empty($meta['item_discount_total'])) {
        $summaryRows[] = ['label' => 'Diskon Item', 'value' => '-' . format_rupiah((float) $meta['item_discount_total'])];
    }
    if (!empty($meta['order_discount']['amount'])) {
        $summaryRows[] = ['label' => 'Diskon Order', 'value' => '-' . format_rupiah((float) $meta['order_discount']['amount'])];
    }
    if (!empty($meta['voucher']['amount'])) {
        $summaryRows[] = [
            'label' => 'Voucher ' . (string) ($meta['voucher']['code'] ?? ''),
            'value' => '-' . format_rupiah((float) $meta['voucher']['amount']),
        ];
    }
    if (!empty($meta['tax']['amount'])) {
        $summaryRows[] = ['label' => 'Pajak', 'value' => format_rupiah((float) $meta['tax']['amount'])];
    }
    if (!empty($meta['service']['amount'])) {
        $summaryRows[] = ['label' => 'Service', 'value' => format_rupiah((float) $meta['service']['amount'])];
    }
    if (!empty($meta['rounding']['amount'])) {
        $summaryRows[] = ['label' => 'Pembulatan', 'value' => format_rupiah((float) $meta['rounding']['amount'])];
    }
}

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
$subtotal = is_array($meta) && isset($meta['subtotal'])
    ? (float) $meta['subtotal']
    : array_reduce($receiptItems, static fn (float $carry, array $item): float => $carry + ((int) $item['qty'] * (float) $item['price']), 0.0);
$totalQty = array_reduce($receiptItems, static fn (int $carry, array $item): int => $carry + (int) ($item['qty'] ?? 0), 0);
$cashReceived = is_array($meta) ? (float) ($meta['cash_received'] ?? $total) : $total;
$cashChange = is_array($meta) ? (float) ($meta['cash_change'] ?? 0) : 0.0;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KASPINDO | Struk #<?php echo (int) $transaction['id']; ?></title>
    <link rel="icon" type="image/jpeg" href="<?php echo e(base_url('assets/images/logo.jpg')); ?>">
    <link rel="apple-touch-icon" href="<?php echo e(base_url('assets/images/logo.jpg')); ?>">
    <link rel="manifest" href="<?php echo e(base_url('manifest.webmanifest')); ?>">
    <meta name="theme-color" content="#00bf63">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        :root {
            --receipt-bg: #f5f8fb;
            --receipt-surface: #ffffff;
            --receipt-border: #d6e2e6;
            --receipt-text: #0f172a;
            --receipt-muted: #526072;
            --receipt-brand: #00bf63;
            --receipt-brand-dark: #009a4f;
        }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            font-size: 13px;
            color: var(--receipt-text);
            margin: 0;
            padding: 20px;
            background: #f2fff8;
        }
        .receipt {
            width: <?php echo e($paper); ?>;
            max-width: 100%;
            box-sizing: border-box;
            margin: 0 auto;
            font-family: 'IBM Plex Mono', 'Courier New', monospace;
            font-size: <?php echo $paper === '58mm' ? '10.5px' : '11.5px'; ?>;
            line-height: 1.28;
            color: #000;
            background: #fff;
            border: 0;
            border-radius: 0;
            padding: <?php echo $paper === '58mm' ? '4mm 3mm 5mm' : '5mm 5mm 6mm'; ?>;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
        }
        .receipt-toolbar {
            max-width: min(860px, calc(100vw - 24px));
            margin: 0 auto 18px;
        }
        .receipt-toolbar-card {
            border: 1px solid var(--receipt-border);
            border-radius: 24px;
            background: rgba(255,255,255,.96);
            padding: 18px;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(10px);
        }
        .receipt-toolbar-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .receipt-toolbar-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(0, 191, 99, 0.10);
            color: var(--receipt-brand-dark);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .receipt-toolbar-title {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0 0 6px;
        }
        .receipt-toolbar-copy {
            color: var(--receipt-muted);
            max-width: 680px;
            line-height: 1.6;
        }
        .receipt-toolbar-side {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid var(--receipt-border);
            color: var(--receipt-muted);
            font-weight: 600;
        }
        .receipt-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        .receipt-btn {
            border: 1px solid transparent;
            border-radius: 14px;
            padding: 11px 16px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: .18s ease;
        }
        .receipt-btn-primary {
            background: var(--receipt-brand);
            color: #fff;
            box-shadow: 0 14px 28px rgba(0, 191, 99, 0.22);
        }
        .receipt-btn-ghost {
            background: #f8fafc;
            color: var(--receipt-text);
            border-color: var(--receipt-border);
        }
        .receipt-btn:hover {
            transform: translateY(-1px);
        }
        .receipt-bluetooth-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 10px;
        }
        .receipt-serial-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 12px;
        }
        .receipt-bluetooth-grid label,
        .receipt-serial-grid label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--receipt-muted);
            margin-bottom: 6px;
        }
        .receipt-bluetooth-grid textarea,
        .receipt-serial-grid select {
            width: 100%;
            border: 1px solid var(--receipt-border);
            border-radius: 14px;
            padding: 10px 12px;
            font: inherit;
            background: #fff;
        }
        .receipt-bluetooth-grid textarea {
            min-height: 74px;
            resize: vertical;
            font-family: 'IBM Plex Mono', 'Courier New', monospace;
        }
        .receipt-serial-grid select {
            min-height: 46px;
        }
        .receipt-status {
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid var(--receipt-border);
            padding: 12px 14px;
            font-size: 12px;
            color: var(--receipt-muted);
            margin-top: 12px;
            white-space: pre-line;
            line-height: 1.6;
        }
        .receipt-status.error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }
        .receipt-status.success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }
        .receipt-help {
            margin-top: 12px;
            font-size: 12px;
            color: var(--receipt-muted);
            line-height: 1.5;
        }
        .receipt-logo {
            width: <?php echo $paper === '58mm' ? '30px' : '38px'; ?>;
            height: <?php echo $paper === '58mm' ? '30px' : '38px'; ?>;
            object-fit: contain;
            object-position: center;
            margin: 0 auto 6px;
            display: block;
        }
        .store-name {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            font-size: <?php echo $paper === '58mm' ? '13px' : '15px'; ?>;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 3px;
        }
        .store-info {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            font-size: <?php echo $paper === '58mm' ? '9px' : '10px'; ?>;
            line-height: 1.28;
        }
        .center {
            text-align: center;
        }
        .line {
            border-top: 1px dashed #000;
            margin: 7px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: baseline;
            line-height: 1.35;
            margin: 2px 0;
        }
        .row span:first-child {
            min-width: 0;
        }
        .row span:last-child {
            flex: 0 0 auto;
            max-width: 56%;
            text-align: right;
            overflow-wrap: anywhere;
        }
        .item-name {
            flex: 1;
        }
        .receipt-info-grid {
            display: grid;
            grid-template-columns: max-content minmax(0, 1fr);
            gap: 2px 8px;
            line-height: 1.35;
        }
        .receipt-info-grid span:nth-child(odd) {
            color: #444;
        }
        .receipt-info-grid span:nth-child(even) {
            text-align: right;
            overflow-wrap: anywhere;
        }
        .receipt-item {
            margin: 6px 0;
        }
        .receipt-item-title {
            font-weight: 700;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }
        .receipt-item-line {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            padding-left: 12px;
            line-height: 1.35;
        }
        .receipt-item-line span:last-child {
            text-align: right;
            white-space: nowrap;
        }
        .receipt-total-row {
            font-weight: 700;
            font-size: <?php echo $paper === '58mm' ? '11.5px' : '13px'; ?>;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            margin: 5px 0;
        }
        .receipt-thanks {
            display: inline-block;
            padding: 0;
            border: 0;
            color: #111;
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            font-size: <?php echo $paper === '58mm' ? '10px' : '11px'; ?>;
            font-weight: 600;
            line-height: 1.35;
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
                background: #fff;
            }
            .no-print {
                display: none !important;
            }
            .receipt {
                box-shadow: none;
                border: 0;
                border-radius: 0;
                width: <?php echo e($paper); ?>;
                padding: <?php echo $paper === '58mm' ? '3mm 2.5mm 4mm' : '4mm 4mm 5mm'; ?>;
            }
        }
        @media (max-width: 640px) {
            .receipt-bluetooth-grid {
                grid-template-columns: 1fr;
            }
            .receipt-serial-grid {
                grid-template-columns: 1fr;
            }
            .receipt-toolbar-title {
                font-size: 19px;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-toolbar no-print">
        <div class="receipt-toolbar-card">
            <div class="receipt-toolbar-top">
                <div>
                    <div class="receipt-toolbar-badge">
                        <span class="material-icons-outlined" style="font-size:16px;">receipt_long</span>
                        KASPINDO Print Center
                    </div>
                    <div class="receipt-toolbar-title">Struk #<?php echo (int) $transaction['id']; ?></div>
                    <div class="receipt-toolbar-copy">
                        Print Browser cocok untuk printer yang drivernya sudah terpasang di Windows. Tombol Bluetooth / Serial akan mencoba BLE lebih dulu, lalu fallback ke Bluetooth Serial/COM bila browser mendukung.
                    </div>
                </div>
                <div class="receipt-toolbar-side">
                    <span class="material-icons-outlined" style="font-size:18px;">straighten</span>
                    <?php echo e($paper); ?>
                </div>
            </div>
            <div class="receipt-toolbar-actions">
                <button type="button" class="receipt-btn receipt-btn-primary" id="browserPrintBtn">
                    <span class="material-icons-outlined">print</span>
                    Print Browser
                </button>
                <button type="button" class="receipt-btn receipt-btn-ghost" id="bluetoothPrintBtn">
                    <span class="material-icons-outlined">bluetooth</span>
                    Cetak Bluetooth / Serial
                </button>
            </div>
            <div class="receipt-bluetooth-grid">
                <div>
                    <label for="bluetoothServices">Service UUID BLE</label>
                    <textarea id="bluetoothServices"></textarea>
                </div>
                <div>
                    <label for="bluetoothCharacteristics">Characteristic UUID BLE</label>
                    <textarea id="bluetoothCharacteristics"></textarea>
                </div>
            </div>
            <div class="receipt-serial-grid">
                <div>
                    <label for="paperWidth">Lebar Kertas Thermal</label>
                    <select id="paperWidth">
                        <option value="58">58 mm</option>
                        <option value="80">80 mm</option>
                    </select>
                </div>
                <div>
                    <label for="serialBaudRate">Baud Rate Serial / COM</label>
                    <select id="serialBaudRate">
                        <option value="9600">9600</option>
                        <option value="19200">19200</option>
                        <option value="38400">38400</option>
                        <option value="57600">57600</option>
                        <option value="115200">115200</option>
                    </select>
                </div>
            </div>
            <div class="receipt-help">
                Jika printer sudah dipairing di Windows tetapi status drivernya masih bermasalah, mode Serial/COM sering lebih cocok daripada BLE untuk printer kasir.
            </div>
            <div class="receipt-status<?php echo $bluetoothMode ? ' success' : ''; ?>" id="bluetoothStatus">
                <?php echo $bluetoothMode ? 'Mode Bluetooth aktif. Nyalakan printer lalu tekan "Cetak Bluetooth / Serial".' : 'Siapkan printer jika ingin cetak langsung via Bluetooth atau Serial.'; ?>
            </div>
        </div>
    </div>
    <div class="receipt">
        <div class="center">
            <img class="receipt-logo" src="<?php echo e(base_url('assets/images/logo.jpg')); ?>" alt="KASPINDO">
            <div class="store-name"><?php echo e($storeName !== '' ? $storeName : $brandName); ?></div>
            <?php if ($storeAddress !== ''): ?>
                <div class="store-info"><?php echo e($storeAddress); ?></div>
            <?php endif; ?>
            <?php if ($storePhone !== ''): ?>
                <div class="store-info">No. Telp <?php echo e($storePhone); ?></div>
            <?php endif; ?>
            <?php if ($storeWhatsapp !== '' && $storeWhatsapp !== $storePhone): ?>
                <div class="store-info"><?php echo e($storeWhatsapp); ?></div>
            <?php endif; ?>
        </div>
        <div class="line"></div>
        <div class="receipt-info-grid">
            <span>No Transaksi</span>
            <span>#<?php echo (int) $transaction['id']; ?></span>
            <span>Tanggal</span>
            <span><?php echo e($receiptDate . ' ' . $receiptTime); ?></span>
            <span>Kasir</span>
            <span><?php echo e($cashierName); ?></span>
            <?php if (!empty($storeInfo['store_code'])): ?>
                <span>Kode Store</span>
                <span><?php echo e((string) ($storeInfo['store_code'] ?? '')); ?></span>
            <?php endif; ?>
        </div>
        <?php if (is_array($customerMeta) && !empty($customerMeta['name'])): ?>
            <div class="receipt-info-grid spaced">
                <span>Member</span>
                <span><?php echo e((string) ($customerMeta['name'] ?? '-')); ?></span>
            </div>
        <?php endif; ?>
        <div class="line"></div>
        <?php foreach ($receiptItems as $index => $item): ?>
            <div class="receipt-item">
                <div class="receipt-item-title"><?php echo ($index + 1) . '. ' . e((string) $item['name']); ?></div>
                <div class="receipt-item-line">
                    <span><?php echo (int) $item['qty']; ?> x <?php echo e(format_rupiah((float) $item['price'])); ?></span>
                    <span><?php echo e(format_rupiah((float) $item['total'])); ?></span>
                </div>
            </div>
            <?php if (!empty($item['discount'])): ?>
                <div class="receipt-item-line muted">
                    <span>Diskon Item</span>
                    <span>-<?php echo e(format_rupiah((float) $item['discount'])); ?></span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="line"></div>
        <div class="row">
            <span>Total Qty</span>
            <span><?php echo e((string) $totalQty); ?></span>
        </div>
        <div class="row">
            <span>Subtotal</span>
            <span><?php echo e(format_rupiah($subtotal)); ?></span>
        </div>
        <?php if (!empty($summaryRows)): ?>
            <?php foreach ($summaryRows as $summaryRow): ?>
                <div class="row">
                    <span><?php echo e((string) ($summaryRow['label'] ?? '')); ?></span>
                    <span><?php echo e((string) ($summaryRow['value'] ?? '')); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="row receipt-total-row">
            <span>Total</span>
            <span><?php echo e(format_rupiah($total)); ?></span>
        </div>
        <div class="row">
            <span>Pembayaran</span>
            <span><?php echo e($paymentMethodText); ?></span>
        </div>
        <?php foreach ($paymentDetailRows as $paymentRow): ?>
            <div class="row">
                <span><?php echo e((string) ($paymentRow['label'] ?? '')); ?></span>
                <span><?php echo e((string) ($paymentRow['value'] ?? '')); ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (is_array($customerMeta) && !empty($customerMeta['points_earned'])): ?>
            <div class="row">
                <span>Poin Didapat</span>
                <span>+<?php echo e((string) ((int) ($customerMeta['points_earned'] ?? 0))); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($note !== ''): ?>
            <div class="line"></div>
            <div class="muted">Catatan:</div>
            <div class="spaced"><?php echo e($note); ?></div>
        <?php endif; ?>
        <div class="center spaced">
            <div class="receipt-thanks"><?php echo e($receiptFooter !== '' ? $receiptFooter : 'Terimakasih Telah Berbelanja'); ?></div>
        </div>
        <div class="center muted spaced">Powered by KASPINDO</div>
    </div>
    <script>
        const receiptPayload = <?php echo json_encode([
            'paper_width_chars' => $paper === '58mm' ? 32 : 42,
            'brand_name' => $brandName,
            'store' => [
                'name' => $storeName,
                'info_lines' => $storeInformationLines,
                'footer' => $receiptFooter,
            ],
            'transaction' => [
                'id' => (int) $transaction['id'],
                'created_at' => (string) ($transaction['created_at'] ?? ''),
                'date' => $receiptDate,
                'time' => $receiptTime,
                'method' => $paymentMethodLabel,
                'method_text' => $paymentMethodText,
                'cashier_name' => $cashierName,
                'store_code' => (string) ($storeInfo['store_code'] ?? ''),
            ],
            'customer' => is_array($customerMeta) ? $customerMeta : null,
            'items' => $receiptItems,
            'summary_rows' => $summaryRows,
            'payment_rows' => $paymentDetailRows,
            'subtotal' => $subtotal,
            'total_qty' => $totalQty,
            'total' => $total,
            'cash_received' => $cashReceived,
            'cash_change' => $cashChange,
            'note' => $note,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const autoPrint = <?php echo $autoPrint ? 'true' : 'false'; ?>;
        const currentPaperSetting = <?php echo json_encode($paper === '58mm' ? '58' : '80'); ?>;
        const defaultServices = [
            '0000ffe0-0000-1000-8000-00805f9b34fb',
            '000018f0-0000-1000-8000-00805f9b34fb',
            '6e400001-b5a3-f393-e0a9-e50e24dcca9e'
        ];
        const defaultCharacteristics = [
            '0000ffe1-0000-1000-8000-00805f9b34fb',
            '00002af1-0000-1000-8000-00805f9b34fb',
            '6e400002-b5a3-f393-e0a9-e50e24dcca9e'
        ];

        const browserPrintBtn = document.getElementById('browserPrintBtn');
        const bluetoothPrintBtn = document.getElementById('bluetoothPrintBtn');
        const bluetoothStatus = document.getElementById('bluetoothStatus');
        const bluetoothServices = document.getElementById('bluetoothServices');
        const bluetoothCharacteristics = document.getElementById('bluetoothCharacteristics');
        const paperWidth = document.getElementById('paperWidth');
        const serialBaudRate = document.getElementById('serialBaudRate');

        const updateBluetoothStatus = (message, type = '') => {
            if (!bluetoothStatus) {
                return;
            }
            bluetoothStatus.textContent = message;
            bluetoothStatus.className = 'receipt-status' + (type ? ' ' + type : '');
        };

        const sanitizeText = (value) => {
            const raw = String(value ?? '');
            const normalized = typeof raw.normalize === 'function' ? raw.normalize('NFKD') : raw;
            return normalized.replace(/[^\x20-\x7E]/g, '').trim();
        };

        const formatCurrencyText = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(Number(value) || 0));
        const repeatText = (char, length) => new Array(Math.max(0, length) + 1).join(char);
        const supportsWebBluetooth = () => typeof navigator !== 'undefined' && !!navigator.bluetooth;
        const supportsWebSerial = () => typeof navigator !== 'undefined'
            && !!navigator.serial
            && typeof navigator.serial.requestPort === 'function';
        const centerText = (value, width) => {
            const cleanValue = sanitizeText(value);
            if (!cleanValue) {
                return '';
            }
            if (cleanValue.length >= width) {
                return cleanValue;
            }
            const leftPadding = Math.floor((width - cleanValue.length) / 2);
            return repeatText(' ', leftPadding) + cleanValue;
        };
        const padLine = (label, value, width) => {
            const cleanLabel = sanitizeText(label);
            const cleanValue = sanitizeText(value);
            if (cleanLabel.length + cleanValue.length + 1 <= width) {
                return cleanLabel + repeatText(' ', width - cleanLabel.length - cleanValue.length) + cleanValue;
            }

            return cleanLabel + '\n' + cleanValue;
        };

        const wrapText = (value, width) => {
            const text = sanitizeText(value);
            if (!text) {
                return [];
            }

            const words = text.split(/\s+/);
            const lines = [];
            let current = '';

            words.forEach((word) => {
                if (word.length > width) {
                    if (current) {
                        lines.push(current);
                        current = '';
                    }
                    for (let index = 0; index < word.length; index += width) {
                        lines.push(word.slice(index, index + width));
                    }
                    return;
                }

                const candidate = current ? current + ' ' + word : word;
                if (candidate.length > width) {
                    if (current) {
                        lines.push(current);
                    }
                    current = word;
                    return;
                }

                current = candidate;
            });

            if (current) {
                lines.push(current);
            }

            return lines;
        };

        const buildReceiptText = (payload) => {
            const width = Number(payload.paper_width_chars) || 42;
            const separator = repeatText('-', width);
            const lines = [];

            lines.push(centerText(payload.store.name || payload.brand_name || 'KASPINDO', width));
            (Array.isArray(payload.store.info_lines) ? payload.store.info_lines : []).forEach((infoLine) => {
                wrapText(infoLine, width).forEach((line) => lines.push(centerText(line, width)));
            });
            lines.push(separator);
            lines.push(padLine(sanitizeText(payload.transaction.date || ''), sanitizeText(payload.transaction.store_code || ''), width));
            lines.push(padLine(sanitizeText(payload.transaction.time || ''), sanitizeText(payload.transaction.cashier_name || ''), width));

            if (payload.customer && payload.customer.name) {
                lines.push(padLine('Member', sanitizeText(payload.customer.name), width));
            }

            lines.push(padLine('No Transaksi', '#' + payload.transaction.id, width));
            lines.push(separator);
            payload.items.forEach((item, index) => {
                wrapText(`${index + 1}. ${item.name}`, width).forEach((line) => lines.push(line));
                lines.push(padLine(
                    `  ${Number(item.qty || 0)} x ${formatCurrencyText(item.price || 0).replace(/^Rp\s*/, '')}`,
                    formatCurrencyText(item.total || 0),
                    width
                ));
                if (Number(item.discount || 0) > 0) {
                    lines.push(padLine('Diskon Item', '-' + formatCurrencyText(item.discount || 0), width));
                }
            });

            lines.push(separator);
            lines.push(padLine('Total Qty', Number(payload.total_qty || 0), width));
            lines.push(padLine('Subtotal', formatCurrencyText(payload.subtotal || 0), width));
            if (payload.summary_rows.length) {
                payload.summary_rows.forEach((row) => {
                    lines.push(padLine(row.label, row.value, width));
                });
            }
            lines.push(padLine('Total', formatCurrencyText(payload.total || 0), width));
            lines.push(padLine('Pembayaran', sanitizeText(payload.transaction.method_text || payload.transaction.method || '-'), width));
            (Array.isArray(payload.payment_rows) ? payload.payment_rows : []).forEach((row) => {
                lines.push(padLine(row.label, row.value, width));
            });

            if (payload.customer && Number(payload.customer.points_earned || 0) > 0) {
                lines.push(padLine('Poin', '+' + Number(payload.customer.points_earned || 0), width));
            }

            if (payload.note) {
                lines.push(separator);
                lines.push('Catatan:');
                wrapText(payload.note, width).forEach((line) => lines.push(line));
            }

            if (payload.store.footer) {
                wrapText(payload.store.footer, width).forEach((line) => lines.push(centerText(line, width)));
            }
            lines.push(centerText('Powered by KASPINDO', width));

            return lines.join('\n') + '\n\n\n';
        };

        const buildEscPosPayload = (payload) => {
            const encoder = new TextEncoder();
            const width = Number(payload.paper_width_chars) || 42;
            const separator = repeatText('-', width);
            const chunks = [];
            const pushCommand = (...bytes) => chunks.push(Uint8Array.from(bytes));
            const pushText = (text = '') => chunks.push(encoder.encode(String(text) + '\n'));
            const footerLines = [];

            pushCommand(0x1b, 0x40);
            pushCommand(0x1b, 0x61, 0x01);
            pushCommand(0x1b, 0x45, 0x01);
            pushCommand(0x1d, 0x21, width <= 32 ? 0x01 : 0x11);
            pushText(sanitizeText(payload.store.name || payload.brand_name || 'KASPINDO'));
            pushCommand(0x1d, 0x21, 0x00);
            pushCommand(0x1b, 0x45, 0x00);
            (Array.isArray(payload.store.info_lines) ? payload.store.info_lines : []).forEach((infoLine) => {
                wrapText(infoLine, width).forEach((line) => pushText(sanitizeText(line)));
            });

            pushCommand(0x1b, 0x61, 0x00);
            pushText(separator);
            pushText(padLine(sanitizeText(payload.transaction.date || ''), sanitizeText(payload.transaction.store_code || ''), width));
            pushText(padLine(sanitizeText(payload.transaction.time || ''), sanitizeText(payload.transaction.cashier_name || ''), width));

            if (payload.customer && payload.customer.name) {
                pushText(padLine('Member', sanitizeText(payload.customer.name), width));
            }

            pushText(padLine('No Transaksi', '#' + payload.transaction.id, width));
            pushText(separator);
            payload.items.forEach((item, index) => {
                wrapText(`${index + 1}. ${item.name}`, width).forEach((line) => pushText(line));
                pushText(padLine(
                    `  ${Number(item.qty || 0)} x ${formatCurrencyText(item.price || 0).replace(/^Rp\s*/, '')}`,
                    formatCurrencyText(item.total || 0),
                    width
                ));
                if (Number(item.discount || 0) > 0) {
                    pushText(padLine('Diskon Item', '-' + formatCurrencyText(item.discount || 0), width));
                }
            });

            pushText(separator);
            pushText(padLine('Total Qty', Number(payload.total_qty || 0), width));
            pushText(padLine('Subtotal', formatCurrencyText(payload.subtotal || 0), width));
            if (payload.summary_rows.length) {
                payload.summary_rows.forEach((row) => {
                    pushText(padLine(row.label, row.value, width));
                });
            }
            pushCommand(0x1b, 0x45, 0x01);
            pushText(padLine('Total', formatCurrencyText(payload.total || 0), width));
            pushCommand(0x1b, 0x45, 0x00);
            pushText(padLine('Pembayaran', sanitizeText(payload.transaction.method_text || payload.transaction.method || '-'), width));
            (Array.isArray(payload.payment_rows) ? payload.payment_rows : []).forEach((row) => {
                pushText(padLine(row.label, row.value, width));
            });

            if (payload.customer && Number(payload.customer.points_earned || 0) > 0) {
                pushText(padLine('Poin', '+' + Number(payload.customer.points_earned || 0), width));
            }

            if (payload.note) {
                pushText(separator);
                pushCommand(0x1b, 0x45, 0x01);
                pushText('Catatan');
                pushCommand(0x1b, 0x45, 0x00);
                wrapText(payload.note, width).forEach((line) => pushText(line));
            }

            if (payload.store.footer) {
                footerLines.push(...wrapText(payload.store.footer, width));
            }
            footerLines.push('Powered by KASPINDO');

            pushCommand(0x1b, 0x61, 0x01);
            footerLines.forEach((line) => pushText(sanitizeText(line)));
            pushCommand(0x1b, 0x64, 0x04);
            pushCommand(0x1d, 0x56, 0x00);

            const totalLength = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
            const merged = new Uint8Array(totalLength);
            let offset = 0;
            chunks.forEach((chunk) => {
                merged.set(chunk, offset);
                offset += chunk.length;
            });

            return merged;
        };

        const parseUuidList = (value, fallback) => {
            const items = String(value || '')
                .split(/[\s,]+/)
                .map((item) => item.trim())
                .filter(Boolean);

            return items.length ? Array.from(new Set(items)) : fallback;
        };

        const findWritableCharacteristic = async (service, characteristicCandidates) => {
            for (const uuid of characteristicCandidates) {
                try {
                    const characteristic = await service.getCharacteristic(uuid);
                    if (characteristic) {
                        return characteristic;
                    }
                } catch (error) {
                }
            }

            try {
                const characteristics = await service.getCharacteristics();
                for (const characteristic of characteristics) {
                    if (characteristic.properties.write || characteristic.properties.writeWithoutResponse) {
                        return characteristic;
                    }
                }
            } catch (error) {
            }

            return null;
        };

        const connectPrinter = async () => {
            if (!supportsWebBluetooth()) {
                throw new Error('Web Bluetooth tidak tersedia di browser ini.');
            }

            const serviceUuids = parseUuidList(bluetoothServices?.value, defaultServices);
            const characteristicUuids = parseUuidList(bluetoothCharacteristics?.value, defaultCharacteristics);

            if (bluetoothServices) {
                localStorage.setItem('kp_receipt_service_uuids', serviceUuids.join('\n'));
            }
            if (bluetoothCharacteristics) {
                localStorage.setItem('kp_receipt_characteristic_uuids', characteristicUuids.join('\n'));
            }

            const device = await navigator.bluetooth.requestDevice({
                acceptAllDevices: true,
                optionalServices: serviceUuids,
            });
            const server = await device.gatt.connect();

            for (const serviceUuid of serviceUuids) {
                try {
                    const service = await server.getPrimaryService(serviceUuid);
                    const characteristic = await findWritableCharacteristic(service, characteristicUuids);
                    if (characteristic) {
                        return { device, characteristic };
                    }
                } catch (error) {
                }
            }

            const services = await server.getPrimaryServices();
            for (const service of services) {
                const characteristic = await findWritableCharacteristic(service, characteristicUuids);
                if (characteristic) {
                    return { device, characteristic };
                }
            }

            throw new Error('Characteristic tulis printer tidak ditemukan. Coba isi UUID printer Anda.');
        };

        const writeChunks = async (characteristic, data, chunkSize = 180) => {
            for (let offset = 0; offset < data.length; offset += chunkSize) {
                const chunk = data.slice(offset, offset + chunkSize);
                if (characteristic.properties.writeWithoutResponse && typeof characteristic.writeValueWithoutResponse === 'function') {
                    await characteristic.writeValueWithoutResponse(chunk);
                } else {
                    await characteristic.writeValue(chunk);
                }
                await new Promise((resolve) => setTimeout(resolve, 35));
            }
        };

        const getSerialBaudRate = () => {
            const baudRate = Number(serialBaudRate?.value || localStorage.getItem('kp_receipt_serial_baud_rate') || 9600);
            return Number.isFinite(baudRate) && baudRate > 0 ? baudRate : 9600;
        };

        const connectSerialPrinter = async () => {
            if (!supportsWebSerial()) {
                throw new Error('Web Serial tidak tersedia di browser ini.');
            }

            if (serialBaudRate) {
                localStorage.setItem('kp_receipt_serial_baud_rate', String(getSerialBaudRate()));
            }

            const existingPorts = await navigator.serial.getPorts();
            if (existingPorts.length > 0) {
                return existingPorts[0];
            }

            return navigator.serial.requestPort({});
        };

        const writeSerial = async (port, data) => {
            if (!port.writable) {
                await port.open({
                    baudRate: getSerialBaudRate(),
                    dataBits: 8,
                    stopBits: 1,
                    parity: 'none',
                    flowControl: 'none',
                    bufferSize: 4096,
                });
            }

            const writer = port.writable.getWriter();
            try {
                await writer.write(data);
            } finally {
                writer.releaseLock();
            }

            await new Promise((resolve) => setTimeout(resolve, 180));

            if (port.readable || port.writable) {
                try {
                    await port.close();
                } catch (error) {
                }
            }
        };

        const shouldFallbackToSerial = (error) => {
            const message = String(error && error.message ? error.message : '').toLowerCase();
            return message.includes('globally disabled')
                || message.includes('web bluetooth tidak tersedia')
                || message.includes('not supported')
                || message.includes('not available')
                || message.includes('bluetooth adapter')
                || message.includes('no bluetooth device selected');
        };

        const handleBlePrint = async (data) => {
            const { device, characteristic } = await connectPrinter();
            updateBluetoothStatus(`Terhubung ke ${device.name || 'printer Bluetooth'}. Mengirim struk via BLE...`, '');
            await writeChunks(characteristic, data);
            updateBluetoothStatus('Struk berhasil dikirim ke printer Bluetooth BLE.', 'success');
        };

        const handleSerialPrint = async (data) => {
            const port = await connectSerialPrinter();
            const portInfo = typeof port.getInfo === 'function' ? port.getInfo() : {};
            const portLabel = portInfo.usbVendorId || portInfo.usbProductId
                ? `port serial (${portInfo.usbVendorId || '-'}:${portInfo.usbProductId || '-'})`
                : 'port serial';
            updateBluetoothStatus(`Terhubung ke ${portLabel}. Mengirim struk via Serial/COM...`, '');
            await writeSerial(port, data);
            updateBluetoothStatus('Struk berhasil dikirim ke printer Serial/COM.', 'success');
        };

        const handleBluetoothPrint = async () => {
            try {
                const escPosPayload = buildEscPosPayload(receiptPayload);

                if (supportsWebBluetooth()) {
                    updateBluetoothStatus('Mencari printer Bluetooth BLE...', '');
                    try {
                        await handleBlePrint(escPosPayload);
                        return;
                    } catch (error) {
                        if (!shouldFallbackToSerial(error) || !supportsWebSerial()) {
                            throw error;
                        }

                        updateBluetoothStatus(
                            'Web Bluetooth tidak bisa dipakai di browser ini. Mencoba jalur Serial/COM...',
                            ''
                        );
                    }
                } else if (!supportsWebSerial()) {
                    throw new Error('Browser ini tidak mendukung Web Bluetooth maupun Web Serial. Gunakan Print Browser atau Chrome/Edge desktop.');
                }

                updateBluetoothStatus('Membuka port Serial/COM printer...', '');
                await handleSerialPrint(escPosPayload);
            } catch (error) {
                updateBluetoothStatus(
                    'Cetak Bluetooth / Serial gagal.\n'
                    + (error && error.message ? error.message : 'Periksa pairing printer, izin browser, atau gunakan Print Browser.'),
                    'error'
                );
            }
        };

        if (bluetoothServices) {
            bluetoothServices.value = localStorage.getItem('kp_receipt_service_uuids') || defaultServices.join('\n');
        }
        if (bluetoothCharacteristics) {
            bluetoothCharacteristics.value = localStorage.getItem('kp_receipt_characteristic_uuids') || defaultCharacteristics.join('\n');
        }
        if (paperWidth) {
            paperWidth.value = currentPaperSetting;
            paperWidth.addEventListener('change', () => {
                localStorage.setItem('kp_receipt_paper_width', paperWidth.value);
                const url = new URL(window.location.href);
                url.searchParams.set('paper', paperWidth.value);
                window.location.href = url.toString();
            });
        }
        if (serialBaudRate) {
            serialBaudRate.value = localStorage.getItem('kp_receipt_serial_baud_rate') || '9600';
            serialBaudRate.addEventListener('change', () => {
                localStorage.setItem('kp_receipt_serial_baud_rate', serialBaudRate.value);
            });
        }

        if (browserPrintBtn) {
            browserPrintBtn.addEventListener('click', () => window.print());
        }
        if (bluetoothPrintBtn) {
            bluetoothPrintBtn.addEventListener('click', handleBluetoothPrint);
        }

        if (!supportsWebBluetooth() && supportsWebSerial()) {
            updateBluetoothStatus(
                'Web Bluetooth tidak aktif di browser ini. Tombol "Cetak Bluetooth / Serial" akan memakai jalur Serial/COM.',
                ''
            );
        } else if (!supportsWebBluetooth() && !supportsWebSerial()) {
            updateBluetoothStatus(
                'Browser ini tidak mendukung Web Bluetooth maupun Web Serial. Gunakan Print Browser atau buka halaman ini di Chrome/Edge desktop.',
                'error'
            );
        }

        if (autoPrint) {
            window.addEventListener('load', () => window.print());
        }
    </script>
</body>
</html>
