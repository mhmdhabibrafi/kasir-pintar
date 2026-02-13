<?php
require_once __DIR__ . '/../layouts/header.php';
?>

<?php if (!empty($errors ?? [])): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="kp-page-header align-items-center">
    <div class="text-center text-md-start w-100">
        <h2 class="kp-page-title">Kas Harian</h2>
        <p class="kp-page-subtitle">Ringkasan cash vs QRIS per shift dan rekap harian otomatis.</p>
    </div>
    <div class="kp-page-actions justify-content-center justify-content-md-end w-100">
        <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin_cash_report_csv.php?start_date=' . urlencode($filters['start_date'] ?? '') . '&end_date=' . urlencode($filters['end_date'] ?? ''))); ?>">
            <span class="material-icons-outlined">download</span>
            Export CSV
        </a>
        <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin_cash_report_pdf.php?start_date=' . urlencode($filters['start_date'] ?? '') . '&end_date=' . urlencode($filters['end_date'] ?? ''))); ?>" target="_blank">
            <span class="material-icons-outlined">picture_as_pdf</span>
            Export PDF
        </a>
    </div>
</div>

<div class="kp-card-flat p-4 mb-4">
    <form method="GET" class="kp-filter-form">
        <div>
            <label class="form-label">Dari</label>
            <input type="date" class="form-control" name="start_date" value="<?php echo e($filters['start_date'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label">Sampai</label>
            <input type="date" class="form-control" name="end_date" value="<?php echo e($filters['end_date'] ?? ''); ?>">
        </div>
        <div>
            <button class="btn kp-btn-primary" type="submit">
                <span class="material-icons-outlined">filter_alt</span>
                Terapkan
            </button>
        </div>
    </form>
</div>

<div class="kp-card-flat p-4 mb-4">
    <div class="fw-semibold mb-2 text-center text-md-start">Rekap Kas Harian</div>
    <?php if (!empty($dailyRecap)): ?>
        <div class="table-responsive">
            <table class="table text-center align-middle">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Cash In</th>
                        <th>Cash Out</th>
                        <th>Sales Cash</th>
                        <th>Refund Cash</th>
                        <th>Net Cash</th>
                        <th>Sales QRIS</th>
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
                            <td><?php echo e($row['date'] ?? '-'); ?></td>
                            <td><?php echo e(format_rupiah($cashIn)); ?></td>
                            <td><?php echo e(format_rupiah($cashOut)); ?></td>
                            <td><?php echo e(format_rupiah($cashSales)); ?></td>
                            <td><?php echo e(format_rupiah($refundCash)); ?></td>
                            <td class="fw-semibold"><?php echo e(format_rupiah($netCash)); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['qris_sales'] ?? 0))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="fw-semibold">
                        <td>Total</td>
                        <td><?php echo e(format_rupiah((float) ($dailyTotals['cash_in'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($dailyTotals['cash_out'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($dailyTotals['cash_sales'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($dailyTotals['refund_cash'] ?? 0))); ?></td>
                        <?php
                            $totalNet = (float) ($dailyTotals['cash_sales'] ?? 0)
                                - (float) ($dailyTotals['refund_cash'] ?? 0)
                                + (float) ($dailyTotals['cash_in'] ?? 0)
                                - (float) ($dailyTotals['cash_out'] ?? 0);
                        ?>
                        <td><?php echo e(format_rupiah($totalNet)); ?></td>
                        <td><?php echo e(format_rupiah((float) ($dailyTotals['qris_sales'] ?? 0))); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="kp-muted text-center text-md-start">Belum ada data kas harian.</div>
    <?php endif; ?>
</div>

<div class="kp-card-flat p-4">
    <div class="fw-semibold mb-2 text-center text-md-start">Detail Kas per Shift</div>
    <?php if (!empty($shiftRows)): ?>
        <div class="table-responsive">
            <table class="table text-center align-middle">
                <thead>
                    <tr>
                        <th>Shift</th>
                        <th>Kasir</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Kas Awal</th>
                        <th>Cash In</th>
                        <th>Cash Out</th>
                        <th>Sales Cash</th>
                        <th>Refund Cash</th>
                        <th>Expected Cash</th>
                        <th>Kas Akhir</th>
                        <th>Selisih</th>
                        <th>Sales QRIS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shiftRows as $row): ?>
                        <?php
                            $diff = $row['diff_cash'];
                            $diffClass = $diff === null ? '' : ($diff == 0.0 ? 'text-success' : 'text-danger');
                        ?>
                        <tr>
                            <td><?php echo e($row['shift_id'] ?? '-'); ?></td>
                            <td><?php echo e($row['user_name'] ?? '-'); ?></td>
                            <td><?php echo e($row['opened_at'] ?? '-'); ?></td>
                            <td><?php echo e($row['closed_at'] ?? '-'); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['opening_cash'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['cash_in'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['cash_out'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['sales_cash'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($row['refund_cash'] ?? 0))); ?></td>
                            <td class="fw-semibold"><?php echo e(format_rupiah((float) ($row['expected_cash'] ?? 0))); ?></td>
                            <td><?php echo $row['closing_cash'] === null ? '-' : e(format_rupiah((float) $row['closing_cash'])); ?></td>
                            <td class="fw-semibold <?php echo $diffClass; ?>">
                                <?php echo $diff === null ? '-' : e(format_rupiah((float) $diff)); ?>
                            </td>
                            <td><?php echo e(format_rupiah((float) ($row['sales_qris'] ?? 0))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="fw-semibold">
                        <td colspan="4">Total</td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['opening_cash'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['cash_in'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['cash_out'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['sales_cash'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['refund_cash'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['expected_cash'] ?? 0))); ?></td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['closing_cash'] ?? 0))); ?></td>
                        <td>-</td>
                        <td><?php echo e(format_rupiah((float) ($shiftTotals['sales_qris'] ?? 0))); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="kp-muted text-center text-md-start">Belum ada data shift pada rentang ini.</div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
