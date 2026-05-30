<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/db_migration_helper.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/user_permission_helper.php';
require_once __DIR__ . '/../app/helpers/validation_helper.php';
require_once __DIR__ . '/../app/models/User.php';

ensure_update_schema();
require_role(['superadmin']);

$pdo = db();
$errors = [];
$success = '';

$activeColumn = User::activeColumn($pdo);
$passwordColumn = User::passwordColumn($pdo);
$permissionColumn = User::permissionColumn($pdo);
$cashierPermissionCatalog = cashier_permission_catalog();

function superadmin_user_edit_roles(PDO $pdo): array
{
    return array_values(array_filter(
        User::roles($pdo),
        static fn (array $role): bool => (string) ($role['name'] ?? '') !== 'superadmin'
    ));
}

function superadmin_user_edit_stores(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, store_code, store_name, status, COALESCE(operational_status, 'active') AS operational_status
         FROM stores
         ORDER BY status = 'approved' DESC, store_name ASC"
    );

    return $stmt->fetchAll();
}

function superadmin_user_edit_find(PDO $pdo, int $id): ?array
{
    $activeColumn = User::activeColumn($pdo);
    $selectActive = $activeColumn ? ', users.' . $activeColumn . ' AS is_active' : ', 1 AS is_active';
    $permissionColumn = User::permissionColumn($pdo);
    $selectPermissions = $permissionColumn ? ', users.' . $permissionColumn . ' AS permissions_json' : ', NULL AS permissions_json';

    $stmt = $pdo->prepare(
        'SELECT users.id, users.name, users.username, users.role_id, users.store_id,
                roles.name AS role_name' . $selectActive . $selectPermissions . ',
                stores.store_name, stores.store_code
         FROM users
         INNER JOIN roles ON roles.id = users.role_id
         LEFT JOIN stores ON stores.id = users.store_id
         WHERE users.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row || (string) ($row['role_name'] ?? '') === 'superadmin') {
        return null;
    }

    return $row;
}

function superadmin_user_edit_store_is_approved(PDO $pdo, int $storeId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE id = :id AND status = 'approved'");
    $stmt->execute([':id' => $storeId]);

    return (int) $stmt->fetchColumn() > 0;
}

function superadmin_user_edit_active_admin_count(PDO $pdo, int $storeId, int $excludeUserId = 0): int
{
    $activeColumn = User::activeColumn($pdo);
    $activeSql = $activeColumn ? ' AND users.' . $activeColumn . ' = 1' : '';
    $excludeSql = $excludeUserId > 0 ? ' AND users.id <> :exclude_id' : '';
    $params = [':store_id' => $storeId];
    if ($excludeUserId > 0) {
        $params[':exclude_id'] = $excludeUserId;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM users
         INNER JOIN roles ON roles.id = users.role_id
         WHERE users.store_id = :store_id
           AND roles.name = 'admin'" . $activeSql . $excludeSql
    );
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function superadmin_user_edit_assert_old_store_keeps_admin(PDO $pdo, array $oldUser, string $newRole, int $newStoreId, int $newActive): void
{
    if ((string) ($oldUser['role_name'] ?? '') !== 'admin') {
        return;
    }

    $oldStoreId = (int) ($oldUser['store_id'] ?? 0);
    if ($oldStoreId <= 0) {
        return;
    }

    $removesOldAdmin = $newRole !== 'admin' || $newStoreId !== $oldStoreId || $newActive !== 1;
    if (!$removesOldAdmin) {
        return;
    }

    if (superadmin_user_edit_active_admin_count($pdo, $oldStoreId, (int) ($oldUser['id'] ?? 0)) <= 0) {
        throw new RuntimeException('Minimal harus ada satu admin aktif pada toko asal.');
    }
}

$id = (int) ($_GET['id'] ?? 0);
$editUser = $id > 0 ? superadmin_user_edit_find($pdo, $id) : null;
$roles = superadmin_user_edit_roles($pdo);
$stores = superadmin_user_edit_stores($pdo);
$rolesById = [];
foreach ($roles as $roleRow) {
    $rolesById[(int) ($roleRow['id'] ?? 0)] = (string) ($roleRow['name'] ?? '');
}
$editCashierPermissions = $editUser ? user_permissions($editUser) : cashier_default_permissions();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editUser) {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $username = normalize_username((string) ($_POST['username'] ?? ''));
        $storeId = (int) ($_POST['store_id'] ?? 0);
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');
        $active = isset($_POST['active']) ? (int) $_POST['active'] : 1;
        $selectedRoleName = (string) ($rolesById[$roleId] ?? '');
        $postedCashierPermissions = cashier_permissions_from_request($_POST['permissions'] ?? []);

        try {
            if ($name === '' || $username === '' || $storeId <= 0 || $roleId <= 0) {
                $errors[] = 'Nama, username, toko, dan role wajib diisi.';
            }
            if ($storeId > 0 && !superadmin_user_edit_store_is_approved($pdo, $storeId)) {
                $errors[] = 'User tenant hanya bisa ditempatkan pada toko approved.';
            }
            if ($selectedRoleName === '') {
                $errors[] = 'Role tidak valid.';
            }
            if ($username !== '' && !is_valid_username($username)) {
                $errors[] = 'Username hanya boleh berisi huruf, angka, titik, strip, atau underscore (3-50 karakter).';
            }
            if ($password !== '' && strlen($password) < 8) {
                $errors[] = 'Password minimal 8 karakter.';
            }
            if ($password !== '' && !$passwordColumn) {
                $errors[] = 'Kolom password tidak ditemukan pada tabel users.';
            }
            if ($selectedRoleName === 'karyawan' && !$permissionColumn) {
                $errors[] = 'Kolom izin kasir tidak tersedia pada database.';
            }
            if ($username !== '' && User::usernameExists($pdo, $username, $id)) {
                $errors[] = 'Username sudah digunakan oleh user lain.';
            }

            if (empty($errors)) {
                superadmin_user_edit_assert_old_store_keeps_admin($pdo, $editUser, $selectedRoleName, $storeId, $active === 1 ? 1 : 0);

                $data = [
                    'name' => $name,
                    'username' => $username,
                    'store_id' => $storeId,
                    'role_id' => $roleId,
                ];
                if ($activeColumn) {
                    $data[$activeColumn] = $active === 1 ? 1 : 0;
                }
                if ($password !== '' && $passwordColumn) {
                    $data[$passwordColumn] = password_hash($password, PASSWORD_DEFAULT);
                }
                if ($permissionColumn) {
                    $data[$permissionColumn] = $selectedRoleName === 'karyawan'
                        ? cashier_permissions_to_json($postedCashierPermissions)
                        : null;
                }

                User::update($pdo, $id, $data);
                $success = 'User tenant berhasil diperbarui.';
                audit_log('superadmin_user_updated', [
                    'user_id' => $id,
                    'store_id' => $storeId,
                    'role' => $selectedRoleName,
                    'password_changed' => $password !== '',
                ]);
                $editUser = superadmin_user_edit_find($pdo, $id);
                $editCashierPermissions = $editUser ? user_permissions($editUser) : cashier_default_permissions();
            } else {
                $editUser['name'] = $name;
                $editUser['username'] = $username;
                $editUser['store_id'] = $storeId;
                $editUser['role_id'] = $roleId;
                $editUser['role_name'] = $selectedRoleName !== '' ? $selectedRoleName : (string) ($editUser['role_name'] ?? '');
                $editUser['is_active'] = $active === 1 ? 1 : 0;
                $editCashierPermissions = $selectedRoleName === 'karyawan' ? $postedCashierPermissions : cashier_default_permissions();
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage() !== '' ? $e->getMessage() : 'Gagal memperbarui user tenant.';
        }
    }
}

$title = 'Edit User Tenant';

require_once __DIR__ . '/../app/views/admin/superadmin_user_edit.php';
