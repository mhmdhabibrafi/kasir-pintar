<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_migration_helper.php';
require_once __DIR__ . '/inventory_helper.php';
require_once __DIR__ . '/shift_helper.php';
require_once __DIR__ . '/store_info_helper.php';

$notificationHelperPath = __DIR__ . '/notification_helper.php';
if (is_file($notificationHelperPath)) {
    require_once $notificationHelperPath;
}

$supportChatHelperPath = __DIR__ . '/support_chat_helper.php';
if (is_file($supportChatHelperPath)) {
    require_once $supportChatHelperPath;
}

function store_operations_notification_connected(array $config): bool
{
    return !empty($config['telegram_enabled'])
        && trim((string) ($config['telegram_token'] ?? '')) !== ''
        && trim((string) ($config['telegram_chat_id'] ?? '')) !== '';
}

function store_operations_support_snapshot(PDO $pdo, array $user): array
{
    $snapshot = [
        'available' => false,
        'disabled' => false,
        'thread_id' => 0,
        'chat_mode' => 'bot',
        'chat_mode_label' => 'Bot ON',
        'status' => 'closed',
        'status_label' => 'Closed',
        'priority' => 'normal',
        'priority_label' => 'Normal',
        'unread_count' => 0,
        'message_count' => 0,
        'last_message_at' => null,
        'last_message' => '',
    ];

    if (function_exists('app_env_bool') && !app_env_bool('APP_SUPPORT_ENABLED', false)) {
        $snapshot['disabled'] = true;
        $snapshot['chat_mode_label'] = 'Nonaktif';
        $snapshot['status_label'] = 'Nonaktif';
        return $snapshot;
    }

    $storeId = (int) ($user['store_id'] ?? 0);
    if (
        $storeId <= 0
        || !function_exists('support_chat_ensure_schema')
        || !function_exists('support_chat_find_thread_by_store')
        || !function_exists('support_chat_can_access')
    ) {
        return $snapshot;
    }

    support_chat_ensure_schema($pdo);
    $thread = support_chat_find_thread_by_store($pdo, $storeId);
    if (!$thread) {
        return $snapshot;
    }

    $snapshot['available'] = true;
    if (support_chat_can_access($user)) {
        $thread = support_chat_enrich_thread($pdo, $thread, $user);
        return [
            'available' => true,
            'thread_id' => (int) ($thread['id'] ?? 0),
            'chat_mode' => (string) ($thread['chat_mode'] ?? 'bot'),
            'chat_mode_label' => (string) ($thread['chat_mode_label'] ?? 'Bot ON'),
            'status' => (string) ($thread['status'] ?? 'closed'),
            'status_label' => (string) ($thread['status_label'] ?? 'Closed'),
            'priority' => (string) ($thread['priority'] ?? 'normal'),
            'priority_label' => (string) ($thread['priority_label'] ?? 'Normal'),
            'unread_count' => (int) ($thread['unread_count'] ?? 0),
            'message_count' => (int) ($thread['message_count'] ?? 0),
            'last_message_at' => $thread['last_message_at'] ?? null,
            'last_message' => (string) ($thread['last_message'] ?? ''),
        ];
    }

    $mode = support_chat_thread_mode($thread);
    return [
        'available' => true,
        'thread_id' => (int) ($thread['id'] ?? 0),
        'chat_mode' => $mode,
        'chat_mode_label' => $mode === 'live' ? 'Live Chat ON' : 'Bot ON',
        'status' => (string) ($thread['status'] ?? 'closed'),
        'status_label' => (string) ($thread['status'] ?? 'closed') === 'open' ? 'Open' : 'Closed',
        'priority' => (string) ($thread['priority'] ?? 'normal'),
        'priority_label' => ucfirst((string) ($thread['priority'] ?? 'normal')),
        'unread_count' => 0,
        'message_count' => support_chat_message_count($pdo, (int) ($thread['id'] ?? 0)),
        'last_message_at' => $thread['last_message_at'] ?? null,
        'last_message' => (string) ($thread['last_message_preview'] ?? ''),
    ];
}

function store_operations_snapshot(PDO $pdo, array $user, array $products = []): array
{
    ensure_update_schema();

    $storeInfo = store_info_get();
    $profileCompletion = store_info_completion($storeInfo);
    $looksLikeDemoProfile = store_info_is_demo_profile($storeInfo);
    $notificationConfig = function_exists('notification_get_config') ? notification_get_config() : [];
    $notificationConnected = store_operations_notification_connected($notificationConfig);
    $lowStockItems = inventory_low_stock($products, $pdo);
    $activeShiftCount = function_exists('shift_active_count') ? shift_active_count() : 0;
    $supportSnapshot = store_operations_support_snapshot($pdo, $user);

    return [
        'store_name' => (string) ($storeInfo['store_name'] ?? 'KASPINDO'),
        'store_code' => (string) ($storeInfo['store_code'] ?? ''),
        'business_hours' => trim((string) ($storeInfo['business_hours'] ?? '')),
        'profile_completion' => $profileCompletion,
        'profile_is_demo' => $looksLikeDemoProfile,
        'profile_needs_attention' => $looksLikeDemoProfile || (int) ($profileCompletion['percent'] ?? 0) < 75,
        'low_stock_items' => $lowStockItems,
        'low_stock_count' => count($lowStockItems),
        'notification' => [
            'connected' => $notificationConnected,
            'telegram_enabled' => !empty($notificationConfig['telegram_enabled']),
            'daily_recap_enabled' => !empty($notificationConfig['telegram_daily_recap_enabled']),
            'updated_at' => $notificationConfig['updated_at'] ?? null,
        ],
        'support' => $supportSnapshot,
        'active_shift_count' => $activeShiftCount,
    ];
}
