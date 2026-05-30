<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/db_migration_helper.php';
require_once __DIR__ . '/../helpers/maintenance_helper.php';
require_once __DIR__ . '/../helpers/store_registration_helper.php';
require_once __DIR__ . '/../helpers/validation_helper.php';

$registrationErrors = [];
$registrationSuccess = '';
$registrationOld = [
    'referral_code' => '',
    'store_name' => '',
    'store_tagline' => '',
    'store_address' => '',
    'store_phone' => '',
    'store_email' => '',
    'owner_name' => '',
    'owner_email' => '',
    'owner_phone' => '',
    'admin_name' => '',
    'admin_username' => '',
];
$prefilledReferral = strtoupper(trim((string) ($_GET['ref'] ?? $_GET['referral'] ?? '')));
if ($prefilledReferral !== '') {
    $registrationOld['referral_code'] = $prefilledReferral;
}
$maintenanceConfig = maintenance_get_config();
$registrationBlocked = !empty($maintenanceConfig['active']);

ensure_update_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['form_action'] ?? '') === 'register_store') {
    $registrationRateKey = login_rate_limit_key(
        'store-register|' . (string) ($_POST['referral_code'] ?? '') . '|' . (string) ($_POST['admin_username'] ?? '')
    );
    $rate = login_rate_limit_check($registrationRateKey);

    if (!$rate['allowed']) {
        $registrationErrors[] = 'Terlalu banyak percobaan pendaftaran. Coba lagi dalam ' . (int) $rate['retry_after'] . ' detik.';
        security_log('store_registration_rate_limited', [
            'referral_code' => strtoupper(trim((string) ($_POST['referral_code'] ?? ''))),
            'admin_username' => normalize_username((string) ($_POST['admin_username'] ?? '')),
        ]);
        return;
    }

    if ($registrationBlocked) {
        $registrationErrors[] = 'Pendaftaran toko sedang ditutup sementara karena sistem masuk mode maintenance.';
        login_rate_limit_record($registrationRateKey, false);
        security_log('store_registration_blocked_maintenance', [
            'referral_code' => strtoupper(trim((string) ($_POST['referral_code'] ?? ''))),
            'admin_username' => normalize_username((string) ($_POST['admin_username'] ?? '')),
        ]);
        return;
    }

    $honeypot = trim((string) ($_POST['company_website'] ?? ''));
    if ($honeypot !== '') {
        login_rate_limit_record($registrationRateKey, false);
        security_log('store_registration_honeypot_triggered', [
            'referral_code' => strtoupper(trim((string) ($_POST['referral_code'] ?? ''))),
            'admin_username' => normalize_username((string) ($_POST['admin_username'] ?? '')),
        ]);
        $registrationErrors[] = 'Pendaftaran toko gagal diproses. Silakan coba lagi.';
        return;
    }

    foreach ($registrationOld as $key => $value) {
        $registrationOld[$key] = trim((string) ($_POST[$key] ?? ''));
        if ($key === 'referral_code') {
            $registrationOld[$key] = strtoupper($registrationOld[$key]);
        }
    }
    $registrationOld['store_phone'] = normalize_phone($registrationOld['store_phone']);
    $registrationOld['owner_phone'] = normalize_phone($registrationOld['owner_phone']);
    $registrationOld['admin_username'] = normalize_username($registrationOld['admin_username']);

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $registrationErrors[] = 'Sesi pendaftaran tidak valid. Silakan muat ulang halaman.';
    }
    if ($registrationOld['store_name'] === '') {
        $registrationErrors[] = 'Nama toko wajib diisi.';
    }
    if ($registrationOld['referral_code'] === '') {
        $registrationErrors[] = 'Kode referral wajib diisi.';
    }
    if ($registrationOld['store_address'] === '') {
        $registrationErrors[] = 'Alamat toko wajib diisi.';
    }
    if ($registrationOld['owner_name'] === '') {
        $registrationErrors[] = 'Nama pemilik wajib diisi.';
    }
    if ($registrationOld['admin_name'] === '') {
        $registrationErrors[] = 'Nama admin toko wajib diisi.';
    }
    if ($registrationOld['admin_username'] === '') {
        $registrationErrors[] = 'Username admin toko wajib diisi.';
    } elseif (!is_valid_username($registrationOld['admin_username'])) {
        $registrationErrors[] = 'Username admin toko hanya boleh berisi huruf, angka, titik, strip, atau underscore (3-50 karakter).';
    }
    if ($password === '') {
        $registrationErrors[] = 'Password admin toko wajib diisi.';
    }
    if (strlen($password) < 8) {
        $registrationErrors[] = 'Password admin toko minimal 8 karakter.';
    }
    if ($password !== $passwordConfirmation) {
        $registrationErrors[] = 'Konfirmasi password admin toko tidak cocok.';
    }
    foreach (['store_email' => 'Email toko', 'owner_email' => 'Email pemilik'] as $field => $label) {
        if ($registrationOld[$field] !== '' && filter_var($registrationOld[$field], FILTER_VALIDATE_EMAIL) === false) {
            $registrationErrors[] = $label . ' tidak valid.';
        }
    }
    foreach (['store_phone' => 'Telepon toko', 'owner_phone' => 'Telepon pemilik'] as $field => $label) {
        if ($registrationOld[$field] !== '' && !is_valid_phone($registrationOld[$field])) {
            $registrationErrors[] = $label . ' tidak valid.';
        }
    }

    if (empty($registrationErrors)) {
        try {
            $pdo = db();
            if (store_registration_username_exists($pdo, $registrationOld['admin_username'])) {
                $registrationErrors[] = 'Username admin toko sudah digunakan.';
            }

            if (empty($registrationErrors)) {
                $storeId = store_registration_submit($pdo, [
                    'referral_code' => strtoupper($registrationOld['referral_code']),
                    'store_name' => $registrationOld['store_name'],
                    'store_tagline' => $registrationOld['store_tagline'],
                    'store_address' => $registrationOld['store_address'],
                    'store_phone' => $registrationOld['store_phone'],
                    'store_email' => $registrationOld['store_email'],
                    'receipt_footer' => 'Terima kasih!',
                    'owner_name' => $registrationOld['owner_name'],
                    'owner_email' => $registrationOld['owner_email'],
                    'owner_phone' => $registrationOld['owner_phone'],
                    'admin_name' => $registrationOld['admin_name'],
                    'admin_username' => $registrationOld['admin_username'],
                    'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                $registrationSuccess = 'Pendaftaran toko berhasil dikirim dengan kode referral resmi. Menunggu persetujuan super admin. ID pengajuan: #' . $storeId;
                audit_log('store_registration_submitted', [
                    'store_id' => $storeId,
                    'store_name' => $registrationOld['store_name'],
                    'admin_username' => $registrationOld['admin_username'],
                    'referral_code' => strtoupper($registrationOld['referral_code']),
                ]);
                login_rate_limit_record($registrationRateKey, true);
                foreach ($registrationOld as $key => $value) {
                    $registrationOld[$key] = '';
                }
            }
        } catch (Throwable $e) {
            $registrationErrors[] = $e->getMessage() !== '' ? $e->getMessage() : 'Pendaftaran toko gagal diproses. Silakan coba lagi.';
            security_log('store_registration_failed', [
                'reason' => $e->getMessage(),
                'referral_code' => strtoupper($registrationOld['referral_code']),
                'admin_username' => $registrationOld['admin_username'],
                'store_name' => $registrationOld['store_name'],
            ]);
        }
    }

    if (!empty($registrationErrors)) {
        login_rate_limit_record($registrationRateKey, false);
        security_log('store_registration_validation_failed', [
            'errors_count' => count($registrationErrors),
            'referral_code' => strtoupper($registrationOld['referral_code']),
            'admin_username' => $registrationOld['admin_username'],
            'store_name' => $registrationOld['store_name'],
        ]);
    }
}
