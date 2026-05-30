<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function notification_ensure_schema(?PDO $pdo = null): void
{
    $pdo = $pdo instanceof PDO ? $pdo : db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notification_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telegram_enabled TINYINT(1) NOT NULL DEFAULT 0,
            telegram_token VARCHAR(255) DEFAULT '',
            telegram_chat_id VARCHAR(120) DEFAULT '',
            telegram_notify_transaction TINYINT(1) NOT NULL DEFAULT 1,
            telegram_notify_refund TINYINT(1) NOT NULL DEFAULT 1,
            telegram_notify_shift TINYINT(1) NOT NULL DEFAULT 1,
            telegram_notify_low_stock TINYINT(1) NOT NULL DEFAULT 1,
            telegram_notify_audit TINYINT(1) NOT NULL DEFAULT 0,
            telegram_notify_tenant TINYINT(1) NOT NULL DEFAULT 1,
            telegram_notify_maintenance TINYINT(1) NOT NULL DEFAULT 1,
            telegram_notify_backup TINYINT(1) NOT NULL DEFAULT 1,
            telegram_notify_health TINYINT(1) NOT NULL DEFAULT 1,
            telegram_daily_recap_enabled TINYINT(1) NOT NULL DEFAULT 0,
            telegram_daily_recap_time VARCHAR(5) DEFAULT '21:00',
            telegram_daily_recap_last_sent DATE DEFAULT NULL,
            notification_scope VARCHAR(20) NOT NULL DEFAULT 'store',
            store_id INT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    foreach ([
        'telegram_notify_audit' => "ALTER TABLE notification_settings ADD COLUMN telegram_notify_audit TINYINT(1) NOT NULL DEFAULT 0",
        'telegram_notify_tenant' => "ALTER TABLE notification_settings ADD COLUMN telegram_notify_tenant TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_notify_maintenance' => "ALTER TABLE notification_settings ADD COLUMN telegram_notify_maintenance TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_notify_backup' => "ALTER TABLE notification_settings ADD COLUMN telegram_notify_backup TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_notify_health' => "ALTER TABLE notification_settings ADD COLUMN telegram_notify_health TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_daily_recap_last_sent' => "ALTER TABLE notification_settings ADD COLUMN telegram_daily_recap_last_sent DATE DEFAULT NULL",
        'notification_scope' => "ALTER TABLE notification_settings ADD COLUMN notification_scope VARCHAR(20) NOT NULL DEFAULT 'store'",
        'store_id' => "ALTER TABLE notification_settings ADD COLUMN store_id INT DEFAULT NULL",
    ] as $column => $sql) {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'notification_settings'
                   AND column_name = :column"
            );
            $stmt->execute([':column' => $column]);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) {
            // Keep notification optional if ALTER privilege is unavailable.
        }
    }

    try {
        $pdo->exec("UPDATE notification_settings SET notification_scope = 'store' WHERE notification_scope IS NULL OR notification_scope = ''");
        $pdo->exec('CREATE INDEX idx_notification_scope_store ON notification_settings (notification_scope, store_id)');
    } catch (Throwable $e) {
        // Index may already exist on some MySQL variants.
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM notification_settings')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO notification_settings
             (telegram_enabled, telegram_token, telegram_chat_id, notification_scope, store_id)
             VALUES (:enabled, :token, :chat_id, :scope, :store_id)'
        );
        $stmt->execute([
            ':enabled' => getenv('TELEGRAM_BOT_TOKEN') && getenv('TELEGRAM_CHAT_ID') ? 1 : 0,
            ':token' => (string) (getenv('TELEGRAM_BOT_TOKEN') ?: ''),
            ':chat_id' => (string) (getenv('TELEGRAM_CHAT_ID') ?: ''),
            ':scope' => 'store',
            ':store_id' => null,
        ]);
    }
}

function notification_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function notification_get_config(?PDO $pdo = null, ?string $scope = null, ?int $storeId = null): array
{
    $pdo = $pdo instanceof PDO ? $pdo : db();
    notification_ensure_schema($pdo);

    [$scope, $storeId] = notification_resolve_scope($scope, $storeId);
    $row = notification_get_scope_row($pdo, $scope, $storeId);
    $token = trim((string) ($row['telegram_token'] ?? ''));
    $chatId = trim((string) ($row['telegram_chat_id'] ?? ''));

    if ($token === '') {
        $token = trim((string) (getenv('TELEGRAM_BOT_TOKEN') ?: ''));
    }
    if ($chatId === '') {
        $chatId = trim((string) (getenv('TELEGRAM_CHAT_ID') ?: ''));
    }

    return [
        'telegram_enabled' => notification_bool($row['telegram_enabled'] ?? false),
        'telegram_token' => $token,
        'telegram_chat_id' => $chatId,
        'telegram_notify_transaction' => notification_bool($row['telegram_notify_transaction'] ?? true),
        'telegram_notify_refund' => notification_bool($row['telegram_notify_refund'] ?? true),
        'telegram_notify_shift' => notification_bool($row['telegram_notify_shift'] ?? true),
        'telegram_notify_low_stock' => notification_bool($row['telegram_notify_low_stock'] ?? true),
        'telegram_notify_audit' => notification_bool($row['telegram_notify_audit'] ?? false),
        'telegram_notify_tenant' => notification_bool($row['telegram_notify_tenant'] ?? true),
        'telegram_notify_maintenance' => notification_bool($row['telegram_notify_maintenance'] ?? true),
        'telegram_notify_backup' => notification_bool($row['telegram_notify_backup'] ?? true),
        'telegram_notify_health' => notification_bool($row['telegram_notify_health'] ?? true),
        'telegram_daily_recap_enabled' => notification_bool($row['telegram_daily_recap_enabled'] ?? false),
        'telegram_daily_recap_time' => (string) ($row['telegram_daily_recap_time'] ?? '21:00'),
        'telegram_daily_recap_last_sent' => (string) ($row['telegram_daily_recap_last_sent'] ?? ''),
        'notification_scope' => $scope,
        'store_id' => $storeId,
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function notification_save_config(array $input, ?PDO $pdo = null, ?string $scope = null, ?int $storeId = null): array
{
    $pdo = $pdo instanceof PDO ? $pdo : db();
    notification_ensure_schema($pdo);
    [$scope, $storeId] = notification_resolve_scope($scope, $storeId);
    $row = notification_get_scope_row($pdo, $scope, $storeId);

    $time = trim((string) ($input['telegram_daily_recap_time'] ?? '21:00'));
    if (!preg_match('/^(2[0-3]|[01]\d):[0-5]\d$/', $time)) {
        $time = '21:00';
    }

    $stmt = $pdo->prepare(
        'UPDATE notification_settings
         SET telegram_enabled = :telegram_enabled,
             telegram_token = :telegram_token,
             telegram_chat_id = :telegram_chat_id,
             telegram_notify_transaction = :telegram_notify_transaction,
             telegram_notify_refund = :telegram_notify_refund,
             telegram_notify_shift = :telegram_notify_shift,
             telegram_notify_low_stock = :telegram_notify_low_stock,
             telegram_notify_audit = :telegram_notify_audit,
             telegram_notify_tenant = :telegram_notify_tenant,
             telegram_notify_maintenance = :telegram_notify_maintenance,
             telegram_notify_backup = :telegram_notify_backup,
             telegram_notify_health = :telegram_notify_health,
             telegram_daily_recap_enabled = :telegram_daily_recap_enabled,
             telegram_daily_recap_time = :telegram_daily_recap_time
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => (int) ($row['id'] ?? 0),
        ':telegram_enabled' => !empty($input['telegram_enabled']) ? 1 : 0,
        ':telegram_token' => trim((string) ($input['telegram_token'] ?? '')),
        ':telegram_chat_id' => trim((string) ($input['telegram_chat_id'] ?? '')),
        ':telegram_notify_transaction' => !empty($input['telegram_notify_transaction']) ? 1 : 0,
        ':telegram_notify_refund' => !empty($input['telegram_notify_refund']) ? 1 : 0,
        ':telegram_notify_shift' => !empty($input['telegram_notify_shift']) ? 1 : 0,
        ':telegram_notify_low_stock' => !empty($input['telegram_notify_low_stock']) ? 1 : 0,
        ':telegram_notify_audit' => !empty($input['telegram_notify_audit']) ? 1 : 0,
        ':telegram_notify_tenant' => !empty($input['telegram_notify_tenant']) ? 1 : 0,
        ':telegram_notify_maintenance' => !empty($input['telegram_notify_maintenance']) ? 1 : 0,
        ':telegram_notify_backup' => !empty($input['telegram_notify_backup']) ? 1 : 0,
        ':telegram_notify_health' => !empty($input['telegram_notify_health']) ? 1 : 0,
        ':telegram_daily_recap_enabled' => !empty($input['telegram_daily_recap_enabled']) ? 1 : 0,
        ':telegram_daily_recap_time' => $time,
    ]);

    return notification_get_config($pdo, $scope, $storeId);
}

function notification_get_scope_row(PDO $pdo, string $scope, ?int $storeId): array
{
    $scope = $scope === 'system' ? 'system' : 'store';
    $storeId = $scope === 'store' && $storeId !== null && $storeId > 0 ? $storeId : null;

    if ($scope === 'store' && $storeId !== null) {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM notification_settings
             WHERE notification_scope = 'store'
               AND store_id = :store_id
             ORDER BY id ASC
             LIMIT 1"
        );
        $stmt->execute([':store_id' => $storeId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM notification_settings
         WHERE notification_scope = :scope
           AND ' . ($scope === 'system' ? 'store_id IS NULL' : 'store_id IS NULL') . '
         ORDER BY id ASC
         LIMIT 1'
    );
    $stmt->execute([':scope' => $scope]);
    $row = $stmt->fetch();
    if ($row) {
        if ($scope === 'store' && $storeId !== null) {
            return notification_clone_config_for_store($pdo, $row, $storeId);
        }

        return $row;
    }

    return notification_create_scope_row($pdo, $scope, $storeId);
}

function notification_create_scope_row(PDO $pdo, string $scope, ?int $storeId): array
{
    $scope = $scope === 'system' ? 'system' : 'store';
    $stmt = $pdo->prepare(
        'INSERT INTO notification_settings
         (telegram_enabled, telegram_token, telegram_chat_id,
          telegram_notify_transaction, telegram_notify_refund, telegram_notify_shift, telegram_notify_low_stock,
          telegram_notify_audit, telegram_notify_tenant, telegram_notify_maintenance, telegram_notify_backup, telegram_notify_health,
          telegram_daily_recap_enabled, telegram_daily_recap_time, notification_scope, store_id)
         VALUES
         (:enabled, :token, :chat_id,
          :transaction, :refund, :shift, :low_stock,
          :audit, :tenant, :maintenance, :backup, :health,
          :daily_enabled, :daily_time, :scope, :store_id)'
    );
    $isSystem = $scope === 'system';
    $stmt->execute([
        ':enabled' => getenv('TELEGRAM_BOT_TOKEN') && getenv('TELEGRAM_CHAT_ID') ? 1 : 0,
        ':token' => (string) (getenv('TELEGRAM_BOT_TOKEN') ?: ''),
        ':chat_id' => (string) (getenv('TELEGRAM_CHAT_ID') ?: ''),
        ':transaction' => $isSystem ? 0 : 1,
        ':refund' => $isSystem ? 0 : 1,
        ':shift' => $isSystem ? 0 : 1,
        ':low_stock' => $isSystem ? 0 : 1,
        ':audit' => $isSystem ? 1 : 0,
        ':tenant' => $isSystem ? 1 : 0,
        ':maintenance' => $isSystem ? 1 : 0,
        ':backup' => $isSystem ? 1 : 0,
        ':health' => $isSystem ? 1 : 0,
        ':daily_enabled' => 0,
        ':daily_time' => '21:00',
        ':scope' => $scope,
        ':store_id' => $scope === 'store' ? $storeId : null,
    ]);

    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM notification_settings WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: [];
}

function notification_clone_config_for_store(PDO $pdo, array $source, int $storeId): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO notification_settings
         (telegram_enabled, telegram_token, telegram_chat_id,
          telegram_notify_transaction, telegram_notify_refund, telegram_notify_shift, telegram_notify_low_stock,
          telegram_notify_audit, telegram_daily_recap_enabled, telegram_daily_recap_time,
          notification_scope, store_id)
         VALUES
         (:enabled, :token, :chat_id,
          :transaction, :refund, :shift, :low_stock,
          :audit, :daily_enabled, :daily_time,
          "store", :store_id)'
    );
    $stmt->execute([
        ':enabled' => (int) ($source['telegram_enabled'] ?? 0),
        ':token' => (string) ($source['telegram_token'] ?? ''),
        ':chat_id' => (string) ($source['telegram_chat_id'] ?? ''),
        ':transaction' => (int) ($source['telegram_notify_transaction'] ?? 1),
        ':refund' => (int) ($source['telegram_notify_refund'] ?? 1),
        ':shift' => (int) ($source['telegram_notify_shift'] ?? 1),
        ':low_stock' => (int) ($source['telegram_notify_low_stock'] ?? 1),
        ':audit' => 0,
        ':daily_enabled' => (int) ($source['telegram_daily_recap_enabled'] ?? 0),
        ':daily_time' => (string) ($source['telegram_daily_recap_time'] ?? '21:00'),
        ':store_id' => $storeId,
    ]);

    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM notification_settings WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: $source;
}

function notification_resolve_scope(?string $scope = null, ?int $storeId = null): array
{
    if ($scope !== null) {
        return [$scope === 'system' ? 'system' : 'store', $storeId !== null && $storeId > 0 ? $storeId : null];
    }

    $user = function_exists('current_user') ? current_user() : null;
    if (is_array($user) && (string) ($user['role'] ?? '') === 'superadmin') {
        return ['system', null];
    }

    $resolvedStoreId = $storeId;
    if ($resolvedStoreId === null && is_array($user)) {
        $resolvedStoreId = (int) ($user['store_id'] ?? 0);
    }

    return ['store', $resolvedStoreId !== null && $resolvedStoreId > 0 ? $resolvedStoreId : null];
}

function notification_event_enabled(string $event, ?array $config = null): bool
{
    $config = $config ?? notification_get_config();
    if (empty($config['telegram_enabled'])) {
        return false;
    }
    if (trim((string) ($config['telegram_token'] ?? '')) === '' || trim((string) ($config['telegram_chat_id'] ?? '')) === '') {
        return false;
    }

    $map = [
        'transaction' => 'telegram_notify_transaction',
        'refund' => 'telegram_notify_refund',
        'shift' => 'telegram_notify_shift',
        'low_stock' => 'telegram_notify_low_stock',
        'audit' => 'telegram_notify_audit',
        'tenant' => 'telegram_notify_tenant',
        'maintenance' => 'telegram_notify_maintenance',
        'backup' => 'telegram_notify_backup',
        'health' => 'telegram_notify_health',
        'daily_recap' => 'telegram_daily_recap_enabled',
    ];
    $key = $map[$event] ?? '';
    return $key !== '' && !empty($config[$key]);
}
