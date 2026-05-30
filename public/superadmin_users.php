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
$currentUser = current_user() ?? [];
$errors = [];
$success = '';

$activeColumn = User::activeColumn($pdo);
$passwordColumn = User::passwordColumn($pdo);
$permissionColumn = User::permissionColumn($pdo);
$cashierPermissionCatalog = cashier_permission_catalog();

function superadmin_user_roles(PDO $pdo): array
{
    return array_values(array_filter(
        User::roles($pdo),
        static fn (array $role): bool => (string) ($role['name'] ?? '') !== 'superadmin'
    ));
}

function superadmin_user_store_options(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, store_code, store_name, status, COALESCE(operational_status, 'active') AS operational_status
         FROM stores
         ORDER BY status = 'approved' DESC, store_name ASC"
    );

    return $stmt->fetchAll();
}

function superadmin_user_store_exists(PDO $pdo, int $storeId): bool
{
    if ($storeId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE id = :id AND status = 'approved'");
    $stmt->execute([':id' => $storeId]);

    return (int) $stmt->fetchColumn() > 0;
}

function superadmin_user_role_name(PDO $pdo, int $roleId): string
{
    $stmt = $pdo->prepare('SELECT name FROM roles WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $roleId]);

    return (string) ($stmt->fetchColumn() ?: '');
}

function superadmin_user_find(PDO $pdo, int $id): ?array
{
    $activeColumn = User::activeColumn($pdo);
    $selectActive = $activeColumn ? ', users.' . $activeColumn . ' AS is_active' : ', 1 AS is_active';

    $stmt = $pdo->prepare(
        'SELECT users.id, users.name, users.username, users.role_id, users.store_id,
                roles.name AS role_name' . $selectActive . ',
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

function superadmin_user_active_admin_count(PDO $pdo, int $storeId, int $excludeUserId = 0): int
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

function superadmin_user_assert_not_last_admin(PDO $pdo, array $target): void
{
    if ((string) ($target['role_name'] ?? '') !== 'admin') {
        return;
    }

    $storeId = (int) ($target['store_id'] ?? 0);
    if ($storeId <= 0) {
        return;
    }

    if (superadmin_user_active_admin_count($pdo, $storeId, (int) ($target['id'] ?? 0)) <= 0) {
        throw new RuntimeException('Minimal harus ada satu admin aktif pada toko tersebut.');
    }
}

$roles = superadmin_user_roles($pdo);
$stores = superadmin_user_store_options($pdo);
$rolesById = [];
foreach ($roles as $roleRow) {
    $rolesById[(int) ($roleRow['id'] ?? 0)] = (string) ($roleRow['name'] ?? '');
}

$isCreatePost = ($_SERVER['REQUEST_METHOD'] === 'POST') && (string) ($_POST['action'] ?? '') === 'create';
$createCashierPermissions = $isCreatePost
    ? cashier_permissions_from_request($_POST['permissions'] ?? [])
    : cashier_default_permissions();
$createRoleName = $isCreatePost ? (string) ($rolesById[(int) ($_POST['role_id'] ?? 0)] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === 'create') {
                $storeId = (int) ($_POST['store_id'] ?? 0);
                $roleId = (int) ($_POST['role_id'] ?? 0);
                $name = trim((string) ($_POST['name'] ?? ''));
                $username = normalize_username((string) ($_POST['username'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $active = isset($_POST['active']) ? (int) $_POST['active'] : 1;
                $selectedRoleName = (string) ($rolesById[$roleId] ?? '');

                if ($storeId <= 0 || !superadmin_user_store_exists($pdo, $storeId)) {
                    $errors[] = 'Pilih toko aktif untuk user baru.';
                }
                if ($name === '' || $username === '' || $roleId <= 0 || $password === '') {
                    $errors[] = 'Nama, username, role, toko, dan password wajib diisi.';
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
                if (!$passwordColumn) {
                    $errors[] = 'Kolom password tidak ditemukan pada tabel users.';
                }
                if ($selectedRoleName === 'karyawan' && !$permissionColumn) {
                    $errors[] = 'Kolom izin kasir tidak tersedia pada database.';
                }
                if ($username !== '' && User::usernameExists($pdo, $username)) {
                    $errors[] = 'Username sudah digunakan.';
                }

                if (empty($errors)) {
                    $data = [
                        'role_id' => $roleId,
                        'name' => $name,
                        'username' => $username,
                        'store_id' => $storeId,
                        $passwordColumn => password_hash($password, PASSWORD_DEFAULT),
                    ];
                    if ($activeColumn) {
                        $data[$activeColumn] = $active === 1 ? 1 : 0;
                    }
                    if ($permissionColumn) {
                        $data[$permissionColumn] = $selectedRoleName === 'karyawan'
                            ? cashier_permissions_to_json(cashier_permissions_from_request($_POST['permissions'] ?? []))
                            : null;
                    }

                    $newUserId = User::create($pdo, $data);
                    $success = 'User tenant berhasil ditambahkan.';
                    audit_log('superadmin_user_created', [
                        'user_id' => $newUserId,
                        'store_id' => $storeId,
                        'role' => $selectedRoleName,
                    ]);
                }
            } elseif ($action === 'toggle' && $activeColumn) {
                $target = superadmin_user_find($pdo, (int) ($_POST['id'] ?? 0));
                if (!$target) {
                    throw new RuntimeException('User tidak ditemukan.');
                }

                $active = (int) ($_POST['active'] ?? 0) === 1 ? 1 : 0;
                if ($active === 0) {
                    superadmin_user_assert_not_last_admin($pdo, $target);
                }

                User::setActive($pdo, (int) $target['id'], $active);
                $success = $active === 1 ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.';
                audit_log('superadmin_user_status_updated', [
                    'user_id' => (int) $target['id'],
                    'store_id' => (int) ($target['store_id'] ?? 0),
                    'active' => $active,
                ]);
            } elseif ($action === 'delete') {
                $target = superadmin_user_find($pdo, (int) ($_POST['id'] ?? 0));
                if (!$target) {
                    throw new RuntimeException('User tidak ditemukan.');
                }

                superadmin_user_assert_not_last_admin($pdo, $target);

                $trxStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = :id');
                $trxStmt->execute([':id' => (int) $target['id']]);
                if ((int) $trxStmt->fetchColumn() > 0) {
                    throw new RuntimeException('User sudah memiliki transaksi. Nonaktifkan saja agar riwayat tetap aman.');
                }

                $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $deleteStmt->execute([':id' => (int) $target['id']]);
                $success = 'User berhasil dihapus.';
                audit_log('superadmin_user_deleted', [
                    'user_id' => (int) $target['id'],
                    'store_id' => (int) ($target['store_id'] ?? 0),
                ]);
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage() !== '' ? $e->getMessage() : 'Gagal memproses user tenant.';
        }
    }
}

$userFilters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'store_id' => trim((string) ($_GET['store_id'] ?? 'all')),
    'role' => trim((string) ($_GET['role'] ?? 'all')),
    'status' => trim((string) ($_GET['status'] ?? 'all')),
];
if ($userFilters['status'] !== 'all' && !in_array($userFilters['status'], ['active', 'inactive'], true)) {
    $userFilters['status'] = 'all';
}
if ($userFilters['store_id'] !== 'all' && (int) $userFilters['store_id'] <= 0) {
    $userFilters['store_id'] = 'all';
}
$roleNames = array_values($rolesById);
if ($userFilters['role'] !== 'all' && !in_array($userFilters['role'], $roleNames, true)) {
    $userFilters['role'] = 'all';
}

$selectActive = $activeColumn ? ', users.' . $activeColumn . ' AS is_active' : ', 1 AS is_active';
$selectPermissions = $permissionColumn ? ', users.' . $permissionColumn . ' AS permissions_json' : ', NULL AS permissions_json';
$conditions = ["roles.name <> 'superadmin'"];
$params = [];

if ($userFilters['store_id'] !== 'all') {
    $conditions[] = 'users.store_id = :store_id';
    $params[':store_id'] = (int) $userFilters['store_id'];
}
if ($userFilters['role'] !== 'all') {
    $conditions[] = 'roles.name = :role';
    $params[':role'] = $userFilters['role'];
}
if ($activeColumn && $userFilters['status'] !== 'all') {
    $conditions[] = 'users.' . $activeColumn . ' = :active';
    $params[':active'] = $userFilters['status'] === 'active' ? 1 : 0;
}
if ($userFilters['q'] !== '') {
    $conditions[] = '(users.name LIKE :q OR users.username LIKE :q_username OR stores.store_name LIKE :q_store OR stores.store_code LIKE :q_code)';
    $keyword = '%' . $userFilters['q'] . '%';
    $params[':q'] = $keyword;
    $params[':q_username'] = $keyword;
    $params[':q_store'] = $keyword;
    $params[':q_code'] = $keyword;
}

$sql = 'SELECT users.id, users.name, users.username, users.role_id, users.store_id, users.created_at,
               roles.name AS role_name' . $selectActive . $selectPermissions . ',
               stores.store_name, stores.store_code, stores.status AS store_status,
               COALESCE(stores.operational_status, "active") AS operational_status
        FROM users
        INNER JOIN roles ON roles.id = users.role_id
        LEFT JOIN stores ON stores.id = users.store_id
        WHERE ' . implode(' AND ', $conditions) . '
        ORDER BY stores.store_name IS NULL ASC, stores.store_name ASC, roles.name ASC, users.name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$summaryStmt = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN roles.name = 'admin' THEN 1 ELSE 0 END) AS admin_total,
            SUM(CASE WHEN roles.name = 'bos' THEN 1 ELSE 0 END) AS bos_total,
            SUM(CASE WHEN roles.name = 'karyawan' THEN 1 ELSE 0 END) AS cashier_total,
            COUNT(DISTINCT users.store_id) AS store_total
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE roles.name <> 'superadmin'"
);
$summaryRow = $summaryStmt->fetch() ?: [];
$userSummary = [
    'visible_total' => count($users),
    'all_total' => (int) ($summaryRow['total'] ?? 0),
    'admin_total' => (int) ($summaryRow['admin_total'] ?? 0),
    'bos_total' => (int) ($summaryRow['bos_total'] ?? 0),
    'cashier_total' => (int) ($summaryRow['cashier_total'] ?? 0),
    'store_total' => (int) ($summaryRow['store_total'] ?? 0),
];

$title = 'Kelola User Tenant';

require_once __DIR__ . '/../app/views/admin/superadmin_users.php';
