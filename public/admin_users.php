<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/models/User.php';

require_role(['admin', 'bos']);

$pdo = db();
$errors = [];
$success = '';

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';

$activeColumn = User::activeColumn($pdo);
$passwordColumn = User::passwordColumn($pdo);

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
            $username = trim($_POST['username'] ?? '');
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $password = $_POST['password'] ?? '';
            $active = isset($_POST['active']) ? (int) $_POST['active'] : 1;

            if ($name === '' || $username === '' || $roleId === 0 || $password === '') {
                $errors[] = 'Semua field wajib diisi.';
            }

            if (!$passwordColumn) {
                $errors[] = 'Kolom password tidak ditemukan pada tabel users.';
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
                User::setActive($pdo, $id, $active === 1 ? 1 : 0);
                $success = 'Status user berhasil diubah.';
                audit_log('user_status_updated', ['user_id' => $id, 'active' => $active === 1 ? 1 : 0]);
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
                     LIMIT 1'
                );
                $stmt->execute([':id' => $id]);
                $target = $stmt->fetch();

                if (!$target) {
                    $errors[] = 'User tidak ditemukan.';
                } else {
                    $trxStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = :id');
                    $trxStmt->execute([':id' => $id]);
                    $trxCount = (int) $trxStmt->fetchColumn();

                    if ($trxCount > 0) {
                        $errors[] = 'User sudah memiliki transaksi. Nonaktifkan saja.';
                    }
                }

                if (empty($errors) && ($target['role_name'] ?? '') === 'admin') {
                    $adminCountStmt = $pdo->query(
                        "SELECT COUNT(*)
                         FROM users
                         INNER JOIN roles ON roles.id = users.role_id
                         WHERE roles.name = 'admin'"
                    );
                    $adminCount = (int) $adminCountStmt->fetchColumn();
                    if ($adminCount <= 1) {
                        $errors[] = 'Minimal harus ada satu admin.';
                    }
                }

                if (empty($errors)) {
                    try {
                        $pdo->beginTransaction();
                        $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                        $deleteStmt->execute([':id' => $id]);
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

$roles = User::roles($pdo);
$users = User::all($pdo);
$title = 'Kelola User';

require_once __DIR__ . '/../app/views/admin/users.php';
