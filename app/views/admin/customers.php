<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';
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
        <h2 class="kp-page-title">Member Pelanggan</h2>
        <p class="kp-page-subtitle"><?php echo $isAdmin ? 'Kelola data member dan poin loyalitas.' : 'Mode lihat saja.'; ?></p>
    </div>
    <?php if ($isAdmin): ?>
        <div class="kp-page-actions">
            <button class="btn kp-btn-primary" data-bs-toggle="modal" data-bs-target="#modalCustomerCreate">
                <span class="material-icons-outlined">person_add</span>
                Tambah Member
            </button>
        </div>
    <?php endif; ?>
</div>

<div class="kp-grid kp-grid-3 mb-4">
    <div class="kp-card-flat p-3">
        <div class="kp-kpi-label">Total Member</div>
        <div class="kp-kpi-value"><?php echo e((string) ($summary['total'] ?? 0)); ?></div>
    </div>
    <div class="kp-card-flat p-3">
        <div class="kp-kpi-label">Member Aktif</div>
        <div class="kp-kpi-value"><?php echo e((string) ($summary['active'] ?? 0)); ?></div>
    </div>
    <div class="kp-card-flat p-3">
        <div class="kp-kpi-label">Total Poin</div>
        <div class="kp-kpi-value"><?php echo e(number_format((int) ($summary['points'] ?? 0), 0, ',', '.')); ?></div>
    </div>
    <div class="kp-card-flat p-3">
        <div class="kp-kpi-label">Total Belanja</div>
        <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($summary['spent'] ?? 0))); ?></div>
    </div>
</div>

<div class="table-responsive">
    <table class="table kp-table align-middle">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Kontak</th>
                <th>Status</th>
                <th>Poin</th>
                <th>Kunjungan</th>
                <th>Total Belanja</th>
                <th>Transaksi Terakhir</th>
                <?php if ($isAdmin): ?>
                    <th>Aksi</th>
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
                        <div class="fw-semibold"><?php echo e($customer['name'] ?? '-'); ?></div>
                        <?php if (!empty($customer['address'])): ?>
                            <div class="kp-muted small"><?php echo e($customer['address']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div><?php echo e($customer['phone'] ?? '-'); ?></div>
                        <?php if (!empty($customer['email'])): ?>
                            <div class="kp-muted small"><?php echo e($customer['email']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge rounded-pill <?php echo $isActive ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                            <?php echo $isActive ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                    </td>
                    <td class="fw-semibold"><?php echo e(number_format((int) ($customer['points'] ?? 0), 0, ',', '.')); ?></td>
                    <td><?php echo e((string) ((int) ($customer['total_visits'] ?? 0))); ?>x</td>
                    <td><?php echo e(format_rupiah((float) ($customer['total_spent'] ?? 0))); ?></td>
                    <td>
                        <?php if (!empty($customer['last_transaction_at'])): ?>
                            <div><?php echo e(date('d/m/Y', strtotime((string) $customer['last_transaction_at']))); ?></div>
                            <div class="kp-muted small"><?php echo e(date('H:i', strtotime((string) $customer['last_transaction_at']))); ?></div>
                        <?php else: ?>
                            <span class="kp-muted">Belum ada</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin_customers.php?edit=' . $customerId)); ?>">
                                    <span class="material-icons-outlined">edit</span>
                                    Edit
                                </a>
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo $customerId; ?>">
                                    <input type="hidden" name="active" value="<?php echo $isActive ? 0 : 1; ?>">
                                    <button class="btn kp-btn-ghost btn-sm" type="submit">
                                        <span class="material-icons-outlined"><?php echo $isActive ? 'block' : 'check_circle'; ?></span>
                                        <?php echo $isActive ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Hapus member ini?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $customerId; ?>">
                                    <button class="btn kp-btn-ghost btn-sm" type="submit">
                                        <span class="material-icons-outlined">delete</span>
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
                    <td colspan="<?php echo $isAdmin ? '8' : '7'; ?>" class="text-center kp-muted">Belum ada member.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($isAdmin): ?>
    <div class="modal fade" id="modalCustomerCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">No. HP</label>
                                <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="is_active_create" name="is_active" checked>
                                    <label class="form-check-label" for="is_active_create">Member aktif</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Alamat</label>
                                <textarea name="address" rows="2" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn kp-btn-ghost" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn kp-btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($editCustomer)): ?>
        <div class="modal fade" id="modalCustomerEdit" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo (int) ($editCustomer['id'] ?? 0); ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Member</h5>
                            <a class="btn-close" href="<?php echo e(base_url('admin_customers.php')); ?>" aria-label="Close"></a>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nama</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo e($editCustomer['name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">No. HP</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo e($editCustomer['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo e($editCustomer['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="is_active_edit" name="is_active" <?php echo !empty($editCustomer['is_active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active_edit">Member aktif</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Alamat</label>
                                    <textarea name="address" rows="2" class="form-control"><?php echo e($editCustomer['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a class="btn kp-btn-ghost" href="<?php echo e(base_url('admin_customers.php')); ?>">Batal</a>
                            <button type="submit" class="btn kp-btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            window.addEventListener('load', () => {
                const modalEl = document.getElementById('modalCustomerEdit');
                if (!modalEl) {
                    return;
                }
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        </script>
    <?php endif; ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
