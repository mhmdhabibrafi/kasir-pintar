<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/db_migration_helper.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/validation_helper.php';

ensure_update_schema();
require_role(['superadmin']);

$pdo = db();
$errors = [];
$success = '';

function superadmin_store_edit_find(PDO $pdo, int $storeId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT stores.*,
                approver.name AS approved_by_name,
                suspender.name AS suspended_by_name
         FROM stores
         LEFT JOIN users AS approver ON approver.id = stores.approved_by
         LEFT JOIN users AS suspender ON suspender.id = stores.suspended_by
         WHERE stores.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $storeId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function superadmin_store_edit_clean(array $source): array
{
    return [
        'store_name' => trim((string) ($source['store_name'] ?? '')),
        'store_tagline' => trim((string) ($source['store_tagline'] ?? '')),
        'store_address' => trim((string) ($source['store_address'] ?? '')),
        'store_phone' => normalize_phone((string) ($source['store_phone'] ?? '')),
        'store_email' => trim((string) ($source['store_email'] ?? '')),
        'store_whatsapp' => normalize_phone((string) ($source['store_whatsapp'] ?? '')),
        'store_instagram' => trim((string) ($source['store_instagram'] ?? '')),
        'store_city' => trim((string) ($source['store_city'] ?? '')),
        'store_province' => trim((string) ($source['store_province'] ?? '')),
        'postal_code' => trim((string) ($source['postal_code'] ?? '')),
        'business_hours' => trim((string) ($source['business_hours'] ?? '')),
        'google_maps_url' => trim((string) ($source['google_maps_url'] ?? '')),
        'receipt_footer' => trim((string) ($source['receipt_footer'] ?? '')),
        'owner_name' => trim((string) ($source['owner_name'] ?? '')),
        'owner_email' => trim((string) ($source['owner_email'] ?? '')),
        'owner_phone' => normalize_phone((string) ($source['owner_phone'] ?? '')),
        'admin_name' => trim((string) ($source['admin_name'] ?? '')),
    ];
}

$storeId = (int) ($_GET['id'] ?? 0);
$store = $storeId > 0 ? superadmin_store_edit_find($pdo, $storeId) : null;
$formStore = $store ? superadmin_store_edit_clean($store) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $store) {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $formStore = superadmin_store_edit_clean($_POST);

        if ($formStore['store_name'] === '') {
            $errors[] = 'Nama toko wajib diisi.';
        }
        if ($formStore['owner_name'] === '') {
            $errors[] = 'Nama pemilik wajib diisi.';
        }
        if ($formStore['admin_name'] === '') {
            $errors[] = 'Nama admin toko wajib diisi.';
        }
        foreach (['store_email' => 'Email toko', 'owner_email' => 'Email pemilik'] as $field => $label) {
            if ($formStore[$field] !== '' && filter_var($formStore[$field], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = $label . ' tidak valid.';
            }
        }
        foreach (['store_phone' => 'Telepon toko', 'store_whatsapp' => 'WhatsApp toko', 'owner_phone' => 'Telepon pemilik'] as $field => $label) {
            if ($formStore[$field] !== '' && !is_valid_phone($formStore[$field])) {
                $errors[] = $label . ' tidak valid.';
            }
        }
        if ($formStore['google_maps_url'] !== '' && filter_var($formStore['google_maps_url'], FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Link Google Maps tidak valid.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'UPDATE stores
                 SET store_name = :store_name,
                     store_tagline = :store_tagline,
                     store_address = :store_address,
                     store_phone = :store_phone,
                     store_email = :store_email,
                     store_whatsapp = :store_whatsapp,
                     store_instagram = :store_instagram,
                     store_city = :store_city,
                     store_province = :store_province,
                     postal_code = :postal_code,
                     business_hours = :business_hours,
                     google_maps_url = :google_maps_url,
                     receipt_footer = :receipt_footer,
                     owner_name = :owner_name,
                     owner_email = :owner_email,
                     owner_phone = :owner_phone,
                     admin_name = :admin_name
                 WHERE id = :id'
            );
            $params = [':id' => $storeId];
            foreach ($formStore as $field => $value) {
                $params[':' . $field] = $value;
            }
            $stmt->execute($params);

            $success = 'Profil toko berhasil diperbarui.';
            audit_log('superadmin_store_profile_updated', [
                'store_id' => $storeId,
                'store_name' => $formStore['store_name'],
            ]);
            $store = superadmin_store_edit_find($pdo, $storeId);
            $formStore = $store ? superadmin_store_edit_clean($store) : $formStore;
        }
    }
}

$title = 'Edit Profil Toko';

require_once __DIR__ . '/../app/views/admin/superadmin_store_edit.php';
