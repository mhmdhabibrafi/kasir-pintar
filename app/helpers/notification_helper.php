<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function notification_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column
         LIMIT 1"
    );
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (bool) $stmt->fetchColumn();
}

function ensure_notification_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $pdo = db();
    } catch (Throwable $e) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'notification_settings'
             LIMIT 1"
        );
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return;
    }

    if (!$exists) {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS notification_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  telegram_enabled TINYINT(1) NOT NULL DEFAULT 0,
  telegram_token VARCHAR(255) DEFAULT NULL,
  telegram_chat_id VARCHAR(100) DEFAULT NULL,
  telegram_include_logo TINYINT(1) NOT NULL DEFAULT 1,
  telegram_include_items TINYINT(1) NOT NULL DEFAULT 1,
  telegram_format ENUM('detail','summary') NOT NULL DEFAULT 'detail',
  telegram_notify_transaction TINYINT(1) NOT NULL DEFAULT 1,
  telegram_notify_refund TINYINT(1) NOT NULL DEFAULT 1,
  telegram_notify_shift TINYINT(1) NOT NULL DEFAULT 1,
  telegram_notify_low_stock TINYINT(1) NOT NULL DEFAULT 1,
  telegram_daily_recap_enabled TINYINT(1) NOT NULL DEFAULT 0,
  telegram_daily_recap_time CHAR(5) NOT NULL DEFAULT '21:00',
  telegram_daily_recap_last_sent DATE DEFAULT NULL,
  whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  whatsapp_api_url VARCHAR(255) DEFAULT NULL,
  whatsapp_api_token VARCHAR(255) DEFAULT NULL,
  whatsapp_session VARCHAR(100) DEFAULT NULL,
  whatsapp_target_id VARCHAR(100) DEFAULT NULL,
  whatsapp_target_name VARCHAR(100) DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            return;
        }
    }

    $columnAdditions = [
        'telegram_include_logo' => "ALTER TABLE notification_settings ADD COLUMN telegram_include_logo TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_include_items' => "ALTER TABLE notification_settings ADD COLUMN telegram_include_items TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_format' => "ALTER TABLE notification_settings ADD COLUMN telegram_format ENUM('detail','summary') NOT NULL DEFAULT 'detail'",
        'telegram_notify_transaction' => "ALTER TABLE notification_settings ADD COLUMN telegram_notify_transaction TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_notify_refund' => "ALTER TABLE notification_settings ADD COLUMN telegram_notify_refund TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_notify_shift' => "ALTER TABLE notification_settings ADD COLUMN telegram_notify_shift TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_notify_low_stock' => "ALTER TABLE notification_settings ADD COLUMN telegram_notify_low_stock TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_daily_recap_enabled' => "ALTER TABLE notification_settings ADD COLUMN telegram_daily_recap_enabled TINYINT(1) NOT NULL DEFAULT 0",
        'telegram_daily_recap_time' => "ALTER TABLE notification_settings ADD COLUMN telegram_daily_recap_time CHAR(5) NOT NULL DEFAULT '21:00'",
        'telegram_daily_recap_last_sent' => "ALTER TABLE notification_settings ADD COLUMN telegram_daily_recap_last_sent DATE DEFAULT NULL",
    ];

    foreach ($columnAdditions as $column => $sql) {
        if (notification_column_exists($pdo, 'notification_settings', $column)) {
            continue;
        }
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // ignore to keep backward compatibility
        }
    }
}

function notification_default_config(): array
{
    return [
        'telegram_enabled' => false,
        'telegram_token' => '',
        'telegram_chat_id' => '',
        'telegram_include_logo' => true,
        'telegram_include_items' => true,
        'telegram_format' => 'detail',
        'telegram_notify_transaction' => true,
        'telegram_notify_refund' => true,
        'telegram_notify_shift' => true,
        'telegram_notify_low_stock' => true,
        'telegram_daily_recap_enabled' => false,
        'telegram_daily_recap_time' => '21:00',
        'telegram_daily_recap_last_sent' => null,
        'whatsapp_enabled' => false,
        'whatsapp_api_url' => '',
        'whatsapp_api_token' => '',
        'whatsapp_session' => '',
        'whatsapp_target_id' => '',
        'whatsapp_target_name' => '',
        'updated_at' => null,
    ];
}

function notification_get_config(): array
{
    ensure_notification_schema();
    $pdo = db();
    $defaults = notification_default_config();

    $stmt = $pdo->query(
        'SELECT telegram_enabled, telegram_token, telegram_chat_id,
                telegram_include_logo, telegram_include_items, telegram_format,
                telegram_notify_transaction, telegram_notify_refund, telegram_notify_shift, telegram_notify_low_stock,
                telegram_daily_recap_enabled, telegram_daily_recap_time, telegram_daily_recap_last_sent,
                whatsapp_enabled, whatsapp_api_url, whatsapp_api_token, whatsapp_session, whatsapp_target_id, whatsapp_target_name,
                updated_at
         FROM notification_settings
         ORDER BY id ASC
         LIMIT 1'
    );
    $row = $stmt->fetch();

    if (!$row) {
        $insert = $pdo->prepare(
            'INSERT INTO notification_settings
                (telegram_enabled, telegram_token, telegram_chat_id,
                 telegram_include_logo, telegram_include_items, telegram_format,
                 telegram_notify_transaction, telegram_notify_refund, telegram_notify_shift, telegram_notify_low_stock,
                 telegram_daily_recap_enabled, telegram_daily_recap_time, telegram_daily_recap_last_sent,
                 whatsapp_enabled, whatsapp_api_url, whatsapp_api_token, whatsapp_session, whatsapp_target_id, whatsapp_target_name)
             VALUES
                (:telegram_enabled, :telegram_token, :telegram_chat_id,
                 :telegram_include_logo, :telegram_include_items, :telegram_format,
                 :telegram_notify_transaction, :telegram_notify_refund, :telegram_notify_shift, :telegram_notify_low_stock,
                 :telegram_daily_recap_enabled, :telegram_daily_recap_time, :telegram_daily_recap_last_sent,
                 :whatsapp_enabled, :whatsapp_api_url, :whatsapp_api_token, :whatsapp_session, :whatsapp_target_id, :whatsapp_target_name)'
        );
        $insert->execute([
            ':telegram_enabled' => $defaults['telegram_enabled'] ? 1 : 0,
            ':telegram_token' => $defaults['telegram_token'],
            ':telegram_chat_id' => $defaults['telegram_chat_id'],
            ':telegram_include_logo' => $defaults['telegram_include_logo'] ? 1 : 0,
            ':telegram_include_items' => $defaults['telegram_include_items'] ? 1 : 0,
            ':telegram_format' => $defaults['telegram_format'],
            ':telegram_notify_transaction' => $defaults['telegram_notify_transaction'] ? 1 : 0,
            ':telegram_notify_refund' => $defaults['telegram_notify_refund'] ? 1 : 0,
            ':telegram_notify_shift' => $defaults['telegram_notify_shift'] ? 1 : 0,
            ':telegram_notify_low_stock' => $defaults['telegram_notify_low_stock'] ? 1 : 0,
            ':telegram_daily_recap_enabled' => $defaults['telegram_daily_recap_enabled'] ? 1 : 0,
            ':telegram_daily_recap_time' => $defaults['telegram_daily_recap_time'],
            ':telegram_daily_recap_last_sent' => $defaults['telegram_daily_recap_last_sent'],
            ':whatsapp_enabled' => $defaults['whatsapp_enabled'] ? 1 : 0,
            ':whatsapp_api_url' => $defaults['whatsapp_api_url'],
            ':whatsapp_api_token' => $defaults['whatsapp_api_token'],
            ':whatsapp_session' => $defaults['whatsapp_session'],
            ':whatsapp_target_id' => $defaults['whatsapp_target_id'],
            ':whatsapp_target_name' => $defaults['whatsapp_target_name'],
        ]);
        return $defaults;
    }

    return [
        'telegram_enabled' => !empty($row['telegram_enabled']),
        'telegram_token' => (string) ($row['telegram_token'] ?? ''),
        'telegram_chat_id' => (string) ($row['telegram_chat_id'] ?? ''),
        'telegram_include_logo' => !empty($row['telegram_include_logo']),
        'telegram_include_items' => !empty($row['telegram_include_items']),
        'telegram_format' => (string) ($row['telegram_format'] ?? 'detail'),
        'telegram_notify_transaction' => !empty($row['telegram_notify_transaction']),
        'telegram_notify_refund' => !empty($row['telegram_notify_refund']),
        'telegram_notify_shift' => !empty($row['telegram_notify_shift']),
        'telegram_notify_low_stock' => !empty($row['telegram_notify_low_stock']),
        'telegram_daily_recap_enabled' => !empty($row['telegram_daily_recap_enabled']),
        'telegram_daily_recap_time' => (string) ($row['telegram_daily_recap_time'] ?? '21:00'),
        'telegram_daily_recap_last_sent' => $row['telegram_daily_recap_last_sent'] ?? null,
        'whatsapp_enabled' => !empty($row['whatsapp_enabled']),
        'whatsapp_api_url' => (string) ($row['whatsapp_api_url'] ?? ''),
        'whatsapp_api_token' => (string) ($row['whatsapp_api_token'] ?? ''),
        'whatsapp_session' => (string) ($row['whatsapp_session'] ?? ''),
        'whatsapp_target_id' => (string) ($row['whatsapp_target_id'] ?? ''),
        'whatsapp_target_name' => (string) ($row['whatsapp_target_name'] ?? ''),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function notification_save_config(array $config): void
{
    ensure_notification_schema();
    $pdo = db();
    $config = array_merge(notification_default_config(), $config);

    $existing = $pdo->query('SELECT id FROM notification_settings ORDER BY id ASC LIMIT 1')->fetch();
    if ($existing) {
        $update = $pdo->prepare(
            'UPDATE notification_settings
             SET telegram_enabled = :telegram_enabled,
                 telegram_token = :telegram_token,
                 telegram_chat_id = :telegram_chat_id,
                 telegram_include_logo = :telegram_include_logo,
                 telegram_include_items = :telegram_include_items,
                 telegram_format = :telegram_format,
                 telegram_notify_transaction = :telegram_notify_transaction,
                 telegram_notify_refund = :telegram_notify_refund,
                 telegram_notify_shift = :telegram_notify_shift,
                 telegram_notify_low_stock = :telegram_notify_low_stock,
                 telegram_daily_recap_enabled = :telegram_daily_recap_enabled,
                 telegram_daily_recap_time = :telegram_daily_recap_time,
                 telegram_daily_recap_last_sent = :telegram_daily_recap_last_sent,
                 whatsapp_enabled = :whatsapp_enabled,
                 whatsapp_api_url = :whatsapp_api_url,
                 whatsapp_api_token = :whatsapp_api_token,
                 whatsapp_session = :whatsapp_session,
                 whatsapp_target_id = :whatsapp_target_id,
                 whatsapp_target_name = :whatsapp_target_name
             WHERE id = :id'
        );
        $update->execute([
            ':telegram_enabled' => $config['telegram_enabled'] ? 1 : 0,
            ':telegram_token' => $config['telegram_token'],
            ':telegram_chat_id' => $config['telegram_chat_id'],
            ':telegram_include_logo' => $config['telegram_include_logo'] ? 1 : 0,
            ':telegram_include_items' => $config['telegram_include_items'] ? 1 : 0,
            ':telegram_format' => $config['telegram_format'],
            ':telegram_notify_transaction' => $config['telegram_notify_transaction'] ? 1 : 0,
            ':telegram_notify_refund' => $config['telegram_notify_refund'] ? 1 : 0,
            ':telegram_notify_shift' => $config['telegram_notify_shift'] ? 1 : 0,
            ':telegram_notify_low_stock' => $config['telegram_notify_low_stock'] ? 1 : 0,
            ':telegram_daily_recap_enabled' => $config['telegram_daily_recap_enabled'] ? 1 : 0,
            ':telegram_daily_recap_time' => $config['telegram_daily_recap_time'],
            ':telegram_daily_recap_last_sent' => $config['telegram_daily_recap_last_sent'],
            ':whatsapp_enabled' => $config['whatsapp_enabled'] ? 1 : 0,
            ':whatsapp_api_url' => $config['whatsapp_api_url'],
            ':whatsapp_api_token' => $config['whatsapp_api_token'],
            ':whatsapp_session' => $config['whatsapp_session'],
            ':whatsapp_target_id' => $config['whatsapp_target_id'],
            ':whatsapp_target_name' => $config['whatsapp_target_name'],
            ':id' => (int) $existing['id'],
        ]);
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO notification_settings
            (telegram_enabled, telegram_token, telegram_chat_id,
             telegram_include_logo, telegram_include_items, telegram_format,
             telegram_notify_transaction, telegram_notify_refund, telegram_notify_shift, telegram_notify_low_stock,
             telegram_daily_recap_enabled, telegram_daily_recap_time, telegram_daily_recap_last_sent,
             whatsapp_enabled, whatsapp_api_url, whatsapp_api_token, whatsapp_session, whatsapp_target_id, whatsapp_target_name)
         VALUES
            (:telegram_enabled, :telegram_token, :telegram_chat_id,
             :telegram_include_logo, :telegram_include_items, :telegram_format,
             :telegram_notify_transaction, :telegram_notify_refund, :telegram_notify_shift, :telegram_notify_low_stock,
             :telegram_daily_recap_enabled, :telegram_daily_recap_time, :telegram_daily_recap_last_sent,
             :whatsapp_enabled, :whatsapp_api_url, :whatsapp_api_token, :whatsapp_session, :whatsapp_target_id, :whatsapp_target_name)'
    );
    $insert->execute([
        ':telegram_enabled' => $config['telegram_enabled'] ? 1 : 0,
        ':telegram_token' => $config['telegram_token'],
        ':telegram_chat_id' => $config['telegram_chat_id'],
        ':telegram_include_logo' => $config['telegram_include_logo'] ? 1 : 0,
        ':telegram_include_items' => $config['telegram_include_items'] ? 1 : 0,
        ':telegram_format' => $config['telegram_format'],
        ':telegram_notify_transaction' => $config['telegram_notify_transaction'] ? 1 : 0,
        ':telegram_notify_refund' => $config['telegram_notify_refund'] ? 1 : 0,
        ':telegram_notify_shift' => $config['telegram_notify_shift'] ? 1 : 0,
        ':telegram_notify_low_stock' => $config['telegram_notify_low_stock'] ? 1 : 0,
        ':telegram_daily_recap_enabled' => $config['telegram_daily_recap_enabled'] ? 1 : 0,
        ':telegram_daily_recap_time' => $config['telegram_daily_recap_time'],
        ':telegram_daily_recap_last_sent' => $config['telegram_daily_recap_last_sent'],
        ':whatsapp_enabled' => $config['whatsapp_enabled'] ? 1 : 0,
        ':whatsapp_api_url' => $config['whatsapp_api_url'],
        ':whatsapp_api_token' => $config['whatsapp_api_token'],
        ':whatsapp_session' => $config['whatsapp_session'],
        ':whatsapp_target_id' => $config['whatsapp_target_id'],
        ':whatsapp_target_name' => $config['whatsapp_target_name'],
    ]);
}
