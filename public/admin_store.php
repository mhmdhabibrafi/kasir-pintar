<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/db_migration_helper.php';
require_once __DIR__ . '/../app/helpers/store_info_helper.php';
require_once __DIR__ . '/../app/helpers/validation_helper.php';

ensure_update_schema();
require_role(['admin', 'bos']);

$errors = [];
$success = '';
$storeInfo = store_info_get();
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

$storeStatusMap = [
    'approved' => ['label' => 'Aktif', 'tone' => 'success'],
    'pending' => ['label' => 'Menunggu approval', 'tone' => 'warning'],
    'rejected' => ['label' => 'Perlu ditinjau ulang', 'tone' => 'danger'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mengubah informasi toko.';
    } else {
        $storeName = trim((string) ($_POST['store_name'] ?? ''));
        $storeTagline = trim((string) ($_POST['store_tagline'] ?? ''));
        $storeAddress = trim((string) ($_POST['store_address'] ?? ''));
        $storeCity = trim((string) ($_POST['store_city'] ?? ''));
        $storeProvince = trim((string) ($_POST['store_province'] ?? ''));
        $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
        $storePhone = trim((string) ($_POST['store_phone'] ?? ''));
        $storeWhatsapp = trim((string) ($_POST['store_whatsapp'] ?? ''));
        $storeEmail = trim((string) ($_POST['store_email'] ?? ''));
        $storeInstagram = trim((string) ($_POST['store_instagram'] ?? ''));
        $businessHours = trim((string) ($_POST['business_hours'] ?? ''));
        $googleMapsUrl = trim((string) ($_POST['google_maps_url'] ?? ''));
        $receiptFooter = trim((string) ($_POST['receipt_footer'] ?? ''));

        if ($storeName === '') {
            $errors[] = 'Nama toko wajib diisi.';
        }
        $storeNameLength = function_exists('mb_strlen') ? mb_strlen($storeName) : strlen($storeName);
        if ($storeNameLength < 3) {
            $errors[] = 'Nama toko minimal 3 karakter.';
        }
        if ($storeAddress === '') {
            $errors[] = 'Alamat toko wajib diisi.';
        }
        if ($storePhone !== '' && !is_valid_phone($storePhone)) {
            $errors[] = 'Format nomor telepon toko tidak valid.';
        }
        if ($storeWhatsapp !== '' && !is_valid_phone($storeWhatsapp)) {
            $errors[] = 'Format nomor WhatsApp bisnis tidak valid.';
        }
        if ($storeEmail !== '' && filter_var($storeEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Format email toko tidak valid.';
        }
        if ($postalCode !== '' && preg_match('/^[A-Za-z0-9 -]{3,12}$/', $postalCode) !== 1) {
            $errors[] = 'Format kode pos tidak valid.';
        }
        if ($googleMapsUrl !== '') {
            $isValidUrl = filter_var($googleMapsUrl, FILTER_VALIDATE_URL) !== false;
            $isHttpUrl = preg_match('~^https?://~i', $googleMapsUrl) === 1;
            if (!$isValidUrl || !$isHttpUrl) {
                $errors[] = 'Link Google Maps harus berupa URL http/https yang valid.';
            }
        }

        if (empty($errors)) {
            $storeInfo = store_info_save([
                'store_name' => $storeName,
                'store_tagline' => $storeTagline,
                'store_address' => $storeAddress,
                'store_city' => $storeCity,
                'store_province' => $storeProvince,
                'postal_code' => $postalCode,
                'store_phone' => normalize_phone($storePhone),
                'store_whatsapp' => normalize_phone($storeWhatsapp),
                'store_email' => $storeEmail,
                'store_instagram' => $storeInstagram,
                'business_hours' => $businessHours,
                'google_maps_url' => $googleMapsUrl,
                'receipt_footer' => $receiptFooter,
            ]);
            $success = 'Profil toko berhasil diperbarui.';
            audit_log('store_info_updated', ['updated_by' => (int) (current_user()['id'] ?? 0)]);
        }
    }
}

$profileCompletion = store_info_completion($storeInfo);
$storeFullAddress = store_info_compose_address($storeInfo);
$looksLikeDemoProfile = store_info_is_demo_profile($storeInfo);
$storeStatusKey = strtolower(trim((string) ($storeInfo['status'] ?? '')));
$storeStatusMeta = $storeStatusMap[$storeStatusKey] ?? ['label' => 'Profil internal', 'tone' => 'secondary'];
$updatedAtValue = trim((string) ($storeInfo['updated_at'] ?? ''));
$updatedAtLabel = $updatedAtValue !== '' ? date('d M Y H:i', strtotime($updatedAtValue)) : '-';

$title = 'Informasi Toko';
require_once __DIR__ . '/../app/views/admin/store.php';
