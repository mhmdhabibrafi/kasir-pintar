<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/user_permission_helper.php';
require_once __DIR__ . '/system_state_helper.php';

function policy_map(): array
{
    return [
        'tenant.manage' => ['superadmin'],
        'tenant.approve' => ['superadmin'],
        'tenant.maintenance' => ['superadmin'],
        'system.health' => ['superadmin'],
        'system.backup' => ['superadmin'],
        'audit.view' => ['superadmin', 'admin', 'bos'],
        'notification.manage' => ['superadmin', 'admin'],
        'store.view' => ['admin', 'bos'],
        'store.manage' => ['admin'],
        'dashboard.view' => ['admin', 'bos'],
        'product.view' => ['admin', 'bos'],
        'product.manage' => ['admin'],
        'category.view' => ['admin', 'bos'],
        'category.manage' => ['admin'],
        'inventory.view' => ['admin', 'bos'],
        'inventory.manage' => ['admin'],
        'promo.view' => ['admin', 'bos'],
        'promo.manage' => ['admin'],
        'customer.view' => ['admin', 'bos'],
        'customer.manage' => ['admin'],
        'transaction.view' => ['admin', 'bos'],
        'transaction.manage' => ['admin'],
        'refund.view' => ['admin', 'bos'],
        'refund.manage' => ['admin'],
        'cash_report.view' => ['admin', 'bos'],
        'cash_report.export' => ['admin', 'bos'],
        'shift.view' => ['admin', 'bos', 'karyawan'],
        'shift.manage_cash' => ['admin', 'bos'],
        'support.view' => ['superadmin', 'admin', 'bos'],
        'cashier.view' => ['admin', 'bos', 'karyawan'],
        'cashier.sell' => ['admin', 'bos', 'karyawan'],
        'receipt.print' => ['admin', 'bos', 'karyawan'],
        'user.view' => ['admin', 'bos'],
        'user.manage' => ['admin'],
    ];
}

function policy_allows(?array $user, string $ability): bool
{
    if (!$user) {
        return false;
    }

    $role = (string) ($user['role'] ?? '');
    $allowedRoles = policy_map()[$ability] ?? [];
    if (!in_array($role, $allowedRoles, true)) {
        return false;
    }

    $cashierAbilityMap = [
        'cashier.view' => 'access_cashier',
        'cashier.sell' => 'access_cashier',
        'shift.view' => 'access_shift',
        'transaction.view' => 'access_history',
        'receipt.print' => 'print_receipt',
    ];

    if ($role === 'karyawan' && isset($cashierAbilityMap[$ability])) {
        return user_can($user, $cashierAbilityMap[$ability]);
    }

    return true;
}

function require_policy(string $ability, string $message = 'Akses halaman ini dibatasi.'): void
{
    $user = current_user();
    if (policy_allows($user, $ability)) {
        return;
    }

    audit_log('policy_denied', [
        'ability' => $ability,
        'role' => $user['role'] ?? null,
        'user_id' => $user['id'] ?? null,
    ]);

    render_system_state_page(
        'Akses Ditolak',
        $message,
        [
            'title' => 'Akses Ditolak',
            'status_code' => 403,
            'icon' => 'lock',
            'actions' => [
                [
                    'label' => 'Kembali',
                    'url' => role_redirect((string) ($user['role'] ?? '')),
                    'icon' => 'arrow_back',
                    'primary' => true,
                ],
            ],
        ]
    );
}
