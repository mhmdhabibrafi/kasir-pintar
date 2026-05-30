<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/promo_helper.php';

require_role(['admin', 'bos']);

$errors = [];
$success = '';
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

$config = promo_get_config();
$defaults = $config['defaults'] ?? [];
$vouchers = $config['vouchers'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mengubah promo dan pajak.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'update_defaults') {
            $defaults['tax_percent'] = max(0, min(100, (float) ($_POST['tax_percent'] ?? 0)));
            $defaults['service_percent'] = max(0, min(100, (float) ($_POST['service_percent'] ?? 0)));
            $defaults['rounding_mode'] = in_array(($_POST['rounding_mode'] ?? 'none'), ['none', 'nearest', 'up', 'down'], true)
                ? (string) $_POST['rounding_mode']
                : 'none';
            $defaults['rounding_unit'] = max(1, (int) ($_POST['rounding_unit'] ?? 100));

            $config['defaults'] = $defaults;
            promo_save_config($config);
            $success = 'Pengaturan default berhasil disimpan.';
        } elseif ($action === 'add_voucher') {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $type = (string) ($_POST['type'] ?? 'amount');
            $value = max(0, (float) ($_POST['value'] ?? 0));
            $minTotal = max(0, (float) ($_POST['min_total'] ?? 0));
            $max = max(0, (float) ($_POST['max'] ?? 0));
            $expires = trim((string) ($_POST['expires'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $active = isset($_POST['active']) ? true : false;

            if ($code === '') {
                $errors[] = 'Kode voucher wajib diisi.';
            } else {
                foreach ($vouchers as $voucher) {
                    if (strtoupper((string) ($voucher['code'] ?? '')) === $code) {
                        $errors[] = 'Kode voucher sudah digunakan.';
                        break;
                    }
                }
            }

            if (empty($errors)) {
                $vouchers[] = [
                    'code' => $code,
                    'type' => $type === 'percent' ? 'percent' : 'amount',
                    'value' => $value,
                    'min_total' => $minTotal,
                    'max' => $max,
                    'expires' => $expires,
                    'name' => $name,
                    'active' => $active,
                ];
                $config['vouchers'] = $vouchers;
                promo_save_config($config);
                $success = 'Voucher berhasil ditambahkan.';
            }
        } elseif ($action === 'toggle_voucher') {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            foreach ($vouchers as $index => $voucher) {
                if (strtoupper((string) ($voucher['code'] ?? '')) === $code) {
                    $vouchers[$index]['active'] = !empty($voucher['active']) ? false : true;
                    break;
                }
            }
            $config['vouchers'] = $vouchers;
            promo_save_config($config);
            $success = 'Status voucher diperbarui.';
        } elseif ($action === 'delete_voucher') {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $vouchers = array_values(array_filter($vouchers, static fn ($voucher) => strtoupper((string) ($voucher['code'] ?? '')) !== $code));
            $config['vouchers'] = $vouchers;
            promo_save_config($config);
            $success = 'Voucher dihapus.';
        }

        $config = promo_get_config();
        $defaults = $config['defaults'] ?? [];
        $vouchers = $config['vouchers'] ?? [];
    }
}

$title = 'Promo & Pajak';
require_once __DIR__ . '/../app/views/admin/promos.php';
