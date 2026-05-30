<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$isCreatePost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['action'] ?? '') === 'create';
$reopenCreateModal = $isAdmin && $isCreatePost && !empty($errors);
$oldCreate = $isCreatePost ? $_POST : [];
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
            <h2 class="kp-page-title">Manajemen User</h2>
            <p class="kp-page-subtitle">Kelola akun operasional, role, status aktif, dan hak akses kasir dengan format yang lebih profesional.</p>
        </div>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUserCreate">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                Tambah User
            </button>
        <?php endif; ?>
    </div>
</section>

<section class="kpi-grid mb-6">
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">User Tampil</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($userSummary['visible_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Total akun: <?php echo e((string) ($userSummary['all_total'] ?? 0)); ?></div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">User Aktif</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($userSummary['active_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Bisa login dan beroperasi.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Admin</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($userSummary['admin_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Pengelola utama outlet.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Kasir</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($userSummary['cashier_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Akun frontliner operasional.</div>
    </div>
</section>

<section class="card kp-filter-card mb-6">
    <form method="GET" class="kp-filter-form">
        <div class="form-group mb-0">
            <label class="form-label">Cari User</label>
            <input type="text" name="q" class="form-input" value="<?php echo e((string) ($userFilters['q'] ?? '')); ?>" placeholder="Nama, username, atau role">
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Role</label>
            <select name="role" class="form-input">
                <option value="all">Semua role</option>
                <?php foreach ($roles as $role): ?>
                    <?php $roleName = (string) ($role['name'] ?? ''); ?>
                    <option value="<?php echo e($roleName); ?>" <?php echo (($userFilters['role'] ?? 'all') === $roleName) ? 'selected' : ''; ?>>
                        <?php echo e($roleName); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
                <option value="all" <?php echo (($userFilters['status'] ?? 'all') === 'all') ? 'selected' : ''; ?>>Semua status</option>
                <option value="active" <?php echo (($userFilters['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Aktif</option>
                <option value="inactive" <?php echo (($userFilters['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Nonaktif</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-3 items-end">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>
                Terapkan
            </button>
            <a href="<?php echo e(base_url('admin_users.php')); ?>" class="btn btn-secondary">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                Reset
            </a>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Daftar User</h3>
            <p class="text-sm text-muted mb-0">Tabel untuk mengontrol struktur role dan keamanan akses operasional.</p>
        </div>
        <?php if (($userSummary['visible_total'] ?? 0) !== ($userSummary['all_total'] ?? 0)): ?>
            <span class="badge badge-neutral">Menampilkan <?php echo e((string) ($userSummary['visible_total'] ?? 0)); ?> dari <?php echo e((string) ($userSummary['all_total'] ?? 0)); ?> user</span>
        <?php endif; ?>
    </div>
    <div class="p-0">
        <div class="table-wrapper border-0 rounded-none">
            <table class="data-table data-table--sheet">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Akses Kasir</th>
                        <th class="text-center">Status</th>
                        <?php if ($isAdmin): ?>
                            <th class="text-right">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <?php
                        $permissionMap = ($row['role_name'] ?? '') === 'karyawan' ? user_permissions($row) : [];
                        $enabledPermissionCount = count(array_filter($permissionMap, static fn ($value): bool => !empty($value)));
                        $isActiveRow = !$activeColumn || (int) ($row[$activeColumn] ?? 1) === 1;
                        $permissionPreview = [];
                        foreach ($cashierPermissionCatalog as $permission) {
                            $key = (string) ($permission['key'] ?? '');
                            if (!empty($permissionMap[$key])) {
                                $permissionPreview[] = (string) ($permission['label'] ?? $key);
                            }
                            if (count($permissionPreview) >= 3) {
                                break;
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="font-semibold text-slate-900"><?php echo e((string) ($row['name'] ?? '-')); ?></div>
                                <div class="text-xs text-slate-500 mt-1">Akun operasional POS.</div>
                            </td>
                            <td>
                                <div class="font-medium text-slate-700">@<?php echo e((string) ($row['username'] ?? '-')); ?></div>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo e((string) ($row['role_name'] ?? '-')); ?></span>
                            </td>
                            <td>
                                <?php if (($row['role_name'] ?? '') === 'karyawan'): ?>
                                    <div class="font-semibold text-slate-900"><?php echo e((string) $enabledPermissionCount); ?> izin aktif</div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        <?php echo e(!empty($permissionPreview) ? implode(', ', $permissionPreview) : 'Belum ada izin khusus.'); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-sm text-slate-400">Akses penuh sesuai role</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $isActiveRow ? 'badge-success' : 'badge-neutral'; ?>">
                                    <?php echo $isActiveRow ? 'Aktif' : 'Nonaktif'; ?>
                                </span>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        <a class="btn btn-secondary btn-sm" href="<?php echo e(base_url('admin_user_edit.php?id=' . (int) ($row['id'] ?? 0))); ?>">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i>
                                            Edit
                                        </a>
                                        <?php if ($activeColumn): ?>
                                            <form method="POST">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                                <input type="hidden" name="active" value="<?php echo $isActiveRow ? 0 : 1; ?>">
                                                <button class="btn btn-secondary btn-sm" type="submit">
                                                    <i data-lucide="<?php echo $isActiveRow ? 'slash' : 'check-circle'; ?>" class="w-4 h-4"></i>
                                                    <?php echo $isActiveRow ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" onsubmit="return confirm('Hapus user ini?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
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
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="<?php echo $isAdmin ? '6' : '5'; ?>" class="text-center py-10 text-slate-500">Tidak ada user yang cocok dengan filter saat ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php if ($isAdmin): ?>
    <div class="modal fade" id="modalUserCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-2xl shadow-lg">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header border-b border-border p-5">
                        <h5 class="modal-title font-bold">Tambah User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group mb-0">
                                <label class="form-label">Nama</label>
                                <input type="text" name="name" class="form-input w-full" value="<?php echo e((string) ($oldCreate['name'] ?? '')); ?>" required>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-input w-full" value="<?php echo e((string) ($oldCreate['username'] ?? '')); ?>" autocomplete="username" pattern="[A-Za-z0-9._-]{3,50}" minlength="3" maxlength="50" required>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Role</label>
                                <select name="role_id" class="form-input w-full" id="create_role_id" required>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo (int) ($role['id'] ?? 0); ?>" <?php echo ((string) ($oldCreate['role_id'] ?? '') === (string) ($role['id'] ?? '')) ? 'selected' : ''; ?>>
                                            <?php echo e((string) ($role['name'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-input w-full" autocomplete="new-password" minlength="8" required>
                            </div>
                            <?php if ($activeColumn): ?>
                                <div class="form-group mb-0">
                                    <label class="form-label">Status</label>
                                    <select name="active" class="form-input w-full">
                                        <option value="1" <?php echo ((string) ($oldCreate['active'] ?? '1') === '1') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="0" <?php echo ((string) ($oldCreate['active'] ?? '1') === '0') ? 'selected' : ''; ?>>Nonaktif</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="md:col-span-2 <?php echo $createRoleName === 'karyawan' ? '' : 'hidden'; ?>" id="createCashierPermissionBox">
                                <div class="p-5 rounded-xl border border-border bg-white">
                                    <div class="font-bold text-slate-900">Izin Kasir</div>
                                    <div class="text-xs text-muted mt-1 mb-4">Centang fitur yang boleh dipakai akun kasir ini di POS, shift, history, dan cetak struk.</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <?php foreach ($cashierPermissionCatalog as $permission): ?>
                                            <?php $permissionKey = (string) ($permission['key'] ?? ''); ?>
                                            <label class="p-3 rounded-lg border border-border bg-white cursor-pointer hover:border-emerald-200 transition-colors">
                                                <div class="flex items-start gap-3">
                                                    <input class="form-check-input mt-0.5" type="checkbox" name="permissions[<?php echo e($permissionKey); ?>]" value="1" <?php echo !empty($createCashierPermissions[$permissionKey]) ? 'checked' : ''; ?>>
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
                    </div>
                    <div class="modal-footer border-t border-border p-5">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    (function () {
        const roleSelect = document.getElementById('create_role_id');
        const permissionBox = document.getElementById('createCashierPermissionBox');

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

    <?php if ($reopenCreateModal): ?>
    window.addEventListener('load', function () {
        if (typeof bootstrap === 'undefined') {
            return;
        }
        const modalEl = document.getElementById('modalUserCreate');
        if (!modalEl) {
            return;
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    <?php endif; ?>
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
