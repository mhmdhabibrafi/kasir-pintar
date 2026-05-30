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
    <div class="alert alert-success mb-6"><?php echo e($success); ?></div>
<?php endif; ?>

<div class="card max-w-5xl mx-auto">
    <div class="p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-1">Edit User Tenant</h2>
                <p class="text-sm text-muted mb-0">Ubah toko, role, status aktif, password, dan izin kasir dari panel superadmin.</p>
            </div>
            <a class="btn btn-secondary" href="<?php echo e(base_url('superadmin_users.php')); ?>">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Kembali
            </a>
        </div>

        <?php if ($editUser): ?>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group mb-0 md:col-span-2">
                        <label class="form-label">Toko</label>
                        <select name="store_id" class="form-input w-full" required>
                            <?php foreach ($stores as $store): ?>
                                <?php
                                $storeId = (int) ($store['id'] ?? 0);
                                $isApproved = (string) ($store['status'] ?? '') === 'approved';
                                ?>
                                <option value="<?php echo $storeId; ?>" <?php echo !$isApproved ? 'disabled' : ''; ?> <?php echo (int) ($editUser['store_id'] ?? 0) === $storeId ? 'selected' : ''; ?>>
                                    <?php echo e((string) ($store['store_name'] ?? '-')); ?> (<?php echo e((string) ($store['store_code'] ?? '-')); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-input w-full" value="<?php echo e((string) ($editUser['name'] ?? '')); ?>" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input w-full" value="<?php echo e((string) ($editUser['username'] ?? '')); ?>" autocomplete="username" pattern="[A-Za-z0-9._-]{3,50}" minlength="3" maxlength="50" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Role</label>
                        <select name="role_id" class="form-input w-full" id="edit_role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo (int) ($role['id'] ?? 0); ?>" <?php echo (int) ($role['id'] ?? 0) === (int) ($editUser['role_id'] ?? 0) ? 'selected' : ''; ?>>
                                    <?php echo e((string) ($role['name'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($activeColumn): ?>
                        <div class="form-group mb-0">
                            <label class="form-label">Status</label>
                            <select name="active" class="form-input w-full">
                                <option value="1" <?php echo (int) ($editUser['is_active'] ?? 1) === 1 ? 'selected' : ''; ?>>Aktif</option>
                                <option value="0" <?php echo (int) ($editUser['is_active'] ?? 1) === 0 ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group mb-0 md:col-span-2">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password" class="form-input w-full" autocomplete="new-password" minlength="8" placeholder="Kosongkan jika password tidak diubah">
                    </div>

                    <div class="md:col-span-2 <?php echo (string) ($editUser['role_name'] ?? '') === 'karyawan' ? '' : 'hidden'; ?>" id="editCashierPermissionBox">
                        <div class="p-5 rounded-xl border border-border bg-white mt-4">
                            <div class="font-bold text-slate-900">Izin Kasir</div>
                            <div class="text-xs text-muted mt-1 mb-4">Atur fitur POS, shift, history, diskon, pembayaran, dan cetak struk untuk akun kasir.</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach ($cashierPermissionCatalog as $permission): ?>
                                    <?php $permissionKey = (string) ($permission['key'] ?? ''); ?>
                                    <label class="p-3 rounded-lg border border-border bg-white cursor-pointer hover:border-emerald-200 transition-colors">
                                        <div class="flex items-start gap-3">
                                            <input class="form-check-input mt-0.5" type="checkbox" name="permissions[<?php echo e($permissionKey); ?>]" value="1" <?php echo !empty($editCashierPermissions[$permissionKey]) ? 'checked' : ''; ?>>
                                            <div>
                                                <div class="font-semibold text-sm text-slate-900 leading-tight"><?php echo e((string) ($permission['label'] ?? $permissionKey)); ?></div>
                                                <div class="text-xs text-muted mt-1 leading-snug"><?php echo e((string) ($permission['description'] ?? '')); ?></div>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-8 flex flex-wrap gap-3 pt-6 border-t border-border">
                    <button class="btn btn-primary" type="submit">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Simpan Perubahan
                    </button>
                    <a class="btn btn-secondary" href="<?php echo e(base_url('superadmin_users.php')); ?>">Batal</a>
                </div>
            </form>
        <?php else: ?>
            <p class="text-muted p-4 text-center bg-slate-50 rounded-xl">User tenant tidak ditemukan.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    (function () {
        const roleSelect = document.getElementById('edit_role_id');
        const permissionBox = document.getElementById('editCashierPermissionBox');

        const syncPermissionBox = () => {
            if (!roleSelect || !permissionBox) {
                return;
            }
            const selectedText = roleSelect.options[roleSelect.selectedIndex]?.text?.trim().toLowerCase() || '';
            permissionBox.classList.toggle('hidden', selectedText !== 'karyawan');
        };

        if (roleSelect) {
            roleSelect.addEventListener('change', syncPermissionBox);
            syncPermissionBox();
        }
    })();
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
