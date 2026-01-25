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

<div class="card kp-card bg-white border border-slate-200 rounded-2xl shadow-sm">
    <div class="card-body p-4">
        <h2 class="h4 mb-3">Edit User</h2>
        <?php if ($editUser): ?>
            <?php if ($isAdmin): ?>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama</label>
                            <input type="text" name="name" class="form-control" value="<?php echo e($editUser['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo e($editUser['username']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role_id" class="form-select" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo (int) $role['id']; ?>" <?php echo ((int) $role['id'] === (int) $editUser['role_id']) ? 'selected' : ''; ?>>
                                        <?php echo e($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($activeColumn): ?>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="active" class="form-select">
                                    <option value="1" <?php echo ((int) $editUser[$activeColumn] === 1) ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="0" <?php echo ((int) $editUser[$activeColumn] === 0) ? 'selected' : ''; ?>>Nonaktif</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label">Password Baru (opsional)</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                    <button class="btn kp-btn-primary" type="submit">Simpan Perubahan</button>
                    <a class="btn kp-btn-ghost" href="<?php echo e(base_url('admin_users.php')); ?>">Kembali</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" value="<?php echo e($editUser['name']); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo e($editUser['username']); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?php echo e($editUser['role_name']); ?>" readonly>
                    </div>
                    <?php if ($activeColumn): ?>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-control" value="<?php echo ((int) $editUser[$activeColumn] === 1) ? 'Aktif' : 'Nonaktif'; ?>" readonly>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-4">
                    <a class="btn kp-btn-ghost" href="<?php echo e(base_url('admin_users.php')); ?>">Kembali</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="kp-muted">User tidak ditemukan.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
