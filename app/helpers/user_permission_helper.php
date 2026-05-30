<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/system_state_helper.php';
require_once __DIR__ . '/../models/User.php';

function cashier_permission_catalog(): array
{
    return [
        [
            'key' => 'access_cashier',
            'label' => 'Buka halaman kasir',
            'description' => 'Kasir bisa membuka POS dan menyiapkan transaksi.',
        ],
        [
            'key' => 'access_shift',
            'label' => 'Buka halaman shift',
            'description' => 'Kasir bisa membuka dan menutup shift miliknya.',
        ],
        [
            'key' => 'access_history',
            'label' => 'Lihat riwayat transaksi',
            'description' => 'Kasir bisa melihat daftar transaksi yang pernah dibuat.',
        ],
        [
            'key' => 'hold_transaction',
            'label' => 'Hold transaksi',
            'description' => 'Kasir bisa menyimpan, membuka, dan menghapus hold transaksi.',
        ],
        [
            'key' => 'void_item',
            'label' => 'Void item',
            'description' => 'Kasir bisa menghapus item dari keranjang dengan alasan void.',
        ],
        [
            'key' => 'adjust_pricing',
            'label' => 'Atur diskon dan komponen harga',
            'description' => 'Kasir bisa memakai diskon item, voucher, pajak, service, dan pembulatan.',
        ],
        [
            'key' => 'pay_cash',
            'label' => 'Bayar dengan cash',
            'description' => 'Kasir bisa memproses pembayaran tunai.',
        ],
        [
            'key' => 'pay_qris',
            'label' => 'Bayar dengan QRIS',
            'description' => 'Kasir bisa memproses pembayaran QRIS.',
        ],
        [
            'key' => 'print_receipt',
            'label' => 'Cetak struk browser',
            'description' => 'Kasir bisa membuka struk dan print lewat browser.',
        ],
        [
            'key' => 'bluetooth_print',
            'label' => 'Cetak struk Bluetooth',
            'description' => 'Kasir bisa mencetak struk ke printer Bluetooth atau serial.',
        ],
    ];
}

function cashier_default_permissions(): array
{
    $defaults = [];
    foreach (cashier_permission_catalog() as $permission) {
        $defaults[(string) $permission['key']] = true;
    }

    return $defaults;
}

function cashier_permissions_from_request($raw): array
{
    $permissions = [];
    $input = is_array($raw) ? $raw : [];

    foreach (cashier_permission_catalog() as $permission) {
        $key = (string) $permission['key'];
        $permissions[$key] = array_key_exists($key, $input) && !empty($input[$key]);
    }

    return $permissions;
}

function normalize_cashier_permissions($raw): array
{
    $defaults = cashier_default_permissions();

    if (!is_array($raw)) {
        return $defaults;
    }

    foreach (cashier_permission_catalog() as $permission) {
        $key = (string) $permission['key'];
        if (array_key_exists($key, $raw)) {
            $defaults[$key] = !empty($raw[$key]);
        }
    }

    return $defaults;
}

function cashier_permissions_to_json(array $permissions): string
{
    $payload = json_encode($permissions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $payload === false ? '{}' : $payload;
}

function user_permissions(?array $user): array
{
    $role = (string) ($user['role'] ?? $user['role_name'] ?? '');
    if ($role === '' || in_array($role, ['superadmin', 'admin', 'bos'], true)) {
        return cashier_default_permissions();
    }

    if ($role !== 'karyawan') {
        return cashier_default_permissions();
    }

    $userId = isset($user['id']) ? (int) $user['id'] : 0;
    if ($userId > 0) {
        $livePermissions = cashier_live_permissions($userId);
        if (is_array($livePermissions)) {
            return $livePermissions;
        }
    }

    if (isset($user['permissions']) && is_array($user['permissions'])) {
        return normalize_cashier_permissions($user['permissions']);
    }

    $rawJson = $user['permissions_json'] ?? null;
    if (is_string($rawJson) && trim($rawJson) !== '') {
        $decoded = json_decode($rawJson, true);
        if (is_array($decoded)) {
            return normalize_cashier_permissions($decoded);
        }
    }

    return cashier_default_permissions();
}

function cashier_live_permissions(int $userId): ?array
{
    static $cache = [];

    if ($userId <= 0) {
        return null;
    }
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    if (!function_exists('db')) {
        $cache[$userId] = null;
        return null;
    }

    try {
        $pdo = db();
        $permissionColumn = User::permissionColumn($pdo);
        $selectPermissions = $permissionColumn ? ', users.' . $permissionColumn . ' AS permissions_json' : '';
        $stmt = $pdo->prepare(
            'SELECT roles.name AS role_name' . $selectPermissions . '
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            $cache[$userId] = null;
            return null;
        }

        if ((string) ($row['role_name'] ?? '') !== 'karyawan') {
            $cache[$userId] = cashier_default_permissions();
            return $cache[$userId];
        }

        $rawJson = $row['permissions_json'] ?? null;
        if (is_string($rawJson) && trim($rawJson) !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $cache[$userId] = normalize_cashier_permissions($decoded);
                return $cache[$userId];
            }
        }

        $cache[$userId] = cashier_default_permissions();
        return $cache[$userId];
    } catch (Throwable $e) {
        $cache[$userId] = null;
        return null;
    }
}

function user_can(?array $user, string $permission): bool
{
    $permissions = user_permissions($user);
    return !empty($permissions[$permission]);
}

function user_permission_home(array $user): string
{
    $role = (string) ($user['role'] ?? '');
    if ($role !== 'karyawan') {
        return role_redirect($role);
    }

    if (user_can($user, 'access_cashier')) {
        return base_url('kasir.php');
    }
    if (user_can($user, 'access_shift')) {
        return base_url('shift.php');
    }
    if (user_can($user, 'access_history')) {
        return base_url('kasir_history.php');
    }

    return base_url('logout.php');
}

function user_permission_guard(array $user, string $permission, string $message): void
{
    if (user_can($user, $permission)) {
        return;
    }

    $actions = [];
    $homeUrl = user_permission_home($user);
    if ($homeUrl !== '') {
        $actions[] = [
            'label' => 'Halaman yang tersedia',
            'url' => $homeUrl,
            'icon' => 'arrow_forward',
            'primary' => true,
        ];
    }
    $actions[] = [
        'label' => 'Keluar',
        'url' => base_url('logout.php'),
        'icon' => 'logout',
        'primary' => false,
    ];

    render_system_state_page(
        'Akses akun dibatasi',
        $message,
        [
            'title' => 'Akses Dibatasi',
            'status_code' => 403,
            'icon' => 'lock',
            'actions' => $actions,
        ]
    );
}
