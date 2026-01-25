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

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title">Shift & Kas</h2>
        <p class="kp-page-subtitle">Kelola buka/tutup shift dan arus kas.</p>
    </div>
</div>

<?php if (empty($activeShift)): ?>
    <div class="kp-card-flat p-4 mb-4">
        <div class="fw-semibold mb-2">Buka Shift Baru</div>
        <form method="POST" class="kp-filter-form">
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
            <div>
                <button class="btn kp-btn-primary">
                    <span class="material-icons-outlined">login</span>
                    Buka Shift
                </button>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="kp-card-flat p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <div class="fw-semibold">Shift Aktif</div>
                <div class="kp-muted small">ID: <?php echo e($activeShift['shift_id'] ?? '-'); ?></div>
                <div class="kp-muted small">Mulai: <?php echo e($activeShift['opened_at'] ?? '-'); ?></div>
            </div>
            <div class="text-md-end">
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
                <div class="fw-semibold mb-2">Arus Kas</div>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($activeShift['movements'] as $move): ?>
                        <div class="d-flex justify-content-between small">
                            <div><?php echo e($move['type'] === 'in' ? 'Cash In' : 'Cash Out'); ?> · <?php echo e($move['note'] ?? '-'); ?></div>
                            <div class="fw-semibold"><?php echo e(format_rupiah((float) ($move['amount'] ?? 0))); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="kp-grid kp-grid-2 mb-4">
        <div class="kp-card-flat p-4">
            <div class="fw-semibold mb-2">Cash In</div>
            <form method="POST" class="kp-filter-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="cash_in">
                <div>
                    <label class="form-label">Nominal</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Catatan</label>
                    <input type="text" name="note" class="form-control" placeholder="Contoh: tambah uang kecil">
                </div>
                <div>
                    <button class="btn kp-btn-primary">Simpan</button>
                </div>
            </form>
        </div>
        <div class="kp-card-flat p-4">
            <div class="fw-semibold mb-2">Cash Out</div>
            <form method="POST" class="kp-filter-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="cash_out">
                <div>
                    <label class="form-label">Nominal</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Catatan</label>
                    <input type="text" name="note" class="form-control" placeholder="Contoh: setor ke brankas">
                </div>
                <div>
                    <button class="btn kp-btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="kp-card-flat p-4 mb-4">
        <div class="fw-semibold mb-2">Tutup Shift</div>
        <form method="POST" class="kp-filter-form">
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
            <div>
                <button class="btn kp-btn-primary">
                    <span class="material-icons-outlined">logout</span>
                    Tutup Shift
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="kp-card-flat p-4">
    <div class="fw-semibold mb-2">Riwayat Shift</div>
    <?php if (!empty($history)): ?>
        <div class="table-responsive">
            <table class="table">
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
                    <?php foreach (array_reverse($history) as $item): ?>
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
