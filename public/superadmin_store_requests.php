<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/db_migration_helper.php';
require_once __DIR__ . '/../app/helpers/domain_helper.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/maintenance_helper.php';
require_once __DIR__ . '/../app/helpers/store_registration_helper.php';
require_once __DIR__ . '/../app/helpers/telegram_helper.php';

ensure_update_schema();
require_role(['superadmin']);

$pdo = db();
$user = current_user();
$errors = [];
$success = '';
$currentFilter = (string) ($_GET['status'] ?? 'all');
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$referralSearch = trim((string) ($_GET['code_q'] ?? ''));
$activeStoreSearch = trim((string) ($_GET['store_q'] ?? ''));
$focusSection = (string) ($_GET['focus'] ?? 'overview');

if (!in_array($currentFilter, ['all', 'pending', 'approved', 'rejected'], true)) {
    $currentFilter = 'all';
}
if (!in_array($focusSection, ['overview', 'referrals', 'requests', 'stores', 'maintenance'], true)) {
    $focusSection = 'overview';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $storeId = (int) ($_POST['store_id'] ?? 0);
        $note = trim((string) ($_POST['approval_note'] ?? ''));
        $referralId = (int) ($_POST['referral_id'] ?? 0);
        $domainId = (int) ($_POST['domain_id'] ?? 0);
        $customDomain = trim((string) ($_POST['custom_domain'] ?? ''));
        $operationalStatus = trim((string) ($_POST['operational_status'] ?? ''));
        $operationalNote = trim((string) ($_POST['operational_note'] ?? ''));

        try {
            if ($action === 'approve') {
                $result = store_registration_approve($pdo, $storeId, (int) ($user['id'] ?? 0));
                $focusSection = 'requests';
                $success = 'Toko berhasil disetujui. Akun admin toko dibuat dengan username ' . (string) ($result['store']['admin_username'] ?? '-');
                audit_log('store_registration_approved', [
                    'store_id' => $storeId,
                    'approved_by' => (int) ($user['id'] ?? 0),
                ]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Toko approved',
                    'store_name' => (string) ($result['store']['store_name'] ?? '-'),
                    'status' => 'approved',
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                ]);
            } elseif ($action === 'reject') {
                store_registration_reject($pdo, $storeId, (int) ($user['id'] ?? 0), $note);
                $focusSection = 'requests';
                $success = 'Pendaftaran toko ditolak.';
                audit_log('store_registration_rejected', [
                    'store_id' => $storeId,
                    'approved_by' => (int) ($user['id'] ?? 0),
                    'note' => $note,
                ]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Toko rejected',
                    'status' => 'rejected',
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                    'note' => $note,
                ]);
            } elseif ($action === 'generate_referral') {
                $referral = store_referral_create($pdo, [
                    'label' => trim((string) ($_POST['label'] ?? '')),
                    'note' => trim((string) ($_POST['note'] ?? '')),
                    'max_uses' => (int) ($_POST['max_uses'] ?? 1),
                    'expires_at' => trim((string) ($_POST['expires_at'] ?? '')),
                ], (int) ($user['id'] ?? 0));
                $focusSection = 'referrals';
                $success = 'Kode referral berhasil dibuat: ' . (string) ($referral['code'] ?? '-');
                audit_log('store_referral_created', [
                    'referral_id' => (int) ($referral['id'] ?? 0),
                    'code' => (string) ($referral['code'] ?? ''),
                ]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Referral dibuat',
                    'referral' => (string) ($referral['code'] ?? '-'),
                    'status' => 'active',
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                ]);
            } elseif ($action === 'update_referral') {
                $referral = store_referral_update($pdo, $referralId, [
                    'label' => trim((string) ($_POST['label'] ?? '')),
                    'note' => trim((string) ($_POST['note'] ?? '')),
                    'max_uses' => (int) ($_POST['max_uses'] ?? 1),
                    'expires_at' => trim((string) ($_POST['expires_at'] ?? '')),
                    'is_active' => !empty($_POST['is_active']),
                ]);
                $focusSection = 'referrals';
                $success = 'Referral ' . (string) ($referral['code'] ?? '-') . ' berhasil diperbarui.';
                audit_log('store_referral_updated', [
                    'referral_id' => $referralId,
                    'code' => (string) ($referral['code'] ?? ''),
                ]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Referral diperbarui',
                    'referral' => (string) ($referral['code'] ?? ('#' . $referralId)),
                    'status' => (int) ($referral['is_active'] ?? 0) === 1 ? 'active' : 'inactive',
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                ]);
            } elseif ($action === 'deactivate_referral') {
                store_referral_set_active($pdo, $referralId, false);
                $focusSection = 'referrals';
                $success = 'Kode referral berhasil dinonaktifkan.';
                audit_log('store_referral_deactivated', ['referral_id' => $referralId]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Referral dinonaktifkan',
                    'referral' => '#' . $referralId,
                    'status' => 'inactive',
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                ]);
            } elseif ($action === 'activate_referral') {
                store_referral_set_active($pdo, $referralId, true);
                $focusSection = 'referrals';
                $success = 'Kode referral berhasil diaktifkan kembali.';
                audit_log('store_referral_activated', ['referral_id' => $referralId]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Referral diaktifkan',
                    'referral' => '#' . $referralId,
                    'status' => 'active',
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                ]);
            } elseif ($action === 'delete_referral') {
                $deletedReferral = store_referral_delete($pdo, $referralId);
                $focusSection = 'referrals';
                $success = 'Referral ' . (string) ($deletedReferral['code'] ?? ('#' . $referralId)) . ' berhasil dihapus.';
                audit_log('store_referral_deleted', [
                    'referral_id' => $referralId,
                    'code' => (string) ($deletedReferral['code'] ?? ''),
                ]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Referral dihapus',
                    'referral' => (string) ($deletedReferral['code'] ?? ('#' . $referralId)),
                    'status' => 'deleted',
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                ]);
            } elseif ($action === 'save_maintenance') {
                $focusSection = 'maintenance';
                $maintenanceConfig = maintenance_set_config([
                    'active' => !empty($_POST['maintenance_active']),
                    'title' => trim((string) ($_POST['maintenance_title'] ?? '')),
                    'message' => trim((string) ($_POST['maintenance_message'] ?? '')),
                ], $user);
                $success = !empty($maintenanceConfig['active'])
                    ? 'Mode maintenance berhasil diaktifkan.'
                    : 'Mode maintenance berhasil dimatikan.';
                audit_log('maintenance_updated', [
                    'active' => !empty($maintenanceConfig['active']),
                    'updated_by' => (int) ($user['id'] ?? 0),
                ]);
                telegram_notify_system_event('maintenance', [
                    'event' => !empty($maintenanceConfig['active']) ? 'Maintenance aktif' : 'Maintenance nonaktif',
                    'status' => !empty($maintenanceConfig['active']) ? 'active' : 'inactive',
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                    'note' => (string) ($maintenanceConfig['title'] ?? ''),
                ]);
            } elseif ($action === 'set_store_operational_status') {
                $focusSection = 'stores';
                $updatedStore = store_registration_set_operational_status(
                    $pdo,
                    $storeId,
                    $operationalStatus,
                    (int) ($user['id'] ?? 0),
                    $operationalNote
                );
                $success = store_registration_operational_status($updatedStore) === 'suspended'
                    ? 'Akses toko berhasil disuspend.'
                    : 'Akses toko berhasil diaktifkan kembali.';
                audit_log('store_operational_status_updated', [
                    'store_id' => $storeId,
                    'operational_status' => store_registration_operational_status($updatedStore),
                    'note' => $operationalNote,
                ]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Status operasional toko berubah',
                    'store_name' => (string) ($updatedStore['store_name'] ?? '-'),
                    'status' => store_registration_operational_status($updatedStore),
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                    'note' => $operationalNote,
                ]);
            } elseif ($action === 'add_store_domain') {
                $focusSection = 'stores';
                $domain = store_domain_create($pdo, $storeId, $customDomain, (int) ($user['id'] ?? 0));
                $success = 'Domain toko berhasil ditambahkan: ' . (string) ($domain['domain'] ?? $customDomain);
                audit_log('store_domain_added', [
                    'store_id' => $storeId,
                    'domain_id' => (int) ($domain['id'] ?? 0),
                    'domain' => (string) ($domain['domain'] ?? $customDomain),
                ]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Custom domain ditambahkan',
                    'domain' => (string) ($domain['domain'] ?? $customDomain),
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                ]);
            } elseif ($action === 'deactivate_store_domain') {
                $focusSection = 'stores';
                store_domain_deactivate($pdo, $domainId, $storeId);
                $success = 'Domain toko berhasil dinonaktifkan.';
                audit_log('store_domain_deactivated', [
                    'store_id' => $storeId,
                    'domain_id' => $domainId,
                ]);
                telegram_notify_system_event('tenant', [
                    'event' => 'Custom domain dinonaktifkan',
                    'status' => 'inactive',
                    'actor' => (string) ($user['username'] ?? 'superadmin'),
                ]);
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage() !== '' ? $e->getMessage() : 'Gagal memproses tindakan super admin.';
        }
    }
}

$counts = store_registration_counts($pdo);
$dashboardStats = store_registration_dashboard_stats($pdo);
$stores = store_registration_all($pdo, $currentFilter, $searchQuery);
$pendingQueue = store_registration_recent($pdo, 'pending', 5);
$approvedStores = store_registration_recent($pdo, 'approved', 6);
$storeDirectory = store_registration_directory($pdo, $activeStoreSearch);
foreach ($storeDirectory as &$storeRow) {
    $storeRow['domains'] = store_domain_all_by_store($pdo, (int) ($storeRow['id'] ?? 0));
}
unset($storeRow);
$storeHealthSummary = store_registration_health_summary($storeDirectory);
$attentionStores = array_values(array_filter($storeDirectory, static function (array $store): bool {
    return (string) ($store['health_level'] ?? 'healthy') !== 'healthy';
}));
$attentionStores = array_slice($attentionStores, 0, 5);
$rejectedStores = store_registration_recent($pdo, 'rejected', 4);
$recentStores = store_registration_recent($pdo, 'all', 6);
$referralStats = store_referral_stats($pdo);
$referrals = store_referral_all($pdo, $referralSearch);
$maintenanceConfig = $maintenanceConfig ?? maintenance_get_config();
$hasFilters = $currentFilter !== 'all' || $searchQuery !== '' || $referralSearch !== '';
$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format('d M Y, H:i');
    } catch (Throwable $e) {
        return $value;
    }
};
$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format('d M Y');
    } catch (Throwable $e) {
        return $value;
    }
};
$title = 'Panel Super Admin';

require_once __DIR__ . '/../app/views/admin/store_requests.php';
