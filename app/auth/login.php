<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../models/User.php';

$errors = [];
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldUsername = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rateKey = login_rate_limit_key($oldUsername);

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi login tidak valid. Silakan muat ulang halaman.';
    }

    if ($oldUsername === '' || $password === '') {
        $errors[] = 'Username dan password wajib diisi.';
    } else {
        $rate = login_rate_limit_check($rateKey);
        if (!$rate['allowed']) {
            $errors[] = 'Terlalu banyak percobaan login. Coba lagi dalam ' . (int) $rate['retry_after'] . ' detik.';
        }
    }

    if (empty($errors)) {
        $pdo = db();
        $passwordColumn = User::passwordColumn($pdo);
        $activeColumn = User::activeColumn($pdo);

        if (!$passwordColumn) {
            $errors[] = 'Kolom password tidak ditemukan pada tabel users.';
        } else {
            $passwordColumn = $passwordColumn === 'password_hash' ? 'password_hash' : 'password';
            $selectActive = $activeColumn ? ', users.' . $activeColumn . ' AS is_active' : '';
            $stmt = $pdo->prepare(
                "SELECT users.id, users.name, users.username, users.{$passwordColumn} AS password_hash, roles.name AS role_name{$selectActive}
                 FROM users
                 INNER JOIN roles ON roles.id = users.role_id
                 WHERE users.username = :username
                 LIMIT 1"
            );
            $stmt->execute([':username' => $oldUsername]);
            $user = $stmt->fetch();

            $passwordHash = (string) ($user['password_hash'] ?? '');
            $verified = $user ? password_verify($password, $passwordHash) : false;
            $legacyMatched = false;

            if ($user && !$verified) {
                if (strlen($passwordHash) === 32 && ctype_xdigit($passwordHash)) {
                    $legacyMatched = hash_equals($passwordHash, md5($password));
                } elseif ($passwordHash !== '' && hash_equals($passwordHash, $password)) {
                    $legacyMatched = true;
                }
            }

            if (!$user || (!$verified && !$legacyMatched)) {
                $errors[] = 'Username atau password salah.';
                login_rate_limit_record($rateKey, false);
                security_log('login_failed', ['username' => $oldUsername]);
            } elseif ($activeColumn && (int) ($user['is_active'] ?? 1) !== 1) {
                $errors[] = 'Akun Anda nonaktif. Hubungi admin.';
                login_rate_limit_record($rateKey, false);
                security_log('login_blocked_inactive', ['username' => $oldUsername]);
            } else {
                if ($legacyMatched || password_needs_rehash($passwordHash, PASSWORD_DEFAULT)) {
                    User::update($pdo, (int) $user['id'], [
                        $passwordColumn => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                }
                login_rate_limit_record($rateKey, true);
                login_user([
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'role' => $user['role_name'],
                ]);

                header('Location: ' . role_redirect($user['role_name']));
                exit;
            }
        }
    }
} elseif (is_logged_in()) {
    $user = current_user();
    header('Location: ' . role_redirect($user['role'] ?? ''));
    exit;
}
