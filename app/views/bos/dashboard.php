<?php
require_once __DIR__ . '/../layouts/header.php';

$summary = $summary ?? ['cash' => 0, 'qris' => 0];
$paymentTotal = max(1, (float) $summary['cash'] + (float) $summary['qris']);
$cashPct = (float) $summary['cash'] / $paymentTotal * 100;
$qrisPct = 100 - $cashPct;
$revenueSeries = $stats['revenue_series'] ?? [];
$revenueValues = array_map(static fn ($row) => (float) $row['value'], $revenueSeries);
$maxRevenue = max(1, ...$revenueValues);
$chartWidth = 320;
$chartHeight = 140;
$chartPadding = 12;
$pointCount = max(1, count($revenueSeries));
$step = $pointCount > 1 ? ($chartWidth - 2 * $chartPadding) / ($pointCount - 1) : 0;
$linePoints = [];
$areaPoints = [];
foreach ($revenueSeries as $index => $row) {
    $x = $chartPadding + ($step * $index);
    $value = (float) $row['value'];
    $y = $chartHeight - $chartPadding - ($value / $maxRevenue) * ($chartHeight - 2 * $chartPadding);
    $linePoints[] = $x . ',' . $y;
    $areaPoints[] = $x . ',' . $y;
}
$areaPoints[] = ($chartWidth - $chartPadding) . ',' . ($chartHeight - $chartPadding);
$areaPoints[] = $chartPadding . ',' . ($chartHeight - $chartPadding);
$linePath = implode(' ', $linePoints);
$areaPath = implode(' ', $areaPoints);
$revenueComparison = $stats['revenue_comparison'] ?? ['today' => 0, 'yesterday' => 0, 'diff' => 0, 'pct' => 0, 'trend' => 'up'];
$topProducts = $stats['top_products'] ?? [];
$hasTransactions = $stats['has_transactions'] ?? false;
$operationsSnapshot = $operationsSnapshot ?? [];
$opsProfile = $operationsSnapshot['profile_completion'] ?? ['percent' => 0, 'completed' => 0, 'total' => 0];
$opsLowStock = $operationsSnapshot['low_stock_items'] ?? [];
$opsLowStockPreview = array_slice($opsLowStock, 0, 3);
$opsBusinessHours = trim((string) ($operationsSnapshot['business_hours'] ?? ''));
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?php echo e($success); ?>
    </div>
<?php endif; ?>

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title">Dashboard Owner</h2>
        <p class="kp-page-subtitle">Ringkasan performa toko hari ini.</p>
    </div>
    <div class="kp-page-actions">
        <a class="btn kp-btn-ghost" href="<?php echo e(base_url('bos_items.php')); ?>">
            <span class="material-icons-outlined">list_alt</span>
            Laporan Per Item
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Revenue Card -->
    <div class="kp-card p-6 border-l-4 border-l-emerald-500">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                <span class="material-icons-outlined">payments</span>
            </div>
            <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg uppercase tracking-wider">Revenue</span>
        </div>
        <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1">Total Penjualan</p>
        <h3 class="text-2xl font-bold text-slate-900"><?php echo e(format_rupiah((float) ($stats['range_sales'] ?? 0))); ?></h3>
        <div class="mt-4 pt-4 border-top border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-500">Bulan ini</span>
            <span class="text-xs font-bold text-slate-900"><?php echo e(format_rupiah((float) $stats['month_sales'])); ?></span>
        </div>
    </div>

    <!-- Transaction Card -->
    <div class="kp-card p-6 border-l-4 border-l-blue-500">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
                <span class="material-icons-outlined">receipt_long</span>
            </div>
            <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg uppercase tracking-wider">Volume</span>
        </div>
        <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1">Transaksi Hari Ini</p>
        <h3 class="text-2xl font-bold text-slate-900"><?php echo e((string) $stats['transactions_today']); ?> <span class="text-sm font-medium text-slate-400">Nota</span></h3>
        <div class="mt-4 pt-4 border-top border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-500">Target Harian</span>
            <span class="text-xs font-bold text-slate-900">50 Nota</span>
        </div>
    </div>

    <!-- Cup Card -->
    <div class="kp-card p-6 border-l-4 border-l-orange-500">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-orange-50 flex items-center justify-center text-orange-600">
                <span class="material-icons-outlined">local_cafe</span>
            </div>
            <span class="text-[10px] font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded-lg uppercase tracking-wider">Product</span>
        </div>
        <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1">Cup Terjual</p>
        <h3 class="text-2xl font-bold text-slate-900"><?php echo e((string) ($stats['cups_range'] ?? 0)); ?> <span class="text-sm font-medium text-slate-400">Cup</span></h3>
        <div class="mt-4 pt-4 border-top border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-500">Periode terpilih</span>
            <span class="material-icons-outlined text-orange-500 text-sm">trending_up</span>
        </div>
    </div>

    <!-- Comparison Card -->
    <div class="kp-card p-6 border-l-4 border-l-indigo-500">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                <span class="material-icons-outlined">insights</span>
            </div>
            <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-lg uppercase tracking-wider">Growth</span>
        </div>
        <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1">Trend vs Kemarin</p>
        <div class="flex items-center gap-2">
            <h3 class="text-2xl font-bold text-slate-900"><?php echo e(number_format((float) abs($revenueComparison['pct']), 1)); ?>%</h3>
            <span class="flex items-center <?php echo ($revenueComparison['trend'] === 'up') ? 'text-emerald-500' : 'text-red-500'; ?> font-bold text-sm">
                <span class="material-icons-outlined text-sm"><?php echo ($revenueComparison['trend'] === 'up') ? 'north_east' : 'south_east'; ?></span>
            </span>
        </div>
        <div class="mt-4 pt-4 border-top border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-500">Status</span>
            <span class="text-xs font-bold <?php echo ($revenueComparison['trend'] === 'up') ? 'text-emerald-600' : 'text-red-600'; ?> uppercase">
                <?php echo e(($revenueComparison['trend'] === 'up') ? 'Meningkat' : 'Menurun'); ?>
            </span>
        </div>
    </div>
</div>

<?php if (!$hasTransactions): ?>
    <div class="kp-card-flat p-3 text-center kp-muted bg-white border border-slate-200 rounded-2xl shadow-sm mb-4">
        Belum ada transaksi.
    </div>
<?php endif; ?>

<div class="kp-page-header">
    <div>
        <h3 class="kp-section-title">Owner Watchlist</h3>
        <p class="kp-section-subtitle">Lihat kesiapan toko, stok menipis, dan kondisi operasional dari sudut pandang owner.</p>
    </div>
</div>

<div class="kp-grid kp-grid-4 mb-4">
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Profil Toko</div>
            <span class="material-icons-outlined">domain</span>
        </div>
        <div class="kp-kpi-value"><?php echo e((string) ($opsProfile['percent'] ?? 0)); ?>%</div>
        <div class="kp-muted small mt-1"><?php echo e((string) ($opsProfile['completed'] ?? 0)); ?> dari <?php echo e((string) ($opsProfile['total'] ?? 0)); ?> bagian sudah terisi.</div>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php if (!empty($operationsSnapshot['profile_is_demo'])): ?>
                <span class="kp-alert-badge active">Masih profil demo</span>
            <?php endif; ?>
            <?php if ($opsBusinessHours !== ''): ?>
                <span class="badge text-bg-light"><?php echo e($opsBusinessHours); ?></span>
            <?php endif; ?>
        </div>
        <a class="btn kp-btn-ghost btn-sm mt-3" href="<?php echo e(base_url('admin_store.php')); ?>">
            <span class="material-icons-outlined">edit</span>
            Lihat Info Toko
        </a>
    </div>

    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Stok Menipis</div>
            <span class="material-icons-outlined">inventory_2</span>
        </div>
        <div class="kp-kpi-value"><?php echo e((string) count($opsLowStock)); ?></div>
        <div class="kp-muted small mt-1">
            <?php if (!empty($opsLowStockPreview)): ?>
                <?php echo e(implode(', ', array_map(static fn (array $item): string => (string) ($item['name'] ?? '-'), $opsLowStockPreview))); ?>
            <?php else: ?>
                Tidak ada stok yang berada di bawah batas minimum.
            <?php endif; ?>
        </div>
        <a class="btn kp-btn-ghost btn-sm mt-3" href="<?php echo e(base_url('admin_inventory.php')); ?>">
            <span class="material-icons-outlined">visibility</span>
            Cek Stok
        </a>
    </div>

</div>

<div class="kp-grid kp-grid-2 mb-4">
    <div class="kp-card p-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="fw-semibold">Penjualan 7 Hari Terakhir</div>
                <div class="kp-muted small">Omzet harian</div>
            </div>
            <span class="material-icons-outlined">show_chart</span>
        </div>
        <div class="kp-line-chart">
            <svg viewBox="0 0 <?php echo (int) $chartWidth; ?> <?php echo (int) $chartHeight; ?>" preserveAspectRatio="none">
                <polygon class="kp-line-area" points="<?php echo e($areaPath); ?>"></polygon>
                <polyline class="kp-line-stroke" points="<?php echo e($linePath); ?>"></polyline>
                <?php foreach ($revenueSeries as $index => $row): ?>
                    <?php
                    $x = $chartPadding + ($step * $index);
                    $value = (float) $row['value'];
                    $y = $chartHeight - $chartPadding - ($value / $maxRevenue) * ($chartHeight - 2 * $chartPadding);
                    ?>
                    <circle class="kp-line-point" cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="3"></circle>
                <?php endforeach; ?>
            </svg>
            <div class="kp-line-labels">
                <?php foreach ($revenueSeries as $row): ?>
                    <span><?php echo e($row['label']); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="kp-card p-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="fw-semibold">Metode Pembayaran</div>
                <div class="kp-muted small">Cash vs QRIS</div>
            </div>
            <span class="material-icons-outlined">qr_code_2</span>
        </div>
        <div class="kp-pay-bars">
            <div class="kp-pay-row">
                <div class="kp-pay-label">Cash</div>
                <div class="kp-pay-bar">
                    <span style="width: <?php echo e((string) $cashPct); ?>%;"></span>
                </div>
                <div class="kp-pay-value"><?php echo e(format_rupiah((float) $summary['cash'])); ?></div>
            </div>
            <div class="kp-pay-row">
                <div class="kp-pay-label">QRIS</div>
                <div class="kp-pay-bar">
                    <span style="width: <?php echo e((string) $qrisPct); ?>%;"></span>
                </div>
                <div class="kp-pay-value"><?php echo e(format_rupiah((float) $summary['qris'])); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="kp-card p-4 mb-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="fw-semibold">Produk Terlaris</div>
            <div class="kp-muted small">Top 5 berdasarkan jumlah terjual</div>
        </div>
        <span class="material-icons-outlined">local_fire_department</span>
    </div>
    <?php if (!empty($topProducts)): ?>
        <div class="kp-top-list">
            <?php foreach ($topProducts as $row): ?>
                <div class="kp-top-item">
                    <div class="fw-semibold"><?php echo e($row['name']); ?></div>
                    <div class="kp-muted small">Terjual <?php echo e((string) $row['total_qty']); ?> cup</div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="kp-muted">Belum ada transaksi.</div>
    <?php endif; ?>
</div>

<div class="kp-card p-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h3 class="kp-section-title">Daftar Transaksi</h3>
            <p class="kp-section-subtitle">Filter tanggal dan metode pembayaran.</p>
        </div>
    </div>

    <div class="kp-filter-card mb-3">
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

    <div class="kp-dashboard-actions mb-3">
        <a class="btn kp-btn-ghost" href="<?php echo e(base_url('bos_report_pdf.php?start_date=' . urlencode($filters['start_date']) . '&end_date=' . urlencode($filters['end_date']) . '&method=' . urlencode($filters['method']))); ?>">
            <span class="material-icons-outlined">picture_as_pdf</span>
            Export PDF
        </a>
        <a class="btn kp-btn-ghost" href="<?php echo e(base_url('bos_export_csv.php?start_date=' . urlencode($filters['start_date']) . '&end_date=' . urlencode($filters['end_date']) . '&method=' . urlencode($filters['method']))); ?>">
            <span class="material-icons-outlined">table_view</span>
            Export CSV
        </a>
    </div>

    <div class="kp-grid kp-grid-3 mb-3">
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
            <div class="kp-muted small">Total Harga Jual</div>
            <div class="fw-semibold"><?php echo e(format_rupiah((float) ($rangeSummary['sales'] ?? 0))); ?></div>
        </div>
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
            <div class="kp-muted small">Net Penjualan</div>
            <div class="fw-semibold"><?php echo e(format_rupiah((float) ($rangeNetSales ?? 0))); ?></div>
        </div>
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
            <div class="kp-muted small">Total Harga Modal</div>
            <div class="fw-semibold"><?php echo e(format_rupiah((float) ($rangeSummary['cost'] ?? 0))); ?></div>
        </div>
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
            <div class="kp-muted small">Total Profit</div>
            <div class="fw-semibold"><?php echo e(format_rupiah((float) ($rangeSummary['profit'] ?? 0))); ?></div>
        </div>
    </div>

    <div class="kp-grid kp-grid-2">
        <?php foreach ($transactions as $row): ?>
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm kp-transaction-card">
                <div class="kp-transaction-header">
                    <div>
                        <div class="fw-semibold">#<?php echo (int) $row['id']; ?> - <?php echo e($row['cashier'] ?? '-'); ?></div>
                        <div class="kp-muted small"><?php echo e($row['created_at']); ?></div>
                    </div>
                    <span class="badge rounded-pill text-bg-light"><?php echo e(strtoupper($row['method'] ?? '-')); ?></span>
                </div>
                <div class="kp-transaction-total">
                    <div class="fw-semibold"><?php echo e(format_rupiah((float) $row['total_amount'])); ?></div>
                    <?php if (!empty($row['qris_proof'])): ?>
                        <button type="button" class="btn kp-btn-ghost btn-sm qris-preview" data-bs-toggle="modal" data-bs-target="#qrisModal" data-image="<?php echo e(base_url($row['qris_proof'])); ?>">
                            <span class="material-icons-outlined">image</span>
                            Bukti
                        </button>
                    <?php endif; ?>
                </div>
                <div class="kp-transaction-section">
                    <div class="kp-muted small mb-2">Item Produk</div>
                    <?php if (!empty($row['items'])): ?>
                        <div class="kp-transaction-items">
                            <?php foreach ($row['items'] as $item): ?>
                                <div class="kp-transaction-item">
                                    <div class="kp-item-name">
                                        <?php echo e($item['name']); ?>
                                        <span class="kp-item-qty">x<?php echo (int) $item['qty']; ?></span>
                                    </div>
                                    <div class="kp-item-amount"><?php echo e(format_rupiah((float) $item['total'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="kp-muted small">Tidak ada detail produk.</div>
                    <?php endif; ?>
                </div>
                <div class="kp-transaction-summary">
                    <?php
                    $itemCount = 0;
                    if (!empty($row['items'])) {
                        foreach ($row['items'] as $item) {
                            $itemCount += (int) $item['qty'];
                        }
                    }
                    ?>
                    <div class="kp-summary-row">
                        <span class="kp-muted">Total Item</span>
                        <span class="fw-semibold"><?php echo (int) $itemCount; ?></span>
                    </div>
                    <div class="kp-summary-row">
                        <span class="kp-muted">Total Harga Jual</span>
                        <span class="fw-semibold"><?php echo e(format_rupiah((float) ($row['total_sales'] ?? $row['total_amount']))); ?></span>
                    </div>
                    <div class="kp-summary-row">
                        <span class="kp-muted">Total Harga Modal</span>
                        <span class="fw-semibold"><?php echo e(format_rupiah((float) ($row['total_cost'] ?? 0))); ?></span>
                    </div>
                    <div class="kp-summary-row">
                        <span class="kp-muted">Profit</span>
                        <span class="fw-semibold"><?php echo e(format_rupiah((float) ($row['total_profit'] ?? 0))); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($transactions)): ?>
            <div class="kp-card-flat p-3 text-center kp-muted bg-white border border-slate-200 rounded-2xl shadow-sm">Tidak ada transaksi.</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="qrisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bukti QRIS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img id="qrisModalImage" class="img-fluid rounded-4" alt="Bukti QRIS">
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.qris-preview').forEach((button) => {
        button.addEventListener('click', () => {
            const image = button.getAttribute('data-image');
            const imgEl = document.getElementById('qrisModalImage');
            imgEl.src = image;
        });
    });
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
