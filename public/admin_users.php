<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/db_migration_helper.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/helpers/user_permission_helper.php';
require_once __DIR__ . '/../app/helpers/validation_helper.php';
require_once __DIR__ . '/../app/models/User.php';

require_role(['admin', 'bos']);

ensure_update_schema();

$pdo = db();
$errors = [];
$success = '';

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';

$activeColumn = User::activeColumn($pdo);
$passwordColumn = User::passwordColumn($pdo);
$permissionColumn = User::permissionColumn($pdo);
$cashierPermissionCatalog = cashier_permission_catalog();
$roleDirectory = User::roles($pdo);
$rolesById = [];
foreach ($roleDirectory as $roleRow) {
    $rolesById[(string) ($roleRow['id'] ?? '')] = (string) ($roleRow['name'] ?? '');
}
$isCreatePost = ($_SERVER['REQUEST_METHOD'] === 'POST') && (string) ($_POST['action'] ?? '') === 'create';
$createCashierPermissions = $isCreatePost
    ? cashier_permissions_from_request($_POST['permissions'] ?? [])
    : cashier_default_permissions();
$createRoleName = $isCreatePost
    ? (string) ($rolesById[(string) ($_POST['role_id'] ?? '')] ?? '')
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    if (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mengubah data user.';
    } elseif (empty($errors)) {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $username = normalize_username((string) ($_POST['username'] ?? ''));
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $password = $_POST['password'] ?? '';
            $active = isset($_POST['active']) ? (int) $_POST['active'] : 1;

            if ($name === '' || $username === '' || $roleId === 0 || $password === '') {
                $errors[] = 'Semua field wajib diisi.';
            }
            if ($username !== '' && !is_valid_username($username)) {
                $errors[] = 'Username hanya boleh berisi huruf, angka, titik, strip, atau underscore (3-50 karakter).';
            }
            if ($password !== '' && strlen($password) < 8) {
                $errors[] = 'Password minimal 8 karakter.';
            }

            if (!$passwordColumn) {
                $errors[] = 'Kolom password tidak ditemukan pada tabel users.';
            }
            $roleCheckStmt = $pdo->prepare('SELECT name FROM roles WHERE id = :id LIMIT 1');
            $roleCheckStmt->execute([':id' => $roleId]);
            $selectedRoleName = (string) ($roleCheckStmt->fetchColumn() ?: '');
            if ($selectedRoleName === 'superadmin') {
                $errors[] = 'Role superadmin tidak dapat dibuat dari menu ini.';
            }
            if ($selectedRoleName === 'karyawan' && !$permissionColumn) {
                $errors[] = 'Kolom izin kasir tidak tersedia pada database.';
            }
            if (User::usernameExists($pdo, $username)) {
                $errors[] = 'Username sudah digunakan.';
            }

            if (empty($errors)) {
                $data = [
                    'role_id' => $roleId,
                    'name' => $name,
                    'username' => $username,
                    $passwordColumn => password_hash($password, PASSWORD_DEFAULT),
                ];

                if ($activeColumn) {
                    $data[$activeColumn] = $active === 1 ? 1 : 0;
                }
                if ($selectedRoleName === 'karyawan' && $permissionColumn) {
                    $data[$permissionColumn] = cashier_permissions_to_json(
                        cashier_permissions_from_request($_POST['permissions'] ?? [])
                    );
                } elseif ($permissionColumn) {
                    $data[$permissionColumn] = null;
                }

                try {
                    User::create($pdo, $data);
                    $success = 'User berhasil ditambahkan.';
                    audit_log('user_created', ['username' => $username, 'role_id' => $roleId]);
                } catch (Throwable $e) {
                    $errors[] = 'Gagal menambah user. Username mungkin sudah digunakan.';
                }
            }
        }

        if ($action === 'toggle' && $activeColumn) {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (int) ($_POST['active'] ?? 0);
            if ($id > 0) {
                $targetStmt = $pdo->prepare(
                    'SELECT roles.name AS role_name
                     FROM users
                     INNER JOIN roles ON roles.id = users.role_id
                     WHERE users.id = :id
                     ' . tenant_where_clause($pdo, 'users', 'users', 'AND') . '
                     LIMIT 1'
                );
                $targetStmt->execute(tenant_bind([':id' => $id], $pdo));
                $targetRole = (string) ($targetStmt->fetchColumn() ?: '');
                if ($targetRole === 'superadmin') {
                    $errors[] = 'Akun superadmin tidak dapat diubah dari menu ini.';
                } else {
                    User::setActive($pdo, $id, $active === 1 ? 1 : 0);
                    $success = 'Status user berhasil diubah.';
                    audit_log('user_status_updated', ['user_id' => $id, 'active' => $active === 1 ? 1 : 0]);
                }
            }
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $currentId = (int) ($user['id'] ?? 0);

            if ($id <= 0) {
                $errors[] = 'User tidak valid.';
            } elseif ($id === $currentId) {
                $errors[] = 'Tidak bisa menghapus akun sendiri.';
            } elseif (empty($errors)) {
                $stmt = $pdo->prepare(
                    'SELECT users.id, roles.name AS role_name
                     FROM users
                     INNER JOIN roles ON roles.id = users.role_id
                     WHERE users.id = :id
                     ' . tenant_where_clause($pdo, 'users', 'users', 'AND') . '
                     LIMIT 1'
                );
                $stmt->execute(tenant_bind([':id' => $id], $pdo));
                $target = $stmt->fetch();

                if (!$target) {
                    $errors[] = 'User tidak ditemukan.';
                } elseif (($target['role_name'] ?? '') === 'superadmin') {
                    $errors[] = 'Akun superadmin tidak dapat dihapus dari menu ini.';
                } else {
                    $trxStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = :id' . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND'));
                    $trxStmt->execute(tenant_bind([':id' => $id], $pdo));
                    $trxCount = (int) $trxStmt->fetchColumn();

                    if ($trxCount > 0) {
                        $errors[] = 'User sudah memiliki transaksi. Nonaktifkan saja.';
                    }
                }

                if (empty($errors) && ($target['role_name'] ?? '') === 'admin') {
                    $adminCountStmt = $pdo->prepare(
                        "SELECT COUNT(*)
                         FROM users
                         INNER JOIN roles ON roles.id = users.role_id
                         WHERE roles.name = 'admin'"
                         . tenant_where_clause($pdo, 'users', 'users', 'AND')
                    );
                    $adminCountStmt->execute(tenant_bind([], $pdo));
                    $adminCount = (int) $adminCountStmt->fetchColumn();
                    if ($adminCount <= 1) {
                        $errors[] = 'Minimal harus ada satu admin.';
                    }
                }

                if (empty($errors)) {
                    try {
                        $pdo->beginTransaction();
                        $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = :id' . tenant_where_clause($pdo, 'users', 'users', 'AND'));
                        $deleteStmt->execute(tenant_bind([':id' => $id], $pdo));
                        $pdo->commit();
                        $success = 'User berhasil dihapus.';
                        audit_log('user_deleted', ['user_id' => $id]);
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $errors[] = 'Gagal menghapus user.';
                    }
                }
            }
        }
    }
}

$roles = array_values(array_filter(
    $roleDirectory,
    static fn (array $role): bool => (string) ($role['name'] ?? '') !== 'superadmin'
));
$userFilters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'role' => trim((string) ($_GET['role'] ?? 'all')),
    'status' => trim((string) ($_GET['status'] ?? 'all')),
];

if (!in_array($userFilters['status'], ['all', 'active', 'inactive'], true)) {
    $userFilters['status'] = 'all';
}

$roleFilterOptions = array_map(
    static fn (array $role): string => (string) ($role['name'] ?? ''),
    $roles
);
if ($userFilters['role'] !== 'all' && !in_array($userFilters['role'], $roleFilterOptions, true)) {
    $userFilters['role'] = 'all';
}

$users = array_values(array_filter(
    User::all($pdo),
    static fn (array $row): bool => (string) ($row['role_name'] ?? '') !== 'superadmin'
));
$allUsers = $users;
$users = array_values(array_filter(
    $users,
    static function (array $row) use ($userFilters, $activeColumn): bool {
        if ($userFilters['role'] !== 'all' && (string) ($row['role_name'] ?? '') !== $userFilters['role']) {
            return false;
        }

        $isActive = !$activeColumn || (int) ($row[$activeColumn] ?? 1) === 1;
        if ($userFilters['status'] === 'active' && !$isActive) {
            return false;
        }
        if ($userFilters['status'] === 'inactive' && $isActive) {
            return false;
        }

        $keyword = strtolower($userFilters['q']);
        if ($keyword === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            (string) ($row['name'] ?? ''),
            (string) ($row['username'] ?? ''),
            (string) ($row['role_name'] ?? ''),
        ])));

        return str_contains($haystack, $keyword);
    }
));

$userSummary = [
    'all_total' => count($allUsers),
    'visible_total' => count($users),
    'active_total' => count(array_filter(
        $users,
        static fn (array $row): bool => !$activeColumn || (int) ($row[$activeColumn] ?? 1) === 1
    )),
    'admin_total' => count(array_filter($users, static fn (array $row): bool => (string) ($row['role_name'] ?? '') === 'admin')),
    'cashier_total' => count(array_filter($users, static fn (array $row): bool => (string) ($row['role_name'] ?? '') === 'karyawan')),
    'owner_total' => count(array_filter($users, static fn (array $row): bool => (string) ($row['role_name'] ?? '') === 'bos')),
];

$title = 'Kelola User';

require_once __DIR__ . '/../app/views/admin/users.php';
