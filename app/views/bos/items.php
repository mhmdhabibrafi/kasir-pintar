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

<div class="kp-card p-4 mb-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
    <div class="kp-page-header">
        <div>
            <h2 class="kp-page-title">Laporan Item Terjual</h2>
            <p class="kp-page-subtitle">Ringkasan penjualan per produk.</p>
        </div>
        <div class="kp-page-actions">
            <a class="btn kp-btn-ghost" href="<?php echo e(base_url('bos.php')); ?>">
                <span class="material-icons-outlined">arrow_back</span>
                Kembali
            </a>
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

    <div class="kp-grid kp-grid-2">
        <?php foreach ($rows as $row): ?>
            <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
                <div class="fw-semibold"><?php echo e($row['product_name']); ?></div>
                <div class="kp-muted small mt-1">Qty terjual: <?php echo e((string) $row['total_qty']); ?></div>
                <div class="fw-semibold mt-3"><?php echo e(format_rupiah((float) $row['total_sales'])); ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <div class="kp-card-flat p-3 text-center kp-muted bg-white border border-slate-200 rounded-2xl shadow-sm">Tidak ada data.</div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
