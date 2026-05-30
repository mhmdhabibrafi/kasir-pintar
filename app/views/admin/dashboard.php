<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$filters = $dashboard['filters'] ?? ['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d')];
$revenueSeries = $dashboard['revenue_series'] ?? [];
$hasChartData = !empty($revenueSeries);
if (!$hasChartData) {
    $revenueSeries = [
        ['label' => date('d/m'), 'value' => 0, 'date' => date('Y-m-d')],
    ];
}

$chartWidth = 360;
$chartHeight = 180;
$chartPadding = 16;
$revenueValues = array_map(static fn (array $row): float => (float) ($row['value'] ?? 0), $revenueSeries);
$maxRevenue = max(1.0, ...$revenueValues);
$pointCount = max(1, count($revenueSeries));
$step = $pointCount > 1 ? ($chartWidth - (2 * $chartPadding)) / ($pointCount - 1) : 0;
$linePoints = [];
$areaPoints = [];
foreach ($revenueSeries as $index => $row) {
    $x = $chartPadding + ($step * $index);
    $value = (float) ($row['value'] ?? 0);
    $y = $chartHeight - $chartPadding - (($value / $maxRevenue) * ($chartHeight - (2 * $chartPadding)));
    $linePoints[] = $x . ',' . $y;
    $areaPoints[] = $x . ',' . $y;
}
$areaPoints[] = ($chartWidth - $chartPadding) . ',' . ($chartHeight - $chartPadding);
$areaPoints[] = $chartPadding . ',' . ($chartHeight - $chartPadding);
$linePath = implode(' ', $linePoints);
$areaPath = implode(' ', $areaPoints);

$comparison = $dashboard['revenue_comparison'] ?? ['pct' => 0, 'trend' => 'up', 'diff' => 0];
$trendUp = ($comparison['trend'] ?? 'up') === 'up';
$operations = $dashboard['operations'] ?? [];
$opsProfile = $operations['profile_completion'] ?? ['percent' => 0, 'completed' => 0, 'total' => 0];
$opsNotification = $operations['notification'] ?? ['connected' => false, 'daily_recap_enabled' => false];
$opsSupport = $operations['support'] ?? ['available' => false];
$supportEnabled = function_exists('app_env_bool') ? app_env_bool('APP_SUPPORT_ENABLED', false) : false;
$lastTransaction = $dashboard['last_transaction'] ?? null;
$attentionItems = $dashboard['attention_items'] ?? [];
$topProducts = $dashboard['top_products'] ?? [];
$paymentMix = $dashboard['payment_percentage'] ?? ['cash_total' => 0, 'qris_total' => 0, 'cash_pct' => 0, 'qris_pct' => 0];
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-6">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="card kp-admin-hero mb-6">
    <div class="grid grid-cols-1 xl:grid-cols-[1.45fr_minmax(320px,1fr)] gap-5 items-stretch">
        <div class="p-7 rounded-[26px] border border-emerald-100 bg-white">
            <div class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-[11px] font-bold tracking-[0.18em] uppercase text-emerald-700 bg-emerald-50 border border-emerald-100 mb-5">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                Command Center POS
            </div>
            <h2 class="kp-page-title">Dashboard Operasional</h2>
            <p class="kp-page-subtitle">
                Pantau performa kasir, stok, produk, dan kesiapan operasional dari satu layar yang rapi dan formal.
            </p>
            <div class="flex flex-wrap gap-3 mt-5">
                <span class="badge badge-success">
                    <i data-lucide="calendar-range" class="w-4 h-4"></i>
                    Periode <?php echo e(date('d/m/Y', strtotime($filters['start_date']))); ?> - <?php echo e(date('d/m/Y', strtotime($filters['end_date']))); ?>
                </span>
                <span class="badge badge-neutral">
                    <i data-lucide="activity" class="w-4 h-4"></i>
                    <?php echo e((string) ($dashboard['period_days'] ?? 1)); ?> hari operasional
                </span>
                <span class="badge <?php echo $trendUp ? 'badge-success' : 'badge-warning'; ?>">
                    <i data-lucide="<?php echo $trendUp ? 'trending-up' : 'trending-down'; ?>" class="w-4 h-4"></i>
                    Trend <?php echo e(number_format((float) ($comparison['pct'] ?? 0), 1)); ?>%
                </span>
            </div>
        </div>

        <div class="card p-6 flex flex-col justify-between">
            <div>
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-3">Kesiapan Outlet</div>
                <div class="flex items-end justify-between gap-4 mb-4">
                    <div class="text-5xl font-black tracking-tight text-slate-900"><?php echo e((string) ($opsProfile['percent'] ?? 0)); ?>%</div>
                    <div class="text-right text-sm text-slate-500">
                        <div><?php echo e((string) ($opsProfile['completed'] ?? 0)); ?> dari <?php echo e((string) ($opsProfile['total'] ?? 0)); ?> elemen</div>
                        <div>profil toko sudah lengkap</div>
                    </div>
                </div>
                <div class="progress-bar mb-5">
                    <div class="progress-fill" style="width: <?php echo e((string) ($opsProfile['percent'] ?? 0)); ?>%;"></div>
                </div>
            </div>
            <div class="grid grid-cols-1 <?php echo $supportEnabled ? 'sm:grid-cols-2' : ''; ?> gap-3 text-sm">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-slate-400 text-[11px] font-bold uppercase tracking-[0.16em] mb-1">Notifikasi</div>
                    <div class="font-semibold text-slate-900"><?php echo !empty($opsNotification['connected']) ? 'Terhubung' : 'Belum terhubung'; ?></div>
                </div>
                <?php if ($supportEnabled): ?>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-slate-400 text-[11px] font-bold uppercase tracking-[0.16em] mb-1">Support</div>
                        <div class="font-semibold text-slate-900"><?php echo !empty($opsSupport['available']) ? 'Aktif' : 'Belum aktif'; ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="card kp-filter-card mb-6">
    <form method="GET" class="kp-filter-form">
        <div class="form-group mb-0">
            <label class="form-label">Tanggal Awal</label>
            <input type="date" name="start_date" class="form-input" value="<?php echo e($filters['start_date']); ?>">
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Tanggal Akhir</label>
            <input type="date" name="end_date" class="form-input" value="<?php echo e($filters['end_date']); ?>">
        </div>
        <div class="flex flex-wrap gap-3 items-end">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="filter" class="w-4 h-4"></i>
                Terapkan Filter
            </button>
            <a href="<?php echo e(base_url('admin.php')); ?>" class="btn btn-secondary">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                Reset
            </a>
        </div>
    </form>
</section>

<section class="kpi-grid mb-6">
    <div class="card p-5">
        <div class="flex items-center justify-between gap-4 mb-3">
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Transaksi</div>
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i data-lucide="receipt"></i>
            </div>
        </div>
        <div class="kp-kpi-value"><?php echo e((string) ($dashboard['transactions_count'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2"><?php echo e((string) ($dashboard['today_transactions'] ?? 0)); ?> transaksi terjadi hari ini.</div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between gap-4 mb-3">
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Penjualan Bersih</div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i data-lucide="wallet"></i>
            </div>
        </div>
        <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($dashboard['net_sales'] ?? 0))); ?></div>
        <div class="text-sm text-slate-500 mt-2">Refund periode ini: <?php echo e(format_rupiah((float) ($dashboard['refund_total'] ?? 0))); ?></div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between gap-4 mb-3">
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Laba Kotor</div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <i data-lucide="trending-up"></i>
            </div>
        </div>
        <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($dashboard['gross_profit'] ?? 0))); ?></div>
        <div class="text-sm text-slate-500 mt-2">Omzet kotor: <?php echo e(format_rupiah((float) ($dashboard['gross_sales'] ?? 0))); ?></div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between gap-4 mb-3">
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Rata-rata Transaksi</div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <i data-lucide="calculator"></i>
            </div>
        </div>
        <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($dashboard['average_ticket'] ?? 0))); ?></div>
        <div class="text-sm text-slate-500 mt-2">Rata-rata harian: <?php echo e(format_rupiah((float) ($dashboard['daily_average'] ?? 0))); ?></div>
    </div>
</section>

<section class="card-grid-4 mb-6">
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Produk Aktif</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($dashboard['product_active'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Total katalog: <?php echo e((string) ($dashboard['product_total'] ?? 0)); ?> produk.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Stok Kritis</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($dashboard['low_stock_count'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Perlu follow-up restock dari gudang.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Staff Aktif</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($dashboard['staff_active'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Dari total <?php echo e((string) ($dashboard['staff_total'] ?? 0)); ?> akun operasional.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Member Aktif</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($dashboard['member_active'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Basis pelanggan: <?php echo e((string) ($dashboard['member_total'] ?? 0)); ?> member.</div>
    </div>
</section>

<section class="card-grid-2 mb-6">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Tren Penjualan 7 Hari Terakhir</h3>
                <p class="text-sm text-muted mb-0">Grafik bersih setelah refund per hari.</p>
            </div>
            <span class="badge <?php echo $trendUp ? 'badge-success' : 'badge-warning'; ?>">
                <?php echo $trendUp ? 'Naik' : 'Turun'; ?>
                <?php echo e(number_format((float) abs($comparison['pct'] ?? 0), 1)); ?>%
            </span>
        </div>
        <div class="p-6 pt-0">
            <div class="kp-line-chart">
                <svg viewBox="0 0 <?php echo (int) $chartWidth; ?> <?php echo (int) $chartHeight; ?>" preserveAspectRatio="none">
                    <polygon class="kp-line-area" points="<?php echo e($areaPath); ?>"></polygon>
                    <polyline class="kp-line-stroke" points="<?php echo e($linePath); ?>"></polyline>
                    <?php foreach ($revenueSeries as $index => $row): ?>
                        <?php
                        $x = $chartPadding + ($step * $index);
                        $value = (float) ($row['value'] ?? 0);
                        $y = $chartHeight - $chartPadding - (($value / $maxRevenue) * ($chartHeight - (2 * $chartPadding)));
                        ?>
                        <circle class="kp-line-point" cx="<?php echo e((string) $x); ?>" cy="<?php echo e((string) $y); ?>" r="4"></circle>
                    <?php endforeach; ?>
                </svg>
                <div class="kp-line-labels">
                    <?php foreach ($revenueSeries as $row): ?>
                        <span><?php echo e((string) ($row['label'] ?? '-')); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (!$hasChartData): ?>
                <div class="text-sm text-slate-500 mt-4">Belum ada penjualan pada rentang ini.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Kondisi Operasional</h3>
                <p class="text-sm text-muted mb-0">Ringkasan kondisi outlet yang paling sering dicek harian.</p>
            </div>
        </div>
        <div class="p-6 pt-0 space-y-3">
            <div class="flex items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <div>
                    <div class="font-semibold text-slate-900">Pembayaran Tunai</div>
                    <div class="text-sm text-slate-500"><?php echo e(format_rupiah((float) ($paymentMix['cash_total'] ?? 0))); ?></div>
                </div>
                <span class="badge badge-neutral"><?php echo e(number_format((float) ($paymentMix['cash_pct'] ?? 0), 1)); ?>%</span>
            </div>
            <div class="flex items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <div>
                    <div class="font-semibold text-slate-900">Pembayaran QRIS</div>
                    <div class="text-sm text-slate-500"><?php echo e(format_rupiah((float) ($paymentMix['qris_total'] ?? 0))); ?></div>
                </div>
                <span class="badge badge-info"><?php echo e(number_format((float) ($paymentMix['qris_pct'] ?? 0), 1)); ?>%</span>
            </div>
            <div class="flex items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <div>
                    <div class="font-semibold text-slate-900">Shift Aktif</div>
                    <div class="text-sm text-slate-500">Jumlah shift yang sedang berjalan sekarang.</div>
                </div>
                <span class="badge badge-neutral"><?php echo e((string) ($operations['active_shift_count'] ?? 0)); ?> shift</span>
            </div>
            <div class="flex items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <div>
                    <div class="font-semibold text-slate-900">Transaksi Terakhir</div>
                    <div class="text-sm text-slate-500">
                        <?php if (!empty($lastTransaction['created_at'])): ?>
                            #<?php echo e((string) ($lastTransaction['id'] ?? '-')); ?> oleh <?php echo e((string) ($lastTransaction['cashier'] ?? '-')); ?>
                        <?php else: ?>
                            Belum ada transaksi pada periode ini.
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($lastTransaction['created_at'])): ?>
                    <span class="badge badge-success"><?php echo e(date('d/m H:i', strtotime((string) $lastTransaction['created_at']))); ?></span>
                <?php else: ?>
                    <span class="badge badge-neutral">Kosong</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="card-grid-2">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Produk Terlaris</h3>
                <p class="text-sm text-muted mb-0">Dasar cepat untuk keputusan stok dan display produk.</p>
            </div>
        </div>
        <div class="p-0">
            <div class="table-wrapper border-0 rounded-none">
                <table class="data-table data-table--sheet">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="text-right">Qty Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $row): ?>
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-900"><?php echo e((string) ($row['name'] ?? '-')); ?></div>
                                </td>
                                <td class="text-right font-semibold text-slate-900"><?php echo e((string) ($row['total_qty'] ?? 0)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topProducts)): ?>
                            <tr>
                                <td colspan="2" class="text-center py-8 text-slate-500">Belum ada data produk terlaris pada periode ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Prioritas Admin</h3>
                <p class="text-sm text-muted mb-0">Area penting yang sebaiknya ditindak lebih dulu.</p>
            </div>
        </div>
        <div class="p-6 pt-0">
            <div class="space-y-3 mb-5">
                <?php foreach ($attentionItems as $item): ?>
                    <div class="rounded-2xl border border-amber-100 bg-amber-50/70 px-4 py-4">
                        <div class="font-semibold text-slate-900"><?php echo e((string) ($item['title'] ?? '')); ?></div>
                        <div class="text-sm text-slate-600 mt-1"><?php echo e((string) ($item['detail'] ?? '')); ?></div>
                        <a href="<?php echo e((string) ($item['url'] ?? '#')); ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 mt-3">
                            <?php echo e((string) ($item['action'] ?? 'Buka')); ?>
                            <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($attentionItems)): ?>
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-4 text-sm text-emerald-800">
                        Operasional sedang dalam kondisi aman. Lanjutkan monitoring rutin dan disiplin input data.
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="<?php echo e(base_url('admin_products.php')); ?>" class="btn btn-secondary justify-center">
                    <i data-lucide="package" class="w-4 h-4"></i>
                    Kelola Produk
                </a>
                <a href="<?php echo e(base_url('admin_inventory.php')); ?>" class="btn btn-secondary justify-center">
                    <i data-lucide="warehouse" class="w-4 h-4"></i>
                    Cek Inventori
                </a>
                <a href="<?php echo e(base_url('admin_users.php')); ?>" class="btn btn-secondary justify-center">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    Atur User
                </a>
                <a href="<?php echo e(base_url('admin_transactions.php')); ?>" class="btn btn-primary justify-center">
                    <i data-lucide="receipt" class="w-4 h-4"></i>
                    Audit Transaksi
                </a>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/../layouts/footer.php';
