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

<div class="card max-w-4xl mx-auto">
    <div class="p-6">
        <h2 class="text-xl font-bold text-slate-900 mb-6">Edit User</h2>
        <?php if ($editUser): ?>
            <?php if ($isAdmin): ?>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group mb-0">
                            <label class="form-label">Nama</label>
                            <input type="text" name="name" class="form-input w-full" value="<?php echo e($editUser['name']); ?>" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-input w-full" value="<?php echo e($editUser['username']); ?>" autocomplete="username" pattern="[A-Za-z0-9._-]{3,50}" minlength="3" maxlength="50" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label">Role</label>
                            <select name="role_id" class="form-input w-full" id="edit_role_id" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo (int) $role['id']; ?>" <?php echo ((int) $role['id'] === (int) $editUser['role_id']) ? 'selected' : ''; ?>>
                                        <?php echo e($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($activeColumn): ?>
                            <div class="form-group mb-0">
                                <label class="form-label">Status</label>
                                <select name="active" class="form-input w-full">
                                    <option value="1" <?php echo ((int) $editUser[$activeColumn] === 1) ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="0" <?php echo ((int) $editUser[$activeColumn] === 0) ? 'selected' : ''; ?>>Nonaktif</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="form-group mb-0 md:col-span-2">
                            <label class="form-label">Password Baru (opsional)</label>
                            <input type="password" name="password" class="form-input w-full" autocomplete="new-password" minlength="8" placeholder="Kosongkan jika tidak diubah">
                        </div>

                        <div class="md:col-span-2 <?php echo (($editUser['role_name'] ?? '') === 'karyawan') ? '' : 'hidden'; ?>" id="editCashierPermissionBox">
                            <div class="p-5 rounded-xl border border-border bg-white mt-4">
                                <div class="font-bold text-slate-900">Izin Kasir</div>
                                <div class="text-xs text-muted mt-1 mb-4">Atur apa saja yang boleh dilakukan akun kasir ini di halaman POS, shift, history, dan cetak struk.</div>
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
                    <div class="mt-8 flex gap-3 pt-6 border-t border-border">
                        <button class="btn btn-primary" type="submit">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
                        </button>
                        <a class="btn btn-secondary" href="<?php echo e(base_url('admin_users.php')); ?>">Kembali</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group mb-0">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-input w-full bg-slate-50" value="<?php echo e($editUser['name']); ?>" readonly>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input w-full bg-slate-50" value="<?php echo e($editUser['username']); ?>" readonly>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-input w-full bg-slate-50" value="<?php echo e($editUser['role_name']); ?>" readonly>
                    </div>
                    <?php if ($activeColumn): ?>
                        <div class="form-group mb-0">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-input w-full bg-slate-50" value="<?php echo ((int) $editUser[$activeColumn] === 1) ? 'Aktif' : 'Nonaktif'; ?>" readonly>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-8 pt-6 border-t border-border">
                    <a class="btn btn-secondary" href="<?php echo e(base_url('admin_users.php')); ?>">Kembali</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted p-4 text-center bg-slate-50 rounded-xl">User tidak ditemukan.</p>
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
