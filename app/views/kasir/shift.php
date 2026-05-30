<?php
require_once __DIR__ . '/../layouts/header.php';

$activeSummary = is_array($shiftSummary ?? null) ? $shiftSummary : shift_empty_summary();
$movementTotals = is_array($shiftMovementTotals ?? null) ? $shiftMovementTotals : ['cash_in' => 0.0, 'cash_out' => 0.0];
$cashInTotal = (float) ($movementTotals['cash_in'] ?? 0);
$cashOutTotal = (float) ($movementTotals['cash_out'] ?? 0);
$salesCash = (float) ($activeSummary['sales_cash'] ?? 0);
$salesQris = (float) ($activeSummary['sales_qris'] ?? 0);
$refundCash = (float) ($activeSummary['refund_cash'] ?? 0);
$transactionCount = (int) ($activeSummary['transactions'] ?? 0);
$expectedCashValue = (float) ($shiftBalance ?? 0);
$openingValue = (float) ($openingCashSuggestion ?? 0);
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

<div class="kp-page-header align-items-center">
    <div class="text-center text-md-start w-100">
        <h2 class="kp-page-title">Shift & Kas</h2>
        <p class="kp-page-subtitle">
            <?php echo $canManageCash ? 'Buka shift, pantau kas drawer, dan rekonsiliasi tutup shift.' : 'Mulai dan akhiri shift kasir.'; ?>
        </p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="kp-card-flat p-4 h-100">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill <?php echo empty($activeShift) ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success-emphasis'; ?>">
                        <span class="material-icons-outlined fs-6"><?php echo empty($activeShift) ? 'lock_clock' : 'check_circle'; ?></span>
                        <span class="fw-semibold"><?php echo empty($activeShift) ? 'Shift belum aktif' : 'Shift aktif'; ?></span>
                    </div>
                    <?php if (!empty($activeShift)): ?>
                        <div class="mt-3 fw-semibold"><?php echo e($activeShift['shift_id'] ?? '-'); ?></div>
                        <div class="kp-muted small">Kasir: <?php echo e($activeShift['user_name'] ?? '-'); ?> &middot; Mulai: <?php echo e($activeShift['opened_at'] ?? '-'); ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($activeShift)): ?>
                    <div class="text-md-end">
                        <div class="kp-muted small">Estimasi Kas Drawer</div>
                        <div class="fs-4 fw-semibold"><?php echo e(format_rupiah($expectedCashValue)); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($activeShift)): ?>
                <div class="mt-4 pt-3 border-top">
                    <?php if ($canManageCash): ?>
                        <form method="POST" class="kp-form-grid">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="open">
                            <div>
                                <label class="form-label">Kas Awal</label>
                                <input type="number" step="0.01" min="0" name="opening_cash" class="form-control" value="<?php echo e(number_format($openingValue, 2, '.', '')); ?>" required>
                            </div>
                            <div>
                                <label class="form-label">Catatan</label>
                                <input type="text" name="note" class="form-control" placeholder="Opsional">
                            </div>
                            <div class="kp-form-full kp-form-actions justify-content-md-end">
                                <button class="btn kp-btn-primary" data-testid="shift-open-submit">
                                    <span class="material-icons-outlined">login</span>
                                    Buka Shift
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="POST" class="d-flex justify-content-center justify-content-md-end">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="open">
                            <button class="btn kp-btn-primary" data-testid="shift-open-submit">
                                <span class="material-icons-outlined">login</span>
                                Mulai Shift
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row g-3 mt-3">
                    <div class="col-6 col-xl-3">
                        <div class="border-start ps-3 py-2 h-100">
                            <div class="kp-muted small">Transaksi</div>
                            <div class="fw-semibold"><?php echo e((string) $transactionCount); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border-start ps-3 py-2 h-100">
                            <div class="kp-muted small">Sales Cash</div>
                            <div class="fw-semibold"><?php echo e(format_rupiah($salesCash)); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border-start ps-3 py-2 h-100">
                            <div class="kp-muted small">Refund Cash</div>
                            <div class="fw-semibold text-danger"><?php echo e(format_rupiah($refundCash)); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border-start ps-3 py-2 h-100">
                            <div class="kp-muted small">Sales QRIS</div>
                            <div class="fw-semibold"><?php echo e(format_rupiah($salesQris)); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border-start ps-3 py-2 h-100">
                            <div class="kp-muted small">Kas Awal</div>
                            <div class="fw-semibold"><?php echo e(format_rupiah((float) ($activeShift['opening_cash'] ?? 0))); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border-start ps-3 py-2 h-100">
                            <div class="kp-muted small">Cash In</div>
                            <div class="fw-semibold"><?php echo e(format_rupiah($cashInTotal)); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border-start ps-3 py-2 h-100">
                            <div class="kp-muted small">Cash Out</div>
                            <div class="fw-semibold"><?php echo e(format_rupiah($cashOutTotal)); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border-start ps-3 py-2 h-100">
                            <div class="kp-muted small">Estimasi Akhir</div>
                            <div class="fw-semibold text-success"><?php echo e(format_rupiah($expectedCashValue)); ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($activeShift['movements'])): ?>
                    <div class="mt-4 pt-3 border-top">
                        <div class="fw-semibold mb-2 text-center text-md-start">Arus Kas Shift Ini</div>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach (array_reverse($activeShift['movements']) as $move): ?>
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-1 small">
                                    <div>
                                        <span class="fw-semibold"><?php echo e(($move['type'] ?? '') === 'in' ? 'Cash In' : 'Cash Out'); ?></span>
                                        <span class="kp-muted">&middot; <?php echo e($move['time'] ?? '-'); ?></span>
                                        <?php if (trim((string) ($move['note'] ?? '')) !== ''): ?>
                                            <span class="kp-muted">&middot; <?php echo e($move['note'] ?? ''); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fw-semibold"><?php echo e(format_rupiah((float) ($move['amount'] ?? 0))); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="kp-card-flat p-4 h-100">
            <?php if (empty($activeShift)): ?>
                <div class="fw-semibold mb-2 text-center text-md-start">POS Terkunci</div>
                <div class="kp-muted small text-center text-md-start mb-3">Transaksi akan aktif setelah shift dibuka.</div>
                <a class="btn kp-btn-ghost w-100" href="<?php echo e(base_url('kasir.php')); ?>">
                    <span class="material-icons-outlined">point_of_sale</span>
                    Ke Kasir
                </a>
            <?php else: ?>
                <div class="fw-semibold mb-2 text-center text-md-start">Tutup Shift</div>
                <?php if ($canManageCash): ?>
                    <form method="POST" class="d-flex flex-column gap-3">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="close">
                        <div>
                            <label class="form-label">Kas Akhir Fisik</label>
                            <input type="number" step="0.01" min="0" name="closing_cash" class="form-control" value="<?php echo e(number_format($expectedCashValue, 2, '.', '')); ?>" required>
                            <div class="kp-muted small mt-1">Estimasi sistem: <?php echo e(format_rupiah($expectedCashValue)); ?></div>
                        </div>
                        <div>
                            <label class="form-label">Catatan</label>
                            <input type="text" name="note" class="form-control" placeholder="Opsional">
                        </div>
                        <button class="btn kp-btn-primary w-100" data-testid="shift-close-submit">
                            <span class="material-icons-outlined">logout</span>
                            Tutup Shift
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="d-flex flex-column gap-3">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="close">
                        <div class="border-start ps-3 py-2">
                            <div class="kp-muted small">Estimasi Kas</div>
                            <div class="fw-semibold"><?php echo e(format_rupiah($expectedCashValue)); ?></div>
                        </div>
                        <button class="btn kp-btn-primary w-100" data-testid="shift-close-submit">
                            <span class="material-icons-outlined">logout</span>
                            Selesai Shift
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canManageCash): ?>
    <div class="kp-card-flat p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
            <div>
                <div class="fw-semibold text-center text-md-start">Arus Kas</div>
                <div class="kp-muted small text-center text-md-start">Cash in/out untuk shift aktif.</div>
            </div>
        </div>
        <?php if (!empty($activeShifts)): ?>
            <?php $defaultTarget = $activeShift['shift_id'] ?? ($activeShifts[0]['shift_id'] ?? ''); ?>
            <form method="POST" class="kp-form-grid">
                <?php echo csrf_field(); ?>
                <div class="kp-form-full">
                    <div class="btn-group w-100" role="group" aria-label="Jenis arus kas">
                        <input type="radio" class="btn-check" name="action" id="cashInAction" value="cash_in" checked>
                        <label class="btn kp-btn-ghost" for="cashInAction">
                            <span class="material-icons-outlined">add_card</span>
                            Cash In
                        </label>
                        <input type="radio" class="btn-check" name="action" id="cashOutAction" value="cash_out">
                        <label class="btn kp-btn-ghost" for="cashOutAction">
                            <span class="material-icons-outlined">payments</span>
                            Cash Out
                        </label>
                    </div>
                </div>
                <div>
                    <label class="form-label">Shift Target</label>
                    <select class="form-select" name="target_shift_id" required>
                        <?php foreach ($activeShifts as $shift): ?>
                            <?php $shiftCode = (string) ($shift['shift_id'] ?? ''); ?>
                            <option value="<?php echo e($shiftCode); ?>" <?php echo $shiftCode === $defaultTarget ? 'selected' : ''; ?>>
                                <?php echo e($shiftCode); ?> - <?php echo e($shift['user_name'] ?? 'Kasir'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Nominal</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Catatan</label>
                    <input type="text" name="note" class="form-control" placeholder="Opsional">
                </div>
                <div class="kp-form-actions justify-content-md-end">
                    <button class="btn kp-btn-primary">
                        <span class="material-icons-outlined">save</span>
                        Simpan
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="kp-muted text-center text-md-start">Tidak ada shift aktif.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="kp-card-flat p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
        <div>
            <div class="fw-semibold text-center text-md-start">Riwayat Shift</div>
            <div class="kp-muted small text-center text-md-start">Data terbaru tampil paling atas.</div>
        </div>
    </div>
    <?php if (!empty($history)): ?>
        <div class="table-responsive">
            <table class="table text-center align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kasir</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Estimasi</th>
                        <th>Kas Akhir</th>
                        <th>Selisih</th>
                        <th>Sales QRIS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                        <?php
                            $summary = is_array($item['summary'] ?? null) ? $item['summary'] : shift_empty_summary();
                            $expected = shift_expected_cash($item, $summary);
                            $closing = isset($item['closing_cash']) ? (float) $item['closing_cash'] : null;
                            $diff = $closing === null ? null : $closing - $expected;
                            $diffClass = $diff === null ? 'text-muted' : (abs($diff) > 0.0001 ? 'text-danger fw-semibold' : 'text-success fw-semibold');
                        ?>
                        <tr>
                            <td class="font-monospace small"><?php echo e($item['shift_id'] ?? '-'); ?></td>
                            <td><?php echo e($item['user_name'] ?? '-'); ?></td>
                            <td><?php echo e($item['opened_at'] ?? '-'); ?></td>
                            <td><?php echo e($item['closed_at'] ?? '-'); ?></td>
                            <td><?php echo e(format_rupiah($expected)); ?></td>
                            <td><?php echo $closing === null ? '<span class="text-muted">-</span>' : e(format_rupiah($closing)); ?></td>
                            <td class="<?php echo e($diffClass); ?>"><?php echo $diff === null ? '-' : e(format_rupiah((float) $diff)); ?></td>
                            <td><?php echo e(format_rupiah((float) ($summary['sales_qris'] ?? 0))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="kp-muted">Belum ada riwayat shift.</div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
