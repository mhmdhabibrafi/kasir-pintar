<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/data_store.php';
require_once __DIR__ . '/system_state_helper.php';

function maintenance_default_config(): array
{
    return [
        'active' => false,
        'title' => 'Sistem sedang maintenance',
        'message' => 'KASPINDO sementara masuk mode maintenance agar update sistem bisa dikerjakan dengan aman.' . "\n" . 'Silakan tunggu beberapa saat lagi.',
        'updated_at' => null,
        'updated_by' => null,
        'updated_by_name' => '',
    ];
}

function maintenance_normalize_config(array $config): array
{
    $defaults = maintenance_default_config();

    return [
        'active' => !empty($config['active']),
        'title' => trim((string) ($config['title'] ?? $defaults['title'])) ?: $defaults['title'],
        'message' => trim((string) ($config['message'] ?? $defaults['message'])) ?: $defaults['message'],
        'updated_at' => isset($config['updated_at']) && $config['updated_at'] !== '' ? (string) $config['updated_at'] : null,
        'updated_by' => isset($config['updated_by']) ? (int) $config['updated_by'] : null,
        'updated_by_name' => trim((string) ($config['updated_by_name'] ?? '')),
    ];
}

function maintenance_get_config(): array
{
    return maintenance_normalize_config(data_store_read('maintenance.json', maintenance_default_config()));
}

function maintenance_set_config(array $config, ?array $actor = null): array
{
    $normalized = maintenance_normalize_config($config);
    $normalized['updated_at'] = date('Y-m-d H:i:s');
    $normalized['updated_by'] = isset($actor['id']) ? (int) $actor['id'] : null;
    $normalized['updated_by_name'] = trim((string) ($actor['name'] ?? ''));

    data_store_write('maintenance.json', $normalized);

    return $normalized;
}

function maintenance_is_active(): bool
{
    $config = maintenance_get_config();
    return !empty($config['active']);
}

function maintenance_allows_user(?array $user): bool
{
    return (($user['role'] ?? '') === 'superadmin');
}

function maintenance_guard(?array $user = null): void
{
    $config = maintenance_get_config();
    if (empty($config['active']) || maintenance_allows_user($user)) {
        return;
    }

    $actions = [];
    if ($user) {
        $actions[] = [
            'label' => 'Keluar',
            'url' => base_url('logout.php'),
            'icon' => 'logout',
            'primary' => true,
        ];
    } else {
        $actions[] = [
            'label' => 'Kembali ke Login',
            'url' => base_url('login.php'),
            'icon' => 'login',
            'primary' => true,
        ];
    }

    render_system_state_page(
        (string) ($config['title'] ?? maintenance_default_config()['title']),
        (string) ($config['message'] ?? maintenance_default_config()['message']),
        [
            'title' => 'Maintenance KASPINDO',
            'status_code' => 503,
            'icon' => 'construction',
            'actions' => $actions,
        ]
    );
}
