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

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h2 class="h4 mb-1">Manajemen User</h2>
        <p class="kp-muted mb-0"><?php echo $isAdmin ? 'Kelola akun dan akses pengguna.' : 'Mode lihat saja.'; ?></p>
    </div>
    <?php if ($isAdmin): ?>
        <button class="btn kp-btn-primary" data-bs-toggle="modal" data-bs-target="#modalUserCreate">
            <span class="material-icons-outlined">person_add</span>
            Tambah User
        </button>
    <?php endif; ?>
</div>

<div class="kp-grid kp-grid-3">
    <?php foreach ($users as $user): ?>
        <div class="kp-card-flat p-4 bg-white border border-slate-200 rounded-2xl shadow-sm transition hover:shadow-md">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold"><?php echo e($user['name']); ?></div>
                    <div class="kp-muted small">@<?php echo e($user['username']); ?></div>
                </div>
                <span class="kp-badge"><?php echo e($user['role_name']); ?></span>
            </div>
            <?php if ($activeColumn): ?>
                <div class="mt-2">
                    <span class="badge rounded-pill <?php echo ((int) $user[$activeColumn] === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                        <?php echo ((int) $user[$activeColumn] === 1) ? 'Aktif' : 'Nonaktif'; ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <div class="d-flex gap-2 mt-3">
                    <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin_user_edit.php?id=' . (int) $user['id'])); ?>">
                        <span class="material-icons-outlined">edit</span>
                        Edit
                    </a>
                    <?php if ($activeColumn): ?>
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
                            <input type="hidden" name="active" value="<?php echo ((int) $user[$activeColumn] === 1) ? 0 : 1; ?>">
                            <button class="btn kp-btn-ghost btn-sm" type="submit">
                                <span class="material-icons-outlined"><?php echo ((int) $user[$activeColumn] === 1) ? 'block' : 'check_circle'; ?></span>
                                <?php echo ((int) $user[$activeColumn] === 1) ? 'Nonaktifkan' : 'Aktifkan'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Hapus user ini?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
                        <button class="btn kp-btn-ghost btn-sm" type="submit">
                            <span class="material-icons-outlined">delete</span>
                            Hapus
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
        <div class="kp-card-flat p-3 text-center kp-muted bg-white border border-slate-200 rounded-2xl shadow-sm">Belum ada user.</div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
    <div class="modal fade" id="modalUserCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <select name="role_id" class="form-select" required>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo (int) $role['id']; ?>"><?php echo e($role['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <?php if ($activeColumn): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="active" class="form-select">
                                        <option value="1">Aktif</option>
                                        <option value="0">Nonaktif</option>
                                    </select>
                                </div>
                            <?php endif; ?>
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
<?php endif; ?>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
