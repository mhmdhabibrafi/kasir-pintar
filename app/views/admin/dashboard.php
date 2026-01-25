<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$paymentSummary = $stats['payment_summary'] ?? ['cash' => 0, 'qris' => 0];
$paymentTotal = max(1, (float) $paymentSummary['cash'] + (float) $paymentSummary['qris']);
$cashPct = (float) $paymentSummary['cash'] / $paymentTotal * 100;
$qrisPct = 100 - $cashPct;

$revenueSeries = $stats['revenue_series'] ?? [];
$revenueValues = array_map(static fn ($row) => (float) $row['value'], $revenueSeries);
$maxRevenue = max(1, ...$revenueValues);
$transactionValues = array_map(static fn ($row) => (int) $row['value'], $stats['chart_transactions'] ?? []);
$maxTransaction = max(1, ...$transactionValues);
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
$hasTransactions = $stats['has_transactions'] ?? false;
$filters = $stats['filters'] ?? ['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d')];
$revenueComparison = $stats['revenue_comparison'] ?? ['today' => 0, 'yesterday' => 0, 'diff' => 0, 'pct' => 0, 'trend' => 'up'];
$topProducts = $stats['top_products'] ?? [];
$activityPanel = $stats['activity_panel'] ?? ['last_time' => null, 'last_cashier' => null, 'transaction_count_today' => 0];
$alerts = $stats['alerts'] ?? ['no_transaction_over_hours' => false, 'qris_zero_transaction' => false, 'cash_zero_transaction' => false];
$paymentPercentage = $stats['payment_percentage'] ?? ['cash_total' => 0, 'qris_total' => 0, 'cash_pct' => 0, 'qris_pct' => 0];
$systemStatus = $stats['system_status'] ?? ['database_connected' => false, 'last_transaction_time' => null];
$lastTimeLabel = $activityPanel['last_time'] ? date('d/m/Y H:i', strtotime((string) $activityPanel['last_time'])) : null;
$systemLastTime = $systemStatus['last_transaction_time'] ? date('d/m/Y H:i', strtotime((string) $systemStatus['last_transaction_time'])) : null;
?>

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title">Dashboard Admin</h2>
        <p class="kp-page-subtitle">Ringkasan performa toko secara cepat.</p>
    </div>
</div>

<div class="kp-card p-4 mb-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
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
            <button class="btn kp-btn-primary">
                <span class="material-icons-outlined">filter_alt</span>
                Terapkan
            </button>
        </div>
    </form>
</div>

<?php if (!$hasTransactions): ?>
    <div class="kp-card-flat p-3 text-center kp-muted bg-white border border-slate-200 rounded-2xl shadow-sm mb-4">
        Belum ada transaksi.
    </div>
<?php endif; ?>

<div class="kp-grid kp-grid-3 mb-4">
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Aktivitas Terakhir</div>
            <span class="material-icons-outlined">history</span>
        </div>
        <?php if ($lastTimeLabel): ?>
            <div class="kp-kpi-value"><?php echo e($lastTimeLabel); ?></div>
            <div class="kp-muted small">Kasir: <?php echo e($activityPanel['last_cashier'] ?? '-'); ?></div>
        <?php else: ?>
            <div class="kp-muted">Belum ada transaksi.</div>
        <?php endif; ?>
        <div class="kp-muted small mt-2">Transaksi pada tanggal <?php echo e($filters['end_date']); ?>: <span class="fw-semibold"><?php echo e((string) $activityPanel['transaction_count_today']); ?></span></div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Alert Operasional</div>
            <span class="material-icons-outlined">error_outline</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="kp-alert-badge <?php echo $alerts['no_transaction_over_hours'] ? 'active' : ''; ?>">
                <?php echo $alerts['no_transaction_over_hours'] ? 'Tidak ada transaksi > 2 jam' : 'Transaksi normal'; ?>
            </span>
            <span class="kp-alert-badge <?php echo $alerts['qris_zero_transaction'] ? 'active' : ''; ?>">
                <?php echo $alerts['qris_zero_transaction'] ? 'QRIS 0 transaksi' : 'QRIS aktif'; ?>
            </span>
            <span class="kp-alert-badge <?php echo $alerts['cash_zero_transaction'] ? 'active' : ''; ?>">
                <?php echo $alerts['cash_zero_transaction'] ? 'Cash 0 transaksi' : 'Cash aktif'; ?>
            </span>
        </div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Payment Insight</div>
            <span class="material-icons-outlined">donut_large</span>
        </div>
        <div class="kp-muted small mb-1">Cash <?php echo e(number_format((float) $paymentPercentage['cash_pct'], 1)); ?>%</div>
        <div class="kp-pay-bar mb-2"><span style="width: <?php echo e((string) $paymentPercentage['cash_pct']); ?>%;"></span></div>
        <div class="kp-muted small mb-1">QRIS <?php echo e(number_format((float) $paymentPercentage['qris_pct'], 1)); ?>%</div>
        <div class="kp-pay-bar"><span style="width: <?php echo e((string) $paymentPercentage['qris_pct']); ?>%;"></span></div>
        <div class="kp-muted small mt-2">Cash <?php echo e(format_rupiah((float) $paymentPercentage['cash_total'])); ?> · QRIS <?php echo e(format_rupiah((float) $paymentPercentage['qris_total'])); ?></div>
    </div>
</div>

<div class="kp-grid kp-grid-2 mb-4">
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">System Status</div>
            <span class="material-icons-outlined">monitor_heart</span>
        </div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="kp-status-dot <?php echo $systemStatus['database_connected'] ? 'online' : 'offline'; ?>"></span>
            <div class="fw-semibold"><?php echo $systemStatus['database_connected'] ? 'Online' : 'Offline'; ?></div>
        </div>
        <div class="kp-muted small">Last transaksi: <?php echo e($systemLastTime ?: 'Belum ada transaksi'); ?></div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Quick Actions</div>
            <span class="material-icons-outlined">bolt</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn kp-btn-ghost" href="<?php echo e(base_url('admin_products.php')); ?>">
                <span class="material-icons-outlined">add_circle</span>
                Tambah Produk
            </a>
            <a class="btn kp-btn-ghost" href="<?php echo e(base_url('admin_users.php')); ?>">
                <span class="material-icons-outlined">person_add</span>
                Tambah User
            </a>
            <a class="btn kp-btn-ghost" href="<?php echo e(base_url('bos_report_pdf.php?start_date=' . urlencode($filters['start_date']) . '&end_date=' . urlencode($filters['end_date']) . '&method=all')); ?>">
                <span class="material-icons-outlined">download</span>
                Export Laporan
            </a>
        </div>
    </div>
</div>

<div class="kp-card p-4 mb-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
    <div class="kp-grid kp-grid-3">
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm kp-kpi-card">
            <div class="d-flex align-items-center gap-3">
                <span class="kp-avatar">
                    <span class="material-icons-outlined">receipt_long</span>
                </span>
                <div>
                    <div class="kp-kpi-label">Total Transaksi</div>
                    <div class="kp-kpi-value"><?php echo e((string) $stats['transactions']); ?></div>
                </div>
            </div>
        </div>
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm kp-kpi-card">
            <div class="d-flex align-items-center gap-3">
                <span class="kp-avatar">
                    <span class="material-icons-outlined">payments</span>
                </span>
                <div>
                    <div class="kp-kpi-label">Total Revenue</div>
                    <div class="kp-kpi-value"><?php echo e(format_rupiah((float) $stats['total_sales'])); ?></div>
                </div>
            </div>
        </div>
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm kp-kpi-card">
            <div class="d-flex align-items-center gap-3">
                <span class="kp-avatar">
                    <span class="material-icons-outlined">trending_up</span>
                </span>
                <div>
                    <div class="kp-kpi-label">Revenue Periode</div>
                    <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($stats['period_sales'] ?? $stats['today_sales']))); ?></div>
                </div>
            </div>
        </div>
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm kp-kpi-card">
            <div class="d-flex align-items-center gap-3">
                <span class="kp-avatar">
                    <span class="material-icons-outlined">local_cafe</span>
                </span>
                <div>
                    <div class="kp-kpi-label">Cup Terjual Hari Ini</div>
                    <div class="kp-kpi-value"><?php echo e((string) ($stats['today_cups'] ?? 0)); ?></div>
                </div>
            </div>
        </div>
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm kp-kpi-card">
            <div class="d-flex align-items-center gap-3">
                <span class="kp-avatar">
                    <span class="material-icons-outlined">show_chart</span>
                </span>
                <div>
                    <div class="kp-kpi-label">Perbandingan Revenue</div>
                    <div class="kp-kpi-value"><?php echo e(format_rupiah((float) $revenueComparison['today'])); ?></div>
                    <div class="kp-kpi-meta">
                        <?php echo e(($revenueComparison['trend'] === 'up') ? 'Naik' : 'Turun'); ?>
                        <?php echo e(number_format((float) abs($revenueComparison['pct']), 1)); ?>%
                    </div>
                </div>
            </div>
        </div>
        <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm kp-kpi-card">
            <div class="d-flex align-items-center gap-3">
                <span class="kp-avatar">
                    <span class="material-icons-outlined">paid</span>
                </span>
                <div>
                    <div class="kp-kpi-label">Metode Pembayaran</div>
                    <div class="kp-kpi-meta">Cash <?php echo e(format_rupiah((float) $paymentSummary['cash'])); ?></div>
                    <div class="kp-kpi-meta">QRIS <?php echo e(format_rupiah((float) $paymentSummary['qris'])); ?></div>
                </div>
            </div>
            <div class="progress mt-3" style="height: 8px;">
                <div class="progress-bar" role="progressbar" style="width: <?php echo e((string) $cashPct); ?>%; background: var(--kp-primary);"></div>
                <div class="progress-bar" role="progressbar" style="width: <?php echo e((string) $qrisPct); ?>%; background: var(--kp-accent);"></div>
            </div>
        </div>
    </div>
</div>

<div class="kp-grid kp-grid-3 mb-4">
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">group</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total User</div>
                <div class="kp-kpi-value"><?php echo e((string) ($stats['users'] ?? 0)); ?></div>
            </div>
        </div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">inventory_2</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total Produk</div>
                <div class="kp-kpi-value"><?php echo e((string) ($stats['products'] ?? 0)); ?></div>
            </div>
        </div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">category</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total Kategori</div>
                <div class="kp-kpi-value"><?php echo e((string) ($stats['categories'] ?? 0)); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="kp-grid kp-grid-3">
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
                <div class="kp-pay-value"><?php echo e(format_rupiah((float) $paymentSummary['cash'])); ?></div>
            </div>
            <div class="kp-pay-row">
                <div class="kp-pay-label">QRIS</div>
                <div class="kp-pay-bar">
                    <span style="width: <?php echo e((string) $qrisPct); ?>%;"></span>
                </div>
                <div class="kp-pay-value"><?php echo e(format_rupiah((float) $paymentSummary['qris'])); ?></div>
            </div>
        </div>
    </div>
    <div class="kp-card p-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="fw-semibold">Transaksi 7 Hari</div>
                <div class="kp-muted small">Jumlah transaksi harian</div>
            </div>
            <span class="material-icons-outlined">bar_chart</span>
        </div>
        <div class="kp-chart">
            <?php foreach ($stats['chart_transactions'] as $row): ?>
                <?php $height = (int) max(8, ($row['value'] / $maxTransaction) * 100); ?>
                <div class="kp-bar" style="height: <?php echo (int) $height; ?>%;">
                    <span><?php echo e($row['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="kp-card p-4 mt-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
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

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
