<?php
require_once __DIR__ . '/../layouts/header.php';

$itemReportSummary = $itemReportSummary ?? [
    'unique_items' => 0,
    'total_qty' => 0,
    'total_sales' => 0,
    'average_sales_per_item' => 0,
    'top_item_name' => '-',
    'top_item_qty' => 0,
    'top_item_sales' => 0,
];
$totalSalesValue = max(0.0, (float) ($itemReportSummary['total_sales'] ?? 0));
?>

<style>
    .kp-item-summary {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        margin-bottom: 20px;
    }
    .kp-item-stat {
        border: 1px solid var(--kp-border);
        border-radius: 18px;
        background: #fff;
        padding: 16px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }
    .kp-item-stat-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--kp-muted);
        margin-bottom: 8px;
    }
    .kp-item-stat-value {
        font-size: 26px;
        line-height: 1.1;
        font-weight: 700;
        color: var(--kp-text);
    }
    .kp-item-stat-note {
        margin-top: 8px;
        font-size: 12px;
        color: var(--kp-muted);
        line-height: 1.55;
    }
</style>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

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

<div class="kp-item-summary">
    <div class="kp-item-stat">
        <div class="kp-item-stat-label">Omzet Item</div>
        <div class="kp-item-stat-value"><?php echo e(format_rupiah((float) ($itemReportSummary['total_sales'] ?? 0))); ?></div>
        <div class="kp-item-stat-note">Total nilai penjualan seluruh item pada rentang aktif.</div>
    </div>
    <div class="kp-item-stat">
        <div class="kp-item-stat-label">Qty Terjual</div>
        <div class="kp-item-stat-value"><?php echo e((string) ($itemReportSummary['total_qty'] ?? 0)); ?></div>
        <div class="kp-item-stat-note">Akumulasi unit yang terjual dari semua produk.</div>
    </div>
    <div class="kp-item-stat">
        <div class="kp-item-stat-label">Produk Terjual</div>
        <div class="kp-item-stat-value"><?php echo e((string) ($itemReportSummary['unique_items'] ?? 0)); ?></div>
        <div class="kp-item-stat-note">Jumlah SKU yang muncul dalam transaksi pada periode ini.</div>
    </div>
    <div class="kp-item-stat">
        <div class="kp-item-stat-label">Produk Teratas</div>
        <div class="kp-item-stat-value"><?php echo e((string) ($itemReportSummary['top_item_name'] ?? '-')); ?></div>
        <div class="kp-item-stat-note"><?php echo e((string) ($itemReportSummary['top_item_qty'] ?? 0)); ?> qty • <?php echo e(format_rupiah((float) ($itemReportSummary['top_item_sales'] ?? 0))); ?></div>
    </div>
</div>

<div class="kp-card p-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
    <?php if (!empty($rows)): ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Produk</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Kontribusi</th>
                        <th class="text-end">Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php $share = $totalSalesValue > 0 ? (((float) ($row['total_sales'] ?? 0) / $totalSalesValue) * 100) : 0; ?>
                        <tr>
                            <td class="fw-semibold">#<?php echo e((string) ($index + 1)); ?></td>
                            <td><?php echo e((string) ($row['product_name'] ?? '-')); ?></td>
                            <td class="text-end"><?php echo e((string) ($row['total_qty'] ?? 0)); ?></td>
                            <td class="text-end"><?php echo e(number_format($share, 1)); ?>%</td>
                            <td class="text-end fw-semibold"><?php echo e(format_rupiah((float) ($row['total_sales'] ?? 0))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="kp-card-flat p-3 text-center kp-muted bg-white border border-slate-200 rounded-2xl shadow-sm">Tidak ada data.</div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
