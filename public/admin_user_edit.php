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

$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

$id = (int) ($_GET['id'] ?? 0);
$user = $id > 0 ? User::find($pdo, $id) : null;
$editUser = $user;
$roles = User::roles($pdo);
$activeColumn = User::activeColumn($pdo);
$passwordColumn = User::passwordColumn($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editUser) {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    if (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mengubah data user.';
    } elseif (empty($errors)) {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $active = isset($_POST['active']) ? (int) $_POST['active'] : null;

        if ($name === '' || $username === '' || $roleId === 0) {
            $errors[] = 'Nama, username, dan role wajib diisi.';
        }
        if (User::usernameExists($pdo, $username, $id)) {
            $errors[] = 'Username sudah digunakan oleh user lain.';
        }

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

        if (empty($errors)) {
            try {
                User::update($pdo, $id, $data);
                $success = 'User berhasil diperbarui.';
                audit_log('user_updated', ['user_id' => $id]);
                $editUser = User::find($pdo, $id);
            } catch (Throwable $e) {
                $errors[] = 'Gagal memperbarui user.';
            }
        }
    }
}

$title = 'Edit User';

require_once __DIR__ . '/../app/views/admin/user_edit.php';
