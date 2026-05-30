<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-6">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success mb-6">
        <?php echo e($success); ?>
    </div>
<?php endif; ?>

<?php if (!$isAdmin): ?>
    <div class="alert alert-info mb-6">Mode lihat saja. Hanya admin yang dapat mengubah default transaksi dan voucher.</div>
<?php endif; ?>

<div class="card mb-8 border-0 shadow-none bg-transparent">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="card-title text-2xl mb-1">Promo & Pajak</h2>
            <p class="text-sm text-muted mb-0">Kelola voucher, pajak, service, dan pembulatan.</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-border">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i data-lucide="calculator" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 m-0">Default Transaksi</h3>
                <p class="text-xs text-muted m-0">Pengaturan pajak dan biaya tambahan</p>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_defaults">

            <div class="grid grid-cols-2 gap-5 mb-6">
                <div class="form-group mb-0">
                    <label class="form-label">Pajak (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_percent" class="form-input w-full" value="<?php echo e((string) ($defaults['tax_percent'] ?? 0)); ?>">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Service (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="service_percent" class="form-input w-full" value="<?php echo e((string) ($defaults['service_percent'] ?? 0)); ?>">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Pembulatan</label>
                    <select name="rounding_mode" class="form-input w-full">
                        <?php $roundingMode = $defaults['rounding_mode'] ?? 'none'; ?>
                        <option value="none" <?php echo $roundingMode === 'none' ? 'selected' : ''; ?>>Tidak ada</option>
                        <option value="nearest" <?php echo $roundingMode === 'nearest' ? 'selected' : ''; ?>>Terdekat</option>
                        <option value="up" <?php echo $roundingMode === 'up' ? 'selected' : ''; ?>>Ke atas</option>
                        <option value="down" <?php echo $roundingMode === 'down' ? 'selected' : ''; ?>>Ke bawah</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Kelipatan</label>
                    <input type="number" min="1" step="1" name="rounding_unit" class="form-input w-full" value="<?php echo e((string) ($defaults['rounding_unit'] ?? 100)); ?>">
                </div>
            </div>

            <button class="btn btn-primary w-full justify-center">
                <i data-lucide="save" class="w-4 h-4"></i>
                Simpan Default Transaksi
            </button>
        </form>
        <?php else: ?>
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-slate-50 border border-border">
                    <div class="text-xs text-muted">Pajak</div>
                    <div class="font-bold text-slate-900"><?php echo e((string) ($defaults['tax_percent'] ?? 0)); ?>%</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-border">
                    <div class="text-xs text-muted">Service</div>
                    <div class="font-bold text-slate-900"><?php echo e((string) ($defaults['service_percent'] ?? 0)); ?>%</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-border">
                    <div class="text-xs text-muted">Pembulatan</div>
                    <div class="font-bold text-slate-900"><?php echo e((string) ($defaults['rounding_mode'] ?? 'none')); ?></div>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-border">
                    <div class="text-xs text-muted">Kelipatan</div>
                    <div class="font-bold text-slate-900"><?php echo e((string) ($defaults['rounding_unit'] ?? 100)); ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card p-6">
        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-border">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <i data-lucide="ticket" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 m-0">Tambah Voucher</h3>
                <p class="text-xs text-muted m-0">Buat kode promo baru untuk pelanggan</p>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_voucher">

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="form-group mb-0 col-span-2">
                    <label class="form-label">Nama Promo</label>
                    <input type="text" name="name" class="form-input w-full" placeholder="Promo Weekend">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Kode</label>
                    <input type="text" name="code" class="form-input w-full font-mono uppercase" placeholder="HEMAT10" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Tipe Diskon</label>
                    <select name="type" class="form-input w-full">
                        <option value="amount">Nominal (Rp)</option>
                        <option value="percent">Persen (%)</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Nilai Diskon</label>
                    <input type="number" step="0.01" min="0" name="value" class="form-input w-full" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Maks. Diskon</label>
                    <input type="number" step="0.01" min="0" name="max" class="form-input w-full" placeholder="Rp">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Min. Pembelian</label>
                    <input type="number" step="0.01" min="0" name="min_total" class="form-input w-full" placeholder="Rp">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Kadaluarsa</label>
                    <input type="date" name="expires" class="form-input w-full">
                </div>
                <div class="form-group mb-0 col-span-2 mt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input class="form-check-input mt-0" type="checkbox" name="active" id="voucher_active" checked>
                        <span class="text-sm font-medium text-slate-700">Voucher Aktif</span>
                    </label>
                </div>
            </div>

            <button class="btn btn-primary w-full justify-center">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Tambah Voucher Baru
            </button>
        </form>
        <?php else: ?>
            <div class="alert alert-info">Voucher dapat dipantau pada daftar di bawah. Perubahan hanya tersedia untuk admin.</div>
        <?php endif; ?>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="p-5 border-b border-border flex justify-between items-center bg-slate-50/50">
        <h3 class="font-bold text-slate-900 m-0">Daftar Voucher</h3>
    </div>

    <?php if (!empty($vouchers)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kode Promo</th>
                        <th>Tipe Diskon</th>
                        <th>Nilai</th>
                        <th>Syarat & Ketentuan</th>
                        <th>Masa Berlaku</th>
                        <th>Status</th>
                        <?php if ($isAdmin): ?>
                            <th class="text-right">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vouchers as $voucher): ?>
                        <tr>
                            <td>
                                <div class="font-bold text-slate-900 uppercase font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-xs inline-block mb-1"><?php echo e($voucher['code'] ?? '-'); ?></div>
                                <?php if (!empty($voucher['name'])): ?>
                                    <div class="text-xs text-muted"><?php echo e($voucher['name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-neutral bg-slate-100 text-slate-600 border-0">
                                    <?php echo e((($voucher['type'] ?? 'amount') === 'percent') ? 'Persentase' : 'Nominal'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="font-bold text-slate-900">
                                    <?php if (($voucher['type'] ?? 'amount') === 'percent'): ?>
                                        <?php echo e((string) ($voucher['value'] ?? 0)); ?>%
                                    <?php else: ?>
                                        <?php echo e(format_rupiah((float) ($voucher['value'] ?? 0))); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-xs text-slate-600">
                                    Min: <span class="font-medium text-slate-900"><?php echo e(format_rupiah((float) ($voucher['min_total'] ?? 0))); ?></span>
                                </div>
                                <?php if (($voucher['type'] ?? 'amount') === 'percent' && !empty($voucher['max'])): ?>
                                    <div class="text-xs text-slate-600 mt-0.5">
                                        Maks: <span class="font-medium text-slate-900"><?php echo e(format_rupiah((float) ($voucher['max'] ?? 0))); ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($voucher['expires'])): ?>
                                    <div class="text-sm font-medium text-slate-700">
                                        <?php echo e(date('d M Y', strtotime($voucher['expires']))); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted text-xs italic">Tanpa batas</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo !empty($voucher['active']) ? 'badge-success' : 'badge-neutral'; ?>">
                                    <?php echo !empty($voucher['active']) ? 'Aktif' : 'Nonaktif'; ?>
                                </span>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        <form method="POST">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_voucher">
                                            <input type="hidden" name="code" value="<?php echo e($voucher['code'] ?? ''); ?>">
                                            <button class="btn btn-secondary w-8 h-8 p-0 flex items-center justify-center text-slate-500 hover:text-slate-900" title="Toggle Status">
                                                <i data-lucide="<?php echo !empty($voucher['active']) ? 'eye-off' : 'eye'; ?>" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Hapus voucher ini secara permanen?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_voucher">
                                            <input type="hidden" name="code" value="<?php echo e($voucher['code'] ?? ''); ?>">
                                            <button class="btn btn-secondary w-8 h-8 p-0 flex items-center justify-center text-red-500 hover:bg-red-50 hover:text-red-700 hover:border-red-200" title="Hapus Voucher">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="p-10 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="ticket" class="w-8 h-8"></i>
            </div>
            <div class="font-bold text-slate-900 mb-1">Belum ada voucher</div>
            <div class="text-sm text-muted">Voucher yang Anda buat akan muncul di sini.</div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
