<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$cashReportSummary = $cashReportSummary ?? [
    'range_days' => 0,
    'net_cash' => 0,
    'qris_sales' => 0,
    'variance_shift_count' => 0,
    'open_shift_count' => 0,
    'peak_day' => null,
    'peak_day_sales' => 0,
    'top_cashier_name' => '-',
    'top_cashier_sales' => 0,
];
$peakDayRow = is_array($cashReportSummary['peak_day'] ?? null) ? $cashReportSummary['peak_day'] : null;
$peakDayLabel = trim((string) ($peakDayRow['date'] ?? '')) !== '' ? (string) $peakDayRow['date'] : '-';
?>

<?php if (!empty($errors ?? [])): ?>
    <div class="alert alert-error mb-6">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card mb-8 border-0 shadow-none bg-transparent">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="card-title text-2xl mb-1">Kas Harian</h2>
            <p class="text-sm text-muted mb-0">Ringkasan cash vs QRIS per shift dan rekap harian otomatis.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn btn-secondary" href="<?php echo e(base_url('admin_cash_report_csv.php?start_date=' . urlencode($filters['start_date'] ?? '') . '&end_date=' . urlencode($filters['end_date'] ?? ''))); ?>">
                <i data-lucide="download" class="w-4 h-4"></i>
                Export CSV
            </a>
            <a class="btn btn-secondary" href="<?php echo e(base_url('admin_cash_report_pdf.php?start_date=' . urlencode($filters['start_date'] ?? '') . '&end_date=' . urlencode($filters['end_date'] ?? ''))); ?>" target="_blank">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Export PDF
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="card p-6 border-l-4 border-l-emerald-500">
        <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2">Net Cash</div>
        <div class="text-3xl font-bold text-slate-900 mb-1"><?php echo e(format_rupiah((float) ($cashReportSummary['net_cash'] ?? 0))); ?></div>
        <div class="text-xs text-muted leading-relaxed">Akumulasi sales cash, cash in/out, dan refund.</div>
    </div>
    <div class="card p-6 border-l-4 border-l-blue-500">
        <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2">Sales QRIS</div>
        <div class="text-3xl font-bold text-slate-900 mb-1"><?php echo e(format_rupiah((float) ($cashReportSummary['qris_sales'] ?? 0))); ?></div>
        <div class="text-xs text-muted leading-relaxed">Nilai pembayaran non-tunai yang masuk pada periode.</div>
    </div>
    <div class="card p-6 border-l-4 <?php echo ((int)($cashReportSummary['variance_shift_count'] ?? 0) > 0) ? 'border-l-amber-500' : 'border-l-slate-300'; ?>">
        <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2">Anomali Shift</div>
        <div class="text-3xl font-bold text-slate-900 mb-1"><?php echo e((string) ($cashReportSummary['variance_shift_count'] ?? 0)); ?></div>
        <div class="text-xs text-muted leading-relaxed">Jumlah shift dengan selisih kas tidak nol.</div>
    </div>
    <div class="card p-6 border-l-4 border-l-purple-500">
        <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2">Kasir Teraktif</div>
        <div class="text-2xl font-bold text-slate-900 mb-1 truncate"><?php echo e((string) ($cashReportSummary['top_cashier_name'] ?? '-')); ?></div>
        <div class="text-xs text-muted leading-relaxed">Omzet tertinggi: <span class="font-semibold text-slate-700"><?php echo e(format_rupiah((float) ($cashReportSummary['top_cashier_sales'] ?? 0))); ?></span></div>
    </div>
</div>

<div class="flex flex-wrap gap-2 mb-8">
    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-sm font-medium text-slate-700 shadow-sm">
        <i data-lucide="calendar" class="w-4 h-4 text-blue-500"></i>
        <?php echo e((string) ($cashReportSummary['range_days'] ?? 0)); ?> hari dipantau
    </div>
    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-sm font-medium text-slate-700 shadow-sm">
        <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
        Hari terpadat: <?php echo e($peakDayLabel); ?> (<?php echo e(format_rupiah((float) ($cashReportSummary['peak_day_sales'] ?? 0))); ?>)
    </div>
    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border text-sm font-medium shadow-sm <?php echo !empty($cashReportSummary['open_shift_count']) ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
        <i data-lucide="<?php echo !empty($cashReportSummary['open_shift_count']) ? 'alert-triangle' : 'check-circle'; ?>" class="w-4 h-4 <?php echo !empty($cashReportSummary['open_shift_count']) ? 'text-amber-500' : 'text-emerald-500'; ?>"></i>
        <?php echo e((string) ($cashReportSummary['open_shift_count'] ?? 0)); ?> shift masih terbuka
    </div>
</div>

<div class="card p-6 mb-8">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div class="form-group mb-0 flex-1 min-w-[200px]">
            <label class="form-label text-xs">Dari Tanggal</label>
            <input type="date" class="form-input w-full" name="start_date" value="<?php echo e($filters['start_date'] ?? ''); ?>">
        </div>
        <div class="form-group mb-0 flex-1 min-w-[200px]">
            <label class="form-label text-xs">Sampai Tanggal</label>
            <input type="date" class="form-input w-full" name="end_date" value="<?php echo e($filters['end_date'] ?? ''); ?>">
        </div>
        <div class="form-group mb-0">
            <button class="btn btn-primary w-full md:w-auto" type="submit">
                <i data-lucide="filter" class="w-4 h-4"></i>
                Terapkan Filter
            </button>
        </div>
    </form>
</div>

<div class="card overflow-hidden mb-8">
    <div class="p-5 border-b border-border bg-slate-50/50">
        <h3 class="font-bold text-slate-900 m-0 text-center md:text-left">Rekap Kas Harian</h3>
    </div>
    <?php if (!empty($dailyRecap)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-left">Tanggal</th>
                        <th class="text-right">Cash In</th>
                        <th class="text-right">Cash Out</th>
                        <th class="text-right">Sales Cash</th>
                        <th class="text-right">Refund Cash</th>
                        <th class="text-right text-emerald-700 bg-emerald-50">Net Cash</th>
                        <th class="text-right text-blue-700 bg-blue-50">Sales QRIS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyRecap as $row): ?>
                        <?php
                            $cashIn = (float) ($row['cash_in'] ?? 0);
                            $cashOut = (float) ($row['cash_out'] ?? 0);
                            $cashSales = (float) ($row['cash_sales'] ?? 0);
                            $refundCash = (float) ($row['refund_cash'] ?? 0);
                            $netCash = $cashSales - $refundCash + $cashIn - $cashOut;
                        ?>
                        <tr>
                            <td class="font-medium text-slate-900"><?php echo e($row['date'] ?? '-'); ?></td>
                            <td class="text-right"><?php echo e(format_rupiah($cashIn)); ?></td>
                            <td class="text-right"><?php echo e(format_rupiah($cashOut)); ?></td>
                            <td class="text-right"><?php echo e(format_rupiah($cashSales)); ?></td>
                            <td class="text-right text-red-600"><?php echo e(format_rupiah($refundCash)); ?></td>
                            <td class="text-right font-bold text-emerald-700 bg-emerald-50/30"><?php echo e(format_rupiah($netCash)); ?></td>
                            <td class="text-right font-bold text-blue-700 bg-blue-50/30"><?php echo e(format_rupiah((float) ($row['qris_sales'] ?? 0))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 font-bold">
                        <td>Total Periode</td>
                        <td class="text-right"><?php echo e(format_rupiah((float) ($dailyTotals['cash_in'] ?? 0))); ?></td>
                        <td class="text-right"><?php echo e(format_rupiah((float) ($dailyTotals['cash_out'] ?? 0))); ?></td>
                        <td class="text-right"><?php echo e(format_rupiah((float) ($dailyTotals['cash_sales'] ?? 0))); ?></td>
                        <td class="text-right text-red-600"><?php echo e(format_rupiah((float) ($dailyTotals['refund_cash'] ?? 0))); ?></td>
                        <?php
                            $totalNet = (float) ($dailyTotals['cash_sales'] ?? 0)
                                - (float) ($dailyTotals['refund_cash'] ?? 0)
                                + (float) ($dailyTotals['cash_in'] ?? 0)
                                - (float) ($dailyTotals['cash_out'] ?? 0);
                        ?>
                        <td class="text-right text-emerald-700"><?php echo e(format_rupiah($totalNet)); ?></td>
                        <td class="text-right text-blue-700"><?php echo e(format_rupiah((float) ($dailyTotals['qris_sales'] ?? 0))); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="p-10 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="inbox" class="w-8 h-8"></i>
            </div>
            <div class="font-bold text-slate-900 mb-1">Data Kosong</div>
            <div class="text-sm text-muted">Belum ada rekap data kas untuk rentang waktu ini.</div>
        </div>
    <?php endif; ?>
</div>

<div class="card overflow-hidden mb-8">
    <div class="p-5 border-b border-border bg-slate-50/50">
        <h3 class="font-bold text-slate-900 m-0 text-center md:text-left">Detail Kas per Shift</h3>
    </div>
    <?php if (!empty($shiftRows)): ?>
        <div class="table-responsive">
            <table class="data-table text-right whitespace-nowrap">
                <thead>
                    <tr>
                        <th class="text-left">Shift ID</th>
                        <th class="text-left">Kasir</th>
                        <th class="text-left">Waktu Buka</th>
                        <th class="text-left">Waktu Tutup</th>
                        <th>Kas Awal</th>
                        <th>Cash In</th>
                        <th>Cash Out</th>
                        <th>Sales Cash</th>
                        <th>Refund Cash</th>
                        <th class="text-emerald-700 bg-emerald-50">Ekspektasi Kas</th>
                        <th>Kas Akhir (Fisik)</th>
                        <th>Selisih Kas</th>
                        <th class="text-blue-700 bg-blue-50">Sales QRIS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shiftRows as $row): ?>
                        <?php
                            $diff = $row['diff_cash'];
                            $diffClass = $diff === null ? '' : ($diff == 0.0 ? 'text-emerald-600 font-bold' : 'text-red-600 font-bold bg-red-50');
                            $rowClass = $diff !== null && abs((float) $diff) > 0.0001 ? 'bg-orange-50/30' : '';
                        ?>
                        <tr class="<?php echo e($rowClass); ?>">
                            <td class="text-left font-mono text-xs"><?php echo e($row['shift_id'] ?? '-'); ?></td>
                            <td class="text-left font-medium text-slate-900">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-xs">
                                        <i data-lucide="user" class="w-3 h-3"></i>
                                    </div>
                                    <?php echo e($row['user_name'] ?? '-'); ?>
                                </div>
                            </td>
                            <td class="text-left text-sm"><?php echo e($row['opened_at'] ?? '-'); ?></td>
                            <td class="text-left text-sm"><?php echo e($row['closed_at'] ?? '-'); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['opening_cash'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['cash_in'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['cash_out'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['sales_cash'] ?? 0))); ?></td>
                            <td class="text-red-600"><?php echo e(format_rupiah((float) ($row['refund_cash'] ?? 0))); ?></td>
                            <td class="font-bold text-emerald-700 bg-emerald-50/30"><?php echo e(format_rupiah((float) ($row['expected_cash'] ?? 0))); ?></td>
                            <td class="font-medium text-slate-900"><?php echo $row['closing_cash'] === null ? '<span class="text-muted italic text-xs">Shift Terbuka</span>' : e(format_rupiah((float) $row['closing_cash'])); ?></td>
                            <td class="<?php echo $diffClass; ?>">
                                <?php echo $diff === null ? '-' : e(format_rupiah((float) $diff)); ?>
                            </td>
                            <td class="font-bold text-blue-700 bg-blue-50/30"><?php echo e(format_rupiah((float) ($row['sales_qris'] ?? 0))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 font-bold">
                        <td colspan="4" class="text-left text-slate-900">Total Akumulasi</td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['opening_cash'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['cash_in'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['cash_out'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['sales_cash'] ?? 0))); ?></td>
                        <td class="text-red-600"><?php echo e(format_rupiah((float) ($shiftTotals['refund_cash'] ?? 0))); ?></td>
                        <td class="text-emerald-700"><?php echo e(format_rupiah((float) ($shiftTotals['expected_cash'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['closing_cash'] ?? 0))); ?></td>
                        <td class="text-center">-</td>
                        <td class="text-blue-700"><?php echo e(format_rupiah((float) ($shiftTotals['sales_qris'] ?? 0))); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="p-10 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="clock" class="w-8 h-8"></i>
            </div>
            <div class="font-bold text-slate-900 mb-1">Tidak Ada Shift</div>
            <div class="text-sm text-muted">Belum ada data shift yang terekam pada rentang waktu ini.</div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
