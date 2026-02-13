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
        <h2 class="kp-page-title">Promo & Pajak</h2>
        <p class="kp-page-subtitle">Kelola voucher, pajak, service, dan pembulatan.</p>
    </div>
</div>

<div class="kp-card-flat p-4 mb-4">
    <div class="fw-semibold mb-2">Default Transaksi</div>
    <form method="POST" class="kp-filter-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_defaults">
        <div>
            <label class="form-label">Pajak (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="tax_percent" class="form-control" value="<?php echo e((string) ($defaults['tax_percent'] ?? 0)); ?>">
        </div>
        <div>
            <label class="form-label">Service (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="service_percent" class="form-control" value="<?php echo e((string) ($defaults['service_percent'] ?? 0)); ?>">
        </div>
        <div>
            <label class="form-label">Pembulatan</label>
            <select name="rounding_mode" class="form-select">
                <?php $roundingMode = $defaults['rounding_mode'] ?? 'none'; ?>
                <option value="none" <?php echo $roundingMode === 'none' ? 'selected' : ''; ?>>Tidak ada</option>
                <option value="nearest" <?php echo $roundingMode === 'nearest' ? 'selected' : ''; ?>>Terdekat</option>
                <option value="up" <?php echo $roundingMode === 'up' ? 'selected' : ''; ?>>Ke atas</option>
                <option value="down" <?php echo $roundingMode === 'down' ? 'selected' : ''; ?>>Ke bawah</option>
            </select>
        </div>
        <div>
            <label class="form-label">Kelipatan</label>
            <input type="number" min="1" step="1" name="rounding_unit" class="form-control" value="<?php echo e((string) ($defaults['rounding_unit'] ?? 100)); ?>">
        </div>
        <div>
            <button class="btn kp-btn-primary">
                <span class="material-icons-outlined">save</span>
                Simpan Default
            </button>
        </div>
    </form>
</div>

<div class="kp-card-flat p-4 mb-4">
    <div class="fw-semibold mb-2">Tambah Voucher</div>
    <form method="POST" class="kp-filter-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_voucher">
        <div>
            <label class="form-label">Kode</label>
            <input type="text" name="code" class="form-control" placeholder="HEMAT10" required>
        </div>
        <div>
            <label class="form-label">Tipe</label>
            <select name="type" class="form-select">
                <option value="amount">Nominal</option>
                <option value="percent">Persen (%)</option>
            </select>
        </div>
        <div>
            <label class="form-label">Nilai</label>
            <input type="number" step="0.01" min="0" name="value" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Min Total</label>
            <input type="number" step="0.01" min="0" name="min_total" class="form-control">
        </div>
        <div>
            <label class="form-label">Maks Diskon</label>
            <input type="number" step="0.01" min="0" name="max" class="form-control">
        </div>
        <div>
            <label class="form-label">Kadaluarsa</label>
            <input type="date" name="expires" class="form-control">
        </div>
        <div>
            <label class="form-label">Nama Promo</label>
            <input type="text" name="name" class="form-control" placeholder="Promo Weekend">
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="active" id="voucher_active" checked>
            <label class="form-check-label" for="voucher_active">Aktif</label>
        </div>
        <div>
            <button class="btn kp-btn-primary">
                <span class="material-icons-outlined">add</span>
                Tambah Voucher
            </button>
        </div>
    </form>
</div>

<div class="kp-card-flat p-4">
    <div class="fw-semibold mb-2">Daftar Voucher</div>
    <?php if (!empty($vouchers)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Tipe</th>
                        <th>Nilai</th>
                        <th>Min Total</th>
                        <th>Maks</th>
                        <th>Kadaluarsa</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vouchers as $voucher): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo e($voucher['code'] ?? '-'); ?></div>
                                <?php if (!empty($voucher['name'])): ?>
                                    <div class="kp-muted small"><?php echo e($voucher['name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e(strtoupper((string) ($voucher['type'] ?? 'amount'))); ?></td>
                            <td>
                                <?php if (($voucher['type'] ?? 'amount') === 'percent'): ?>
                                    <?php echo e((string) ($voucher['value'] ?? 0)); ?>%
                                <?php else: ?>
                                    <?php echo e(format_rupiah((float) ($voucher['value'] ?? 0))); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e(format_rupiah((float) ($voucher['min_total'] ?? 0))); ?></td>
                            <td><?php echo e(format_rupiah((float) ($voucher['max'] ?? 0))); ?></td>
                            <td><?php echo e($voucher['expires'] ?? '-'); ?></td>
                            <td>
                                <span class="badge rounded-pill <?php echo !empty($voucher['active']) ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?php echo !empty($voucher['active']) ? 'Aktif' : 'Nonaktif'; ?>
                                </span>
                            </td>
                            <td class="d-flex gap-2">
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_voucher">
                                    <input type="hidden" name="code" value="<?php echo e($voucher['code'] ?? ''); ?>">
                                    <button class="btn kp-btn-ghost btn-sm">Toggle</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Hapus voucher ini?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_voucher">
                                    <input type="hidden" name="code" value="<?php echo e($voucher['code'] ?? ''); ?>">
                                    <button class="btn kp-btn-ghost btn-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="kp-muted">Belum ada voucher.</div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
