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

<section class="card p-0 mb-6">
    <div class="kp-sheet-toolbar">
        <div>
            <h2 class="kp-page-title">Member Pelanggan</h2>
            <p class="kp-page-subtitle"><?php echo $isAdmin ? 'Kelola basis pelanggan, poin loyalitas, dan histori belanja dalam format tabel yang rapi.' : 'Mode lihat saja untuk ringkasan member outlet.'; ?></p>
        </div>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCustomerCreate">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                Tambah Member
            </button>
        <?php endif; ?>
    </div>
</section>

<section class="kpi-grid mb-6">
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Member Tampil</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($summary['visible_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Total database: <?php echo e((string) ($summary['all_total'] ?? 0)); ?> member.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Member Aktif</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($summary['active'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Masih bisa dipakai di kasir.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Total Poin</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e(number_format((int) ($summary['points'] ?? 0), 0, ',', '.')); ?></div>
        <div class="text-sm text-slate-500 mt-2">Akumulasi dari hasil filter.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Total Belanja</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e(format_rupiah((float) ($summary['spent'] ?? 0))); ?></div>
        <div class="text-sm text-slate-500 mt-2">Nilai transaksi member tersaring.</div>
    </div>
</section>

<section class="card kp-filter-card mb-6">
    <form method="GET" class="kp-filter-form">
        <div class="form-group mb-0">
            <label class="form-label">Cari Member</label>
            <input type="text" name="q" class="form-input" value="<?php echo e((string) ($customerFilters['q'] ?? '')); ?>" placeholder="Nama, no. HP, atau email">
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
                <option value="all" <?php echo (($customerFilters['status'] ?? 'all') === 'all') ? 'selected' : ''; ?>>Semua status</option>
                <option value="active" <?php echo (($customerFilters['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Aktif</option>
                <option value="inactive" <?php echo (($customerFilters['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Nonaktif</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-3 items-end">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>
                Terapkan
            </button>
            <a href="<?php echo e(base_url('admin_customers.php')); ?>" class="btn btn-secondary">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                Reset
            </a>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Daftar Member</h3>
            <p class="text-sm text-muted mb-0">Tabel rapi untuk cek status loyalitas, kunjungan, dan total belanja pelanggan.</p>
        </div>
        <?php if (($summary['visible_total'] ?? 0) !== ($summary['all_total'] ?? 0)): ?>
            <span class="badge badge-neutral">Menampilkan <?php echo e((string) ($summary['visible_total'] ?? 0)); ?> dari <?php echo e((string) ($summary['all_total'] ?? 0)); ?> member</span>
        <?php endif; ?>
    </div>
    <div class="p-0">
        <div class="table-wrapper border-0 rounded-none">
            <table class="data-table data-table--sheet">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Kontak</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Poin</th>
                        <th class="text-right">Kunjungan</th>
                        <th class="text-right">Total Belanja</th>
                        <th>Transaksi Terakhir</th>
                        <?php if ($isAdmin): ?>
                            <th class="text-right">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <?php
                        $customerId = (int) ($customer['id'] ?? 0);
                        $isActive = !empty($customer['is_active']);
                        ?>
                        <tr>
                            <td>
                                <div class="font-semibold text-slate-900"><?php echo e((string) ($customer['name'] ?? '-')); ?></div>
                                <?php if (!empty($customer['address'])): ?>
                                    <div class="text-xs text-slate-500 mt-1"><?php echo e((string) ($customer['address'] ?? '')); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo e((string) ($customer['phone'] ?? '-')); ?></div>
                                <div class="text-xs text-slate-500 mt-1"><?php echo e((string) ($customer['email'] ?? '-')); ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $isActive ? 'badge-success' : 'badge-neutral'; ?>">
                                    <?php echo $isActive ? 'Aktif' : 'Nonaktif'; ?>
                                </span>
                            </td>
                            <td class="text-right font-semibold text-amber-700"><?php echo e(number_format((int) ($customer['points'] ?? 0), 0, ',', '.')); ?></td>
                            <td class="text-right"><?php echo e((string) ((int) ($customer['total_visits'] ?? 0))); ?>x</td>
                            <td class="text-right font-semibold text-slate-900"><?php echo e(format_rupiah((float) ($customer['total_spent'] ?? 0))); ?></td>
                            <td>
                                <?php if (!empty($customer['last_transaction_at'])): ?>
                                    <div><?php echo e(date('d/m/Y', strtotime((string) $customer['last_transaction_at']))); ?></div>
                                    <div class="text-xs text-slate-500 mt-1"><?php echo e(date('H:i', strtotime((string) $customer['last_transaction_at']))); ?></div>
                                <?php else: ?>
                                    <span class="text-slate-400">Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        <a class="btn btn-secondary btn-sm" href="<?php echo e(base_url('admin_customers.php?edit=' . $customerId)); ?>">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            Edit
                                        </a>
                                        <form method="POST">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo e((string) $customerId); ?>">
                                            <input type="hidden" name="active" value="<?php echo $isActive ? 0 : 1; ?>">
                                            <button class="btn btn-secondary btn-sm" type="submit">
                                                <i data-lucide="<?php echo $isActive ? 'slash' : 'check-circle'; ?>" class="w-4 h-4"></i>
                                                <?php echo $isActive ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Hapus member ini?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo e((string) $customerId); ?>">
                                            <button class="btn btn-secondary btn-sm text-red-600 hover:text-red-700 hover:border-red-200 hover:bg-red-50" type="submit">
                                                <i data-lucide="trash" class="w-4 h-4"></i>
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="<?php echo $isAdmin ? '8' : '7'; ?>" class="text-center py-10 text-slate-500">Tidak ada member yang cocok dengan filter saat ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php if ($isAdmin): ?>
    <div class="modal fade" id="modalCustomerCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-2xl shadow-lg">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header border-b border-border p-5">
                        <h5 class="modal-title font-bold">Tambah Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-6">
                        <div class="row g-4">
                            <div class="col-md-6 form-group mb-0">
                                <label class="form-label">Nama</label>
                                <input type="text" name="name" class="form-input w-full" required>
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label class="form-label">No. HP</label>
                                <input type="text" name="phone" class="form-input w-full" placeholder="08xxxxxxxxxx">
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-input w-full">
                            </div>
                            <div class="col-md-6 form-group mb-0">
                                <label class="form-label">Status</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="is_active_create" name="is_active" checked>
                                    <label class="form-check-label" for="is_active_create">Member aktif</label>
                                </div>
                            </div>
                            <div class="col-12 form-group mb-0">
                                <label class="form-label">Alamat</label>
                                <textarea name="address" rows="2" class="form-input w-full"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-t border-border p-5">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($editCustomer)): ?>
        <div class="modal fade" id="modalCustomerEdit" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 rounded-2xl shadow-lg">
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo (int) ($editCustomer['id'] ?? 0); ?>">
                        <div class="modal-header border-b border-border p-5">
                            <h5 class="modal-title font-bold">Edit Member</h5>
                            <a class="btn-close" href="<?php echo e(base_url('admin_customers.php')); ?>" aria-label="Close"></a>
                        </div>
                        <div class="modal-body p-6">
                            <div class="row g-4">
                                <div class="col-md-6 form-group mb-0">
                                    <label class="form-label">Nama</label>
                                    <input type="text" name="name" class="form-input w-full" value="<?php echo e((string) ($editCustomer['name'] ?? '')); ?>" required>
                                </div>
                                <div class="col-md-6 form-group mb-0">
                                    <label class="form-label">No. HP</label>
                                    <input type="text" name="phone" class="form-input w-full" value="<?php echo e((string) ($editCustomer['phone'] ?? '')); ?>">
                                </div>
                                <div class="col-md-6 form-group mb-0">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-input w-full" value="<?php echo e((string) ($editCustomer['email'] ?? '')); ?>">
                                </div>
                                <div class="col-md-6 form-group mb-0">
                                    <label class="form-label">Status</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="is_active_edit" name="is_active" <?php echo !empty($editCustomer['is_active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active_edit">Member aktif</label>
                                    </div>
                                </div>
                                <div class="col-12 form-group mb-0">
                                    <label class="form-label">Alamat</label>
                                    <textarea name="address" rows="2" class="form-input w-full"><?php echo e((string) ($editCustomer['address'] ?? '')); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-t border-border p-5">
                            <a class="btn btn-secondary" href="<?php echo e(base_url('admin_customers.php')); ?>">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            window.addEventListener('load', () => {
                const modalEl = document.getElementById('modalCustomerEdit');
                if (!modalEl || typeof bootstrap === 'undefined') {
                    return;
                }
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        </script>
    <?php endif; ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/../layouts/footer.php';
