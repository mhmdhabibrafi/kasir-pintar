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

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?php echo e($success); ?>
    </div>
<?php endif; ?>

<div class="kp-page-header align-items-center">
    <div class="text-center text-md-start w-100">
        <h2 class="kp-page-title">Shift & Kas</h2>
        <p class="kp-page-subtitle">
            <?php echo $canManageCash ? 'Kelola buka/tutup shift dan arus kas.' : 'Absensi shift harian dan lihat ringkasan kas.'; ?>
        </p>
    </div>
</div>

<?php if (empty($activeShift)): ?>
    <div class="kp-card-flat p-4 mb-4">
        <div class="fw-semibold mb-2 text-center text-md-start">Buka Shift Baru</div>
        <?php if ($canManageCash): ?>
            <form method="POST" class="kp-form-grid">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="open">
                <div>
                    <label class="form-label">Kas Awal</label>
                    <input type="number" step="0.01" min="0" name="opening_cash" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Catatan (opsional)</label>
                    <input type="text" name="note" class="form-control" placeholder="Contoh: uang pecahan">
                </div>
                <div class="kp-form-full kp-form-actions justify-content-md-end">
                    <button class="btn kp-btn-primary">
                        <span class="material-icons-outlined">login</span>
                        Buka Shift
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="kp-muted mb-3">Kas harian hanya dapat diinput oleh admin/bos.</div>
            <form method="POST" class="d-flex justify-content-center justify-content-md-end">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="open">
                <button class="btn kp-btn-primary">
                    <span class="material-icons-outlined">login</span>
                    Mulai Shift
                </button>
            </form>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="kp-card-flat p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div class="text-center text-md-start w-100">
                <div class="fw-semibold">Shift Aktif</div>
                <div class="kp-muted small">ID: <?php echo e($activeShift['shift_id'] ?? '-'); ?></div>
                <div class="kp-muted small">Mulai: <?php echo e($activeShift['opened_at'] ?? '-'); ?></div>
            </div>
            <div class="text-center text-md-end">
                <div class="kp-muted small">Estimasi Kas Drawer</div>
                <div class="fw-semibold"><?php echo e(format_rupiah((float) $shiftBalance)); ?></div>
            </div>
        </div>
        <?php if (!empty($shiftSummary)): ?>
            <div class="kp-grid kp-grid-3 mt-3">
                <div class="kp-card-flat p-3">
                    <div class="kp-muted small">Transaksi</div>
                    <div class="fw-semibold"><?php echo e((string) ($shiftSummary['transactions'] ?? 0)); ?></div>
                </div>
                <div class="kp-card-flat p-3">
                    <div class="kp-muted small">Sales Cash</div>
                    <div class="fw-semibold"><?php echo e(format_rupiah((float) ($shiftSummary['sales_cash'] ?? 0))); ?></div>
                </div>
                <div class="kp-card-flat p-3">
                    <div class="kp-muted small">Sales QRIS</div>
                    <div class="fw-semibold"><?php echo e(format_rupiah((float) ($shiftSummary['sales_qris'] ?? 0))); ?></div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($activeShift['movements'])): ?>
            <div class="kp-card-flat p-3 mt-3">
                <div class="fw-semibold mb-2 text-center text-md-start">Arus Kas</div>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($activeShift['movements'] as $move): ?>
                        <div class="d-flex justify-content-between small">
                            <div><?php echo e($move['type'] === 'in' ? 'Cash In' : 'Cash Out'); ?> &middot; <?php echo e($move['note'] ?? '-'); ?></div>
                            <div class="fw-semibold"><?php echo e(format_rupiah((float) ($move['amount'] ?? 0))); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php if ($canManageCash): ?>
    <?php if (!empty($activeShifts)): ?>
        <?php
            $defaultTarget = $activeShift['shift_id'] ?? ($activeShifts[0]['shift_id'] ?? '');
        ?>
        <div class="kp-grid kp-grid-2 mb-4">
            <div class="kp-card-flat p-4">
                <div class="fw-semibold mb-2 text-center text-md-start">Cash In</div>
                <form method="POST" class="kp-form-grid">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="cash_in">
                    <div class="kp-form-full">
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
                        <input type="text" name="note" class="form-control" placeholder="Contoh: tambah uang kecil">
                    </div>
                    <div class="kp-form-full kp-form-actions justify-content-md-end">
                        <button class="btn kp-btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
            <div class="kp-card-flat p-4">
                <div class="fw-semibold mb-2 text-center text-md-start">Cash Out</div>
                <form method="POST" class="kp-form-grid">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="cash_out">
                    <div class="kp-form-full">
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
                        <input type="text" name="note" class="form-control" placeholder="Contoh: setor ke brankas">
                    </div>
                    <div class="kp-form-full kp-form-actions justify-content-md-end">
                        <button class="btn kp-btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="kp-card-flat p-4 mb-4">
            <div class="kp-muted text-center text-md-start">
                Tidak ada shift aktif untuk dicatat kas masuk/keluar.
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="kp-card-flat p-4 mb-4">
        <div class="kp-muted text-center text-md-start">
            Input kas harian (cash in/out) hanya dapat dilakukan oleh admin/bos.
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($activeShift)): ?>
    <div class="kp-card-flat p-4 mb-4">
        <div class="fw-semibold mb-2 text-center text-md-start">Tutup Shift</div>
        <?php if ($canManageCash): ?>
            <form method="POST" class="kp-form-grid">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="close">
                <div>
                    <label class="form-label">Kas Akhir (hasil hitung)</label>
                    <input type="number" step="0.01" min="0" name="closing_cash" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Catatan</label>
                    <input type="text" name="note" class="form-control" placeholder="Contoh: selisih +2.000">
                </div>
                <div class="kp-form-full kp-form-actions justify-content-md-end">
                    <button class="btn kp-btn-primary">
                        <span class="material-icons-outlined">logout</span>
                        Tutup Shift
                    </button>
                </div>
            </form>
        <?php else: ?>
            <form method="POST" class="d-flex justify-content-center justify-content-md-end">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="close">
                <button class="btn kp-btn-primary">
                    <span class="material-icons-outlined">logout</span>
                    Selesai Shift
                </button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

    <div class="kp-card-flat p-4">
        <div class="fw-semibold mb-2 text-center text-md-start">Riwayat Shift</div>
    <?php if (!empty($history)): ?>
        <div class="table-responsive">
            <table class="table text-center align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kasir</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Kas Awal</th>
                        <th>Kas Akhir</th>
                        <th>Sales</th>
                        <th>Refund</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                        <?php $summary = $item['summary'] ?? []; ?>
                        <tr>
                            <td><?php echo e($item['shift_id'] ?? '-'); ?></td>
                            <td><?php echo e($item['user_name'] ?? '-'); ?></td>
                            <td><?php echo e($item['opened_at'] ?? '-'); ?></td>
                            <td><?php echo e($item['closed_at'] ?? '-'); ?></td>
                            <td><?php echo e(format_rupiah((float) ($item['opening_cash'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($item['closing_cash'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($summary['gross_total'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($summary['refund_total'] ?? 0))); ?></td>
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
