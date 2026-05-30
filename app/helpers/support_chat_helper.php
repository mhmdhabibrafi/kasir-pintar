<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/data_store.php';
require_once __DIR__ . '/store_info_helper.php';

function support_chat_enabled(): bool
{
    return function_exists('app_env_bool') ? app_env_bool('APP_SUPPORT_ENABLED', false) : false;
}

function support_chat_is_superadmin(array $user): bool
{
    return (string) ($user['role'] ?? '') === 'superadmin';
}

function support_chat_can_access(?array $user): bool
{
    if (!$user) {
        return false;
    }

    if (!support_chat_enabled()) {
        return false;
    }

    return in_array((string) ($user['role'] ?? ''), ['superadmin', 'admin', 'bos'], true);
}

function support_chat_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS support_threads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL UNIQUE,
            store_name VARCHAR(120) NOT NULL,
            store_code VARCHAR(40) DEFAULT '',
            status ENUM('open','closed') NOT NULL DEFAULT 'open',
            chat_mode ENUM('bot','live') NOT NULL DEFAULT 'bot',
            priority ENUM('normal','high','urgent') NOT NULL DEFAULT 'normal',
            assigned_to_user_id INT DEFAULT NULL,
            assigned_to_name VARCHAR(120) DEFAULT '',
            support_last_read_message_id INT NOT NULL DEFAULT 0,
            superadmin_last_read_message_id INT NOT NULL DEFAULT 0,
            last_message_at DATETIME DEFAULT NULL,
            last_message_preview VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_support_threads_status (status),
            INDEX idx_support_threads_priority (priority),
            INDEX idx_support_threads_last_message (last_message_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS support_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            thread_id INT NOT NULL,
            sender_user_id INT DEFAULT NULL,
            sender_name VARCHAR(120) NOT NULL,
            sender_role ENUM('admin','superadmin','bot') NOT NULL DEFAULT 'admin',
            message_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_support_messages_thread (thread_id),
            INDEX idx_support_messages_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function support_chat_current_store_meta(array $user): array
{
    $storeId = (int) ($user['store_id'] ?? 0);
    $storeInfo = store_info_get();

    return [
        'store_id' => $storeId > 0 ? $storeId : (int) ($user['id'] ?? 0),
        'store_name' => (string) ($storeInfo['store_name'] ?? 'KASPINDO'),
        'store_code' => (string) ($storeInfo['store_code'] ?? ''),
    ];
}

function support_chat_thread_mode(array $thread): string
{
    return (string) ($thread['chat_mode'] ?? 'bot') === 'live' ? 'live' : 'bot';
}

function support_chat_message_count(PDO $pdo, int $threadId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_messages WHERE thread_id = :thread_id');
    $stmt->execute([':thread_id' => $threadId]);
    return (int) $stmt->fetchColumn();
}

function support_chat_find_thread_by_store(PDO $pdo, int $storeId): ?array
{
    support_chat_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM support_threads WHERE store_id = :store_id LIMIT 1');
    $stmt->execute([':store_id' => $storeId]);
    $thread = $stmt->fetch();
    return $thread ?: null;
}

function support_chat_create_thread(PDO $pdo, array $storeMeta): array
{
    support_chat_ensure_schema($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO support_threads (store_id, store_name, store_code, status, chat_mode, last_message_at)
         VALUES (:store_id, :store_name, :store_code, "open", "bot", NOW())
         ON DUPLICATE KEY UPDATE store_name = VALUES(store_name), store_code = VALUES(store_code)'
    );
    $stmt->execute([
        ':store_id' => (int) $storeMeta['store_id'],
        ':store_name' => (string) $storeMeta['store_name'],
        ':store_code' => (string) ($storeMeta['store_code'] ?? ''),
    ]);

    $thread = support_chat_find_thread_by_store($pdo, (int) $storeMeta['store_id']);
    if (!$thread) {
        throw new RuntimeException('Room support gagal dibuat.');
    }

    return $thread;
}

function support_chat_get_or_create_thread_for_user(PDO $pdo, array $user): array
{
    $storeMeta = support_chat_current_store_meta($user);
    $thread = support_chat_find_thread_by_store($pdo, (int) $storeMeta['store_id']);
    return $thread ?: support_chat_create_thread($pdo, $storeMeta);
}

function support_chat_get_thread_for_access(PDO $pdo, array $user, int $threadId): ?array
{
    support_chat_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM support_threads WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $threadId]);
    $thread = $stmt->fetch();
    if (!$thread) {
        return null;
    }

    if (support_chat_is_superadmin($user)) {
        return support_chat_enrich_thread($pdo, $thread, $user);
    }

    $storeMeta = support_chat_current_store_meta($user);
    if ((int) ($thread['store_id'] ?? 0) !== (int) $storeMeta['store_id']) {
        return null;
    }

    return support_chat_enrich_thread($pdo, $thread, $user);
}

function support_chat_list_threads(PDO $pdo, array $user, array $filters = []): array
{
    support_chat_ensure_schema($pdo);

    if (!support_chat_is_superadmin($user)) {
        $thread = support_chat_get_or_create_thread_for_user($pdo, $user);
        return [support_chat_enrich_thread($pdo, $thread, $user)];
    }

    $where = [];
    $params = [];

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(store_name LIKE :search OR store_code LIKE :search OR last_message_preview LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
    if (in_array($status, ['open', 'closed'], true)) {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }

    $priority = strtolower(trim((string) ($filters['priority'] ?? 'all')));
    if (in_array($priority, ['normal', 'high', 'urgent'], true)) {
        $where[] = 'priority = :priority';
        $params[':priority'] = $priority;
    }

    $assigned = strtolower(trim((string) ($filters['assigned'] ?? 'all')));
    if ($assigned === 'mine') {
        $where[] = 'assigned_to_user_id = :assigned_to_user_id';
        $params[':assigned_to_user_id'] = (int) ($user['id'] ?? 0);
    } elseif ($assigned === 'unassigned') {
        $where[] = 'assigned_to_user_id IS NULL';
    }

    $sql = 'SELECT * FROM support_threads';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY COALESCE(last_message_at, created_at) DESC, id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $threads = $stmt->fetchAll();
    $threads = array_map(
        static fn (array $thread): array => support_chat_enrich_thread($pdo, $thread, $user),
        $threads
    );

    if (!empty($filters['unread_only'])) {
        $threads = array_values(array_filter($threads, static fn (array $thread): bool => (int) ($thread['unread_count'] ?? 0) > 0));
    }

    return $threads;
}

function support_chat_enrich_thread(PDO $pdo, array $thread, array $viewer): array
{
    $unreadColumn = support_chat_is_superadmin($viewer) ? 'superadmin_last_read_message_id' : 'support_last_read_message_id';
    $senderRole = support_chat_is_superadmin($viewer) ? 'admin' : 'superadmin';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM support_messages
         WHERE thread_id = :thread_id
           AND id > :last_read
           AND sender_role = :sender_role'
    );
    $stmt->execute([
        ':thread_id' => (int) ($thread['id'] ?? 0),
        ':last_read' => (int) ($thread[$unreadColumn] ?? 0),
        ':sender_role' => $senderRole,
    ]);

    $thread['unread_count'] = (int) $stmt->fetchColumn();
    $thread['chat_mode_label'] = support_chat_thread_mode($thread) === 'live' ? 'Live Chat ON' : 'Bot ON';
    $thread['status_label'] = (string) ($thread['status'] ?? 'open') === 'closed' ? 'Closed' : 'Open';
    $thread['priority_label'] = ucfirst((string) ($thread['priority'] ?? 'normal'));
    $thread['last_message'] = (string) ($thread['last_message_preview'] ?? '');

    return $thread;
}

function support_chat_list_messages(PDO $pdo, array $user, int $threadId, int $afterId = 0, int $limit = 100): array
{
    $thread = support_chat_get_thread_for_access($pdo, $user, $threadId);
    if (!$thread) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare(
        'SELECT id, thread_id, sender_user_id, sender_name, sender_role, message_text, created_at
         FROM support_messages
         WHERE thread_id = :thread_id AND id > :after_id
         ORDER BY id ASC
         LIMIT ' . $limit
    );
    $stmt->execute([
        ':thread_id' => $threadId,
        ':after_id' => max(0, $afterId),
    ]);
    $messages = $stmt->fetchAll();

    support_chat_mark_read($pdo, $user, $threadId);
    return $messages;
}

function support_chat_mark_read(PDO $pdo, array $user, int $threadId): void
{
    $column = support_chat_is_superadmin($user) ? 'superadmin_last_read_message_id' : 'support_last_read_message_id';
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM support_messages WHERE thread_id = :thread_id');
    $stmt->execute([':thread_id' => $threadId]);
    $lastId = (int) $stmt->fetchColumn();

    $update = $pdo->prepare('UPDATE support_threads SET ' . $column . ' = GREATEST(' . $column . ', :last_id) WHERE id = :thread_id');
    $update->execute([':last_id' => $lastId, ':thread_id' => $threadId]);
}

function support_chat_add_message(PDO $pdo, int $threadId, ?int $userId, string $senderName, string $senderRole, string $message): int
{
    $message = trim($message);
    if ($message === '') {
        throw new RuntimeException('Pesan tidak boleh kosong.');
    }
    if (strlen($message) > 4000) {
        throw new RuntimeException('Pesan terlalu panjang.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO support_messages (thread_id, sender_user_id, sender_name, sender_role, message_text)
         VALUES (:thread_id, :sender_user_id, :sender_name, :sender_role, :message_text)'
    );
    $stmt->execute([
        ':thread_id' => $threadId,
        ':sender_user_id' => $userId,
        ':sender_name' => $senderName,
        ':sender_role' => $senderRole,
        ':message_text' => $message,
    ]);

    $messageId = (int) $pdo->lastInsertId();
    $preview = substr(preg_replace('/\s+/', ' ', $message) ?: $message, 0, 255);
    $update = $pdo->prepare(
        'UPDATE support_threads
         SET status = "open", last_message_at = NOW(), last_message_preview = :preview
         WHERE id = :thread_id'
    );
    $update->execute([':preview' => $preview, ':thread_id' => $threadId]);

    return $messageId;
}

function support_chat_send_user_message(PDO $pdo, array $user, int $threadId, string $message): void
{
    $thread = support_chat_get_thread_for_access($pdo, $user, $threadId);
    if (!$thread) {
        throw new RuntimeException('Room support tidak ditemukan.');
    }

    $senderRole = support_chat_is_superadmin($user) ? 'superadmin' : 'admin';
    if ($senderRole === 'superadmin' && support_chat_thread_mode($thread) === 'bot') {
        support_chat_set_thread_mode($pdo, $user, $threadId, 'live');
    }

    support_chat_add_message(
        $pdo,
        $threadId,
        (int) ($user['id'] ?? 0),
        (string) ($user['name'] ?? 'User'),
        $senderRole,
        $message
    );

}

function support_chat_set_thread_priority(PDO $pdo, array $user, int $threadId, string $priority): void
{
    if (!support_chat_is_superadmin($user)) {
        throw new RuntimeException('Hanya superadmin yang bisa mengubah prioritas.');
    }
    $priority = in_array($priority, ['normal', 'high', 'urgent'], true) ? $priority : 'normal';
    $stmt = $pdo->prepare('UPDATE support_threads SET priority = :priority WHERE id = :id');
    $stmt->execute([':priority' => $priority, ':id' => $threadId]);
}

function support_chat_set_thread_status(PDO $pdo, array $user, int $threadId, string $status): void
{
    if (!support_chat_is_superadmin($user)) {
        throw new RuntimeException('Hanya superadmin yang bisa mengubah status.');
    }
    $status = $status === 'closed' ? 'closed' : 'open';
    $stmt = $pdo->prepare('UPDATE support_threads SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $threadId]);
}

function support_chat_set_thread_mode(PDO $pdo, array $user, int $threadId, string $mode): void
{
    if (!support_chat_is_superadmin($user)) {
        throw new RuntimeException('Hanya superadmin yang bisa mengubah mode chat.');
    }
    $mode = $mode === 'live' ? 'live' : 'bot';
    $stmt = $pdo->prepare('UPDATE support_threads SET chat_mode = :mode WHERE id = :id');
    $stmt->execute([':mode' => $mode, ':id' => $threadId]);
}

function support_chat_assign_thread(PDO $pdo, array $user, int $threadId, ?int $assignedUserId): void
{
    if (!support_chat_is_superadmin($user)) {
        throw new RuntimeException('Hanya superadmin yang bisa mengambil room.');
    }

    $assignedName = $assignedUserId ? (string) ($user['name'] ?? 'Superadmin') : '';
    $stmt = $pdo->prepare(
        'UPDATE support_threads
         SET assigned_to_user_id = :assigned_to_user_id, assigned_to_name = :assigned_to_name
         WHERE id = :id'
    );
    $stmt->execute([
        ':assigned_to_user_id' => $assignedUserId,
        ':assigned_to_name' => $assignedName,
        ':id' => $threadId,
    ]);
}

function support_chat_thread_stats(array $threads): array
{
    $stats = ['all' => count($threads), 'open' => 0, 'closed' => 0, 'unread' => 0, 'urgent' => 0, 'assigned' => 0];
    foreach ($threads as $thread) {
        $status = (string) ($thread['status'] ?? 'open');
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
        if ((int) ($thread['unread_count'] ?? 0) > 0) {
            $stats['unread']++;
        }
        if ((string) ($thread['priority'] ?? '') === 'urgent') {
            $stats['urgent']++;
        }
        if ((int) ($thread['assigned_to_user_id'] ?? 0) > 0) {
            $stats['assigned']++;
        }
    }
    return $stats;
}
