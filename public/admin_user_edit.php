<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/db_migration_helper.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/user_permission_helper.php';
require_once __DIR__ . '/../app/helpers/validation_helper.php';
require_once __DIR__ . '/../app/models/User.php';

require_role(['admin']);

ensure_update_schema();

$pdo = db();
$errors = [];
$success = '';

$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

$id = (int) ($_GET['id'] ?? 0);
$user = $id > 0 ? User::find($pdo, $id) : null;
if ($user && (string) ($user['role_name'] ?? '') === 'superadmin') {
    $user = null;
}
$editUser = $user;
$roles = array_values(array_filter(
    User::roles($pdo),
    static fn (array $role): bool => (string) ($role['name'] ?? '') !== 'superadmin'
));
$activeColumn = User::activeColumn($pdo);
$passwordColumn = User::passwordColumn($pdo);
$permissionColumn = User::permissionColumn($pdo);
$cashierPermissionCatalog = cashier_permission_catalog();
$editCashierPermissions = $editUser ? user_permissions($editUser) : cashier_default_permissions();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editUser) {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    if (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mengubah data user.';
    } elseif (empty($errors)) {
        $name = trim($_POST['name'] ?? '');
        $username = normalize_username((string) ($_POST['username'] ?? ''));
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $active = isset($_POST['active']) ? (int) $_POST['active'] : null;

        if ($name === '' || $username === '' || $roleId === 0) {
            $errors[] = 'Nama, username, dan role wajib diisi.';
        }
        if ($username !== '' && !is_valid_username($username)) {
            $errors[] = 'Username hanya boleh berisi huruf, angka, titik, strip, atau underscore (3-50 karakter).';
        }
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        $roleCheckStmt = $pdo->prepare('SELECT name FROM roles WHERE id = :id LIMIT 1');
        $roleCheckStmt->execute([':id' => $roleId]);
        $selectedRoleName = (string) ($roleCheckStmt->fetchColumn() ?: '');
        if ($selectedRoleName === 'superadmin') {
            $errors[] = 'Role superadmin tidak dapat diatur dari menu ini.';
        }
        if ($selectedRoleName === 'karyawan' && !$permissionColumn) {
            $errors[] = 'Kolom izin kasir tidak tersedia pada database.';
        }
        if (User::usernameExists($pdo, $username, $id)) {
            $errors[] = 'Username sudah digunakan oleh user lain.';
        }

        $postedCashierPermissions = cashier_permissions_from_request($_POST['permissions'] ?? []);
        $editCashierPermissions = $selectedRoleName === 'karyawan'
            ? $postedCashierPermissions
            : cashier_default_permissions();

        $data = [
            'name' => $name,
            'username' => $username,
            'role_id' => $roleId,
        ];

        if ($password !== '') {
            if ($passwordColumn) {
                $data[$passwordColumn] = password_hash($password, PASSWORD_DEFAULT);
            } else {
                $errors[] = 'Kolom password tidak ditemukan pada tabel users.';
            }
        }

        if ($activeColumn && $active !== null) {
            $data[$activeColumn] = $active === 1 ? 1 : 0;
        }
        if ($permissionColumn) {
            $data[$permissionColumn] = $selectedRoleName === 'karyawan'
                ? cashier_permissions_to_json($postedCashierPermissions)
                : null;
        }

        if (empty($errors)) {
            try {
                User::update($pdo, $id, $data);
                $success = 'User berhasil diperbarui.';
                audit_log('user_updated', ['user_id' => $id]);
                $editUser = User::find($pdo, $id);
                $editCashierPermissions = $editUser ? user_permissions($editUser) : cashier_default_permissions();
            } catch (Throwable $e) {
                $errors[] = 'Gagal memperbarui user.';
            }
        } else {
            $editUser['name'] = $name;
            $editUser['username'] = $username;
            $editUser['role_id'] = $roleId;
            $editUser['role_name'] = $selectedRoleName !== '' ? $selectedRoleName : (string) ($editUser['role_name'] ?? '');
            if ($activeColumn && $active !== null) {
                $editUser[$activeColumn] = $active === 1 ? 1 : 0;
            }
            if ($permissionColumn) {
                $editUser['permissions_json'] = $selectedRoleName === 'karyawan'
                    ? cashier_permissions_to_json($postedCashierPermissions)
                    : null;
            }
        }
    }
}

$title = 'Edit User';

require_once __DIR__ . '/../app/views/admin/user_edit.php';
