<?php
require_once __DIR__ . '/../layouts/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title">Riwayat Transaksi</h2>
        <p class="kp-page-subtitle">Daftar transaksi kasir.</p>
    </div>
</div>

<div class="kp-filter-card mb-4">
    <form method="GET" class="kp-filter-form">
        <div>
            <label class="form-label">Dari</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo e($filters['start_date']); ?>">
        </div>
        <div>
            <label class="form-label">Sampai</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo e($filters['end_date']); ?>">
        </div>
        <div>
            <label class="form-label">Metode</label>
            <select name="method" class="form-select">
                <option value="all" <?php echo ($filters['method'] === 'all') ? 'selected' : ''; ?>>Semua</option>
                <option value="cash" <?php echo ($filters['method'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                <option value="qris" <?php echo ($filters['method'] === 'qris') ? 'selected' : ''; ?>>QRIS</option>
            </select>
        </div>
        <div>
            <button class="btn kp-btn-primary">
                <span class="material-icons-outlined">filter_alt</span>
                Filter
            </button>
        </div>
    </form>
</div>

<?php
$summary = ['transactions' => 0, 'items' => 0, 'sales' => 0.0];
if (!empty($transactions)) {
    $summary['transactions'] = count($transactions);
    foreach ($transactions as $row) {
        $summary['sales'] += (float) ($row['total_amount'] ?? 0);
        if (!empty($row['items'])) {
            foreach ($row['items'] as $item) {
                $summary['items'] += (int) ($item['qty'] ?? 0);
            }
        }
    }
}
?>

<div class="kp-grid kp-grid-3 mb-4">
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">receipt_long</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total Transaksi</div>
                <div class="kp-kpi-value"><?php echo e((string) $summary['transactions']); ?></div>
            </div>
        </div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">local_cafe</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total Cup</div>
                <div class="kp-kpi-value"><?php echo e((string) $summary['items']); ?></div>
            </div>
        </div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">payments</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total Penjualan</div>
                <div class="kp-kpi-value"><?php echo e(format_rupiah((float) $summary['sales'])); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="kp-grid kp-grid-2">
    <?php foreach ($transactions as $row): ?>
        <div class="kp-card-flat p-4 bg-white border border-slate-200 rounded-2xl shadow-sm transition hover:shadow-md">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">#<?php echo (int) $row['id']; ?></div>
                    <div class="kp-muted small"><?php echo e($row['created_at']); ?></div>
                </div>
                <span class="badge rounded-pill text-bg-light"><?php echo e(strtoupper($row['method'] ?? '-')); ?></span>
            </div>
            <div class="mt-3">
                <div class="fw-semibold text-lg"><?php echo e(format_rupiah((float) $row['total_amount'])); ?></div>
                <?php if (!empty($row['meta'])): ?>
                    <?php $meta = $row['meta']; ?>
                    <div class="kp-muted small">
                        Diskon: <?php echo e(format_rupiah((float) ($meta['item_discount_total'] ?? 0))); ?>
                        <?php if (!empty($meta['order_discount']['amount'])): ?>
                            · Order: <?php echo e(format_rupiah((float) ($meta['order_discount']['amount'] ?? 0))); ?>
                        <?php endif; ?>
                        <?php if (!empty($meta['voucher']['amount'])): ?>
                            · Voucher: <?php echo e(format_rupiah((float) ($meta['voucher']['amount'] ?? 0))); ?>
                        <?php endif; ?>
                    </div>
                    <div class="kp-muted small">
                        Pajak: <?php echo e(format_rupiah((float) ($meta['tax']['amount'] ?? 0))); ?>
                        · Service: <?php echo e(format_rupiah((float) ($meta['service']['amount'] ?? 0))); ?>
                        · Pembulatan: <?php echo e(format_rupiah((float) ($meta['rounding']['amount'] ?? 0))); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <div class="kp-muted small mb-2">Item Produk</div>
                <?php if (!empty($row['items'])): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($row['items'] as $item): ?>
                            <div class="d-flex justify-content-between small">
                                <div><?php echo e($item['name']); ?> <span class="kp-muted">x<?php echo (int) $item['qty']; ?></span></div>
                                <div class="fw-semibold"><?php echo e(format_rupiah((float) $item['total'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="kp-muted small">Tidak ada detail produk.</div>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <a class="btn kp-btn-primary btn-sm d-inline-flex align-items-center gap-2" target="_blank" href="<?php echo e(base_url('print_receipt.php?id=' . (int) $row['id'])); ?>">
                    <span class="material-icons-outlined">print</span>
                    Print Struk
                </a>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($transactions)): ?>
        <div class="kp-card-flat p-3 text-center kp-muted">Belum ada transaksi.</div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
