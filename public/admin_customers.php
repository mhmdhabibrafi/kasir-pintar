<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/models/Customer.php';

require_role(['admin', 'bos']);

$pdo = db();
$errors = [];
$success = '';

$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
$editId = (int) ($_GET['edit'] ?? 0);
$editCustomer = $editId > 0 ? Customer::find($pdo, $editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    if (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mengubah data member.';
    } elseif (empty($errors)) {
        $action = (string) ($_POST['action'] ?? '');
        $normalizeCustomerInput = static function (array $source): array {
            return [
                'name' => trim((string) ($source['name'] ?? '')),
                'phone' => trim((string) ($source['phone'] ?? '')),
                'email' => trim((string) ($source['email'] ?? '')),
                'address' => trim((string) ($source['address'] ?? '')),
                'is_active' => isset($source['is_active']) ? 1 : 0,
            ];
        };
        $validateCustomerInput = static function (array $payload, ?int $excludeId = null) use (&$errors, $pdo): void {
            if ($payload['name'] === '') {
                $errors[] = 'Nama member wajib diisi.';
            }
            if ($payload['phone'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $payload['phone'])) {
                $errors[] = 'Format nomor HP tidak valid.';
            }
            if ($payload['email'] !== '' && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format email tidak valid.';
            }
            if ($payload['phone'] !== '' && Customer::phoneExists($pdo, $payload['phone'], $excludeId)) {
                $errors[] = 'Nomor HP sudah digunakan member lain.';
            }
        };

        if ($action === 'create') {
            $payload = $normalizeCustomerInput($_POST);
            $validateCustomerInput($payload);

            if (empty($errors)) {
                try {
                    $id = Customer::create($pdo, $payload);
                    $success = 'Member berhasil ditambahkan.';
                    audit_log('customer_created', ['customer_id' => $id, 'name' => $payload['name']]);
                } catch (Throwable $e) {
                    $errors[] = 'Gagal menambahkan member.';
                }
            }
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $customer = $id > 0 ? Customer::find($pdo, $id) : null;
            if (!$customer) {
                $errors[] = 'Member tidak ditemukan.';
            } else {
                $payload = $normalizeCustomerInput($_POST);
                $validateCustomerInput($payload, $id);
                if (empty($errors)) {
                    try {
                        Customer::update($pdo, $id, $payload);
                        $success = 'Member berhasil diperbarui.';
                        audit_log('customer_updated', ['customer_id' => $id, 'name' => $payload['name']]);
                    } catch (Throwable $e) {
                        $errors[] = 'Gagal memperbarui member.';
                    }
                }
            }
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (int) ($_POST['active'] ?? 0);
            if ($id > 0) {
                $customer = Customer::find($pdo, $id);
                if ($customer) {
                    try {
                        Customer::update($pdo, $id, [
                            'name' => (string) ($customer['name'] ?? ''),
                            'phone' => (string) ($customer['phone'] ?? ''),
                            'email' => (string) ($customer['email'] ?? ''),
                            'address' => (string) ($customer['address'] ?? ''),
                            'is_active' => $active === 1 ? 1 : 0,
                        ]);
                        $success = 'Status member berhasil diperbarui.';
                        audit_log('customer_status_updated', ['customer_id' => $id, 'is_active' => $active === 1 ? 1 : 0]);
                    } catch (Throwable $e) {
                        $errors[] = 'Gagal mengubah status member.';
                    }
                }
            }
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    Customer::delete($pdo, $id);
                    $success = 'Member berhasil dihapus.';
                    audit_log('customer_deleted', ['customer_id' => $id]);
                } catch (Throwable $e) {
                    $errors[] = 'Gagal menghapus member.';
                }
            }
        }
    }
}

$customers = Customer::all($pdo);
$summary = [
    'total' => count($customers),
    'active' => 0,
    'points' => 0,
    'spent' => 0.0,
];
foreach ($customers as $customer) {
    if (!empty($customer['is_active'])) {
        $summary['active']++;
    }
    $summary['points'] += (int) ($customer['points'] ?? 0);
    $summary['spent'] += (float) ($customer['total_spent'] ?? 0);
}

$title = 'Member Pelanggan';
require_once __DIR__ . '/../app/views/admin/customers.php';
