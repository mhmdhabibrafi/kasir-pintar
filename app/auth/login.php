<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/db_migration_helper.php';
require_once __DIR__ . '/../helpers/domain_helper.php';
require_once __DIR__ . '/../helpers/maintenance_helper.php';
require_once __DIR__ . '/../helpers/store_registration_helper.php';
require_once __DIR__ . '/../helpers/user_permission_helper.php';
require_once __DIR__ . '/../models/User.php';

$errors = [];
$oldUsername = '';
$maintenanceConfig = maintenance_get_config();
$blockedReason = trim((string) ($_GET['blocked'] ?? ''));

ensure_update_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $blockedReason === 'store_access') {
    $errors[] = 'Akses toko Anda sedang dibatasi oleh super admin. Hubungi super admin untuk membuka akses lagi.';
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' && $blockedReason === 'domain_access') {
    $errors[] = 'Akun Anda tidak sesuai dengan domain toko yang sedang dibuka.';
}

$formAction = (string) ($_POST['form_action'] ?? 'login');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formAction === 'login') {
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
        $storeIdColumn = User::storeIdColumn($pdo);
        $permissionColumn = User::permissionColumn($pdo);

        if (!$passwordColumn) {
            $errors[] = 'Kolom password tidak ditemukan pada tabel users.';
        } else {
            $passwordColumn = $passwordColumn === 'password_hash' ? 'password_hash' : 'password';
            $selectActive = $activeColumn ? ', users.' . $activeColumn . ' AS is_active' : '';
            $selectStoreId = $storeIdColumn ? ', users.' . $storeIdColumn . ' AS store_id' : '';
            $selectPermissions = $permissionColumn ? ', users.' . $permissionColumn . ' AS permissions_json' : '';
            $stmt = $pdo->prepare(
                "SELECT users.id, users.name, users.username, users.{$passwordColumn} AS password_hash, roles.name AS role_name{$selectActive}{$selectStoreId}{$selectPermissions}
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
                audit_log('login_failed', ['username' => $oldUsername]);
            } elseif ($activeColumn && (int) ($user['is_active'] ?? 1) !== 1) {
                $errors[] = 'Akun Anda nonaktif. Hubungi admin.';
                login_rate_limit_record($rateKey, false);
                security_log('login_blocked_inactive', ['username' => $oldUsername]);
                audit_log('login_blocked_inactive', ['username' => $oldUsername]);
            } elseif (!maintenance_allows_user(['role' => (string) ($user['role_name'] ?? '')]) && !empty($maintenanceConfig['active'])) {
                $errors[] = 'Sistem sedang maintenance. Sementara hanya super admin yang bisa masuk.';
                login_rate_limit_record($rateKey, false);
                security_log('login_blocked_maintenance', ['username' => $oldUsername]);
                audit_log('login_blocked_maintenance', ['username' => $oldUsername]);
            } else {
                $domainIssue = store_domain_access_issue($pdo, [
                    'role' => (string) ($user['role_name'] ?? ''),
                    'store_id' => isset($user['store_id']) ? (int) $user['store_id'] : 0,
                ]);
                if ($domainIssue !== null) {
                    $errors[] = (string) ($domainIssue['message'] ?? 'Akun tidak sesuai dengan domain toko ini.');
                    login_rate_limit_record($rateKey, false);
                    security_log('login_blocked_domain_access', [
                        'username' => $oldUsername,
                        'reason' => (string) ($domainIssue['code'] ?? 'domain_access'),
                        'host' => store_domain_current_host(),
                    ]);
                    audit_log('login_blocked_domain_access', [
                        'username' => $oldUsername,
                        'reason' => (string) ($domainIssue['code'] ?? 'domain_access'),
                        'host' => store_domain_current_host(),
                    ]);
                    return;
                }

                $storeAccessIssue = store_registration_access_issue($pdo, [
                    'role' => (string) ($user['role_name'] ?? ''),
                    'store_id' => isset($user['store_id']) ? (int) $user['store_id'] : 0,
                ]);
                if ($storeAccessIssue !== null) {
                    $errors[] = (string) ($storeAccessIssue['message'] ?? 'Akses toko sedang dibatasi.');
                    login_rate_limit_record($rateKey, false);
                    security_log('login_blocked_store_access', [
                        'username' => $oldUsername,
                        'reason' => (string) ($storeAccessIssue['code'] ?? 'store_access'),
                    ]);
                    audit_log('login_blocked_store_access', [
                        'username' => $oldUsername,
                        'reason' => (string) ($storeAccessIssue['code'] ?? 'store_access'),
                    ]);
                    return;
                }

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
                    'store_id' => isset($user['store_id']) ? (int) $user['store_id'] : null,
                    'permissions' => user_permissions([
                        'role' => $user['role_name'],
                        'permissions_json' => $user['permissions_json'] ?? null,
                    ]),
                    'permissions_json' => $user['permissions_json'] ?? null,
                ]);
                if (isset($user['store_id']) && (int) $user['store_id'] > 0) {
                    store_registration_touch_login($pdo, (int) $user['store_id']);
                }
                audit_log('login_success', [
                    'user_id' => (int) $user['id'],
                    'username' => (string) $user['username'],
                    'role' => (string) $user['role_name'],
                    'store_id' => isset($user['store_id']) ? (int) $user['store_id'] : null,
                    'legacy_password_rehashed' => $legacyMatched,
                ]);

                send_security_headers();
                header('Location: ' . role_redirect($user['role_name']));
                exit;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' && is_logged_in()) {
    $user = current_user();
    send_security_headers();
    header('Location: ' . role_redirect($user['role'] ?? ''));
    exit;
}
