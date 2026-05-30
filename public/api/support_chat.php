<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/api_helper.php';

$supportChatHelperPath = __DIR__ . '/../../app/helpers/support_chat_helper.php';
if (!is_file($supportChatHelperPath)) {
    api_error('Modul support chat belum tersedia pada instalasi ini.', 503, 'service_unavailable');
}

require_once $supportChatHelperPath;

$user = current_user();
if (!$user) {
    api_error('Anda harus login untuk mengakses support chat.', 401, 'unauthenticated');
}
if (!support_chat_enabled()) {
    api_error('Fitur support chat sedang dinonaktifkan.', 404, 'support_disabled');
}
if (!support_chat_can_access($user)) {
    api_error('Anda tidak memiliki akses ke support chat.', 403, 'forbidden');
}

$pdo = db();
support_chat_ensure_schema($pdo);

function support_chat_api_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function support_chat_api_filters(array $source): array
{
    return [
        'search' => trim((string) ($source['search'] ?? $source['q'] ?? '')),
        'status' => strtolower(trim((string) ($source['status'] ?? 'all'))),
        'priority' => strtolower(trim((string) ($source['priority'] ?? 'all'))),
        'assigned' => strtolower(trim((string) ($source['assigned'] ?? 'all'))),
        'unread_only' => support_chat_api_bool($source['unread_only'] ?? $source['unreadOnly'] ?? false),
    ];
}

function support_chat_api_user_meta(array $user): array
{
    return [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'store_id' => (int) ($user['store_id'] ?? 0),
    ];
}

function support_chat_api_thread_is_visible(array $threads, int $threadId): bool
{
    foreach ($threads as $thread) {
        if ((int) ($thread['id'] ?? 0) === $threadId) {
            return true;
        }
    }

    return false;
}

function support_chat_api_state(PDO $pdo, array $user, array $filters, int $threadId = 0): array
{
    $allThreads = support_chat_list_threads($pdo, $user);
    $threads = support_chat_is_superadmin($user)
        ? support_chat_list_threads($pdo, $user, $filters)
        : $allThreads;

    if ($threadId <= 0 && !empty($threads)) {
        $threadId = (int) ($threads[0]['id'] ?? 0);
    }

    $activeThread = null;
    $messages = [];

    if ($threadId > 0) {
        $candidate = support_chat_get_thread_for_access($pdo, $user, $threadId);
        if (
            $candidate
            && (!support_chat_is_superadmin($user) || support_chat_api_thread_is_visible($threads, $threadId))
        ) {
            $messages = support_chat_list_messages($pdo, $user, $threadId, 0, 100);
            $activeThread = support_chat_get_thread_for_access($pdo, $user, $threadId);
        }
    }

    $allThreads = support_chat_list_threads($pdo, $user);
    $threads = support_chat_is_superadmin($user)
        ? support_chat_list_threads($pdo, $user, $filters)
        : $allThreads;

    if (
        !$activeThread
        && !empty($threads)
        && ($threadId <= 0 || !support_chat_api_thread_is_visible($threads, $threadId))
    ) {
        $fallbackThreadId = (int) ($threads[0]['id'] ?? 0);
        if ($fallbackThreadId > 0) {
            $messages = support_chat_list_messages($pdo, $user, $fallbackThreadId, 0, 100);
            $activeThread = support_chat_get_thread_for_access($pdo, $user, $fallbackThreadId);
            $allThreads = support_chat_list_threads($pdo, $user);
            $threads = support_chat_is_superadmin($user)
                ? support_chat_list_threads($pdo, $user, $filters)
                : $allThreads;
        }
    }

    return [
        'threads' => $threads,
        'stats' => support_chat_thread_stats($allThreads),
        'active_thread' => $activeThread,
        'messages' => $messages,
        'filters' => $filters,
        'user' => support_chat_api_user_meta($user),
    ];
}

function support_chat_api_incremental_state(
    PDO $pdo,
    array $user,
    array $filters,
    int $threadId,
    int $afterId
): array {
    $thread = support_chat_get_thread_for_access($pdo, $user, $threadId);
    if (!$thread) {
        throw new RuntimeException('Thread support tidak ditemukan.');
    }

    $messages = support_chat_list_messages($pdo, $user, $threadId, $afterId, 100);
    $allThreads = support_chat_list_threads($pdo, $user);
    $threads = support_chat_is_superadmin($user)
        ? support_chat_list_threads($pdo, $user, $filters)
        : $allThreads;

    return [
        'threads' => $threads,
        'stats' => support_chat_thread_stats($allThreads),
        'active_thread' => support_chat_get_thread_for_access($pdo, $user, $threadId),
        'messages' => $messages,
        'filters' => $filters,
        'user' => support_chat_api_user_meta($user),
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $action = strtolower(trim((string) ($_GET['action'] ?? 'bootstrap')));
    $filters = support_chat_api_filters($_GET);
    $threadId = max(0, (int) ($_GET['thread_id'] ?? 0));

    try {
        if ($action === 'bootstrap') {
            api_ok(support_chat_api_state($pdo, $user, $filters, $threadId));
        }

        if ($action === 'messages') {
            if ($threadId <= 0) {
                api_error('Thread support tidak valid.', 422, 'validation_error');
            }

            api_ok(support_chat_api_incremental_state(
                $pdo,
                $user,
                $filters,
                $threadId,
                max(0, (int) ($_GET['after_id'] ?? 0))
            ));
        }

        api_error('Action support chat tidak valid.', 404, 'not_found');
    } catch (Throwable $e) {
        api_error($e->getMessage() !== '' ? $e->getMessage() : 'Gagal memuat support chat.', 500, 'server_error');
    }
}

if ($method !== 'POST') {
    api_error('Method tidak diizinkan.', 405, 'method_not_allowed');
}

$body = api_json_body();
if (!csrf_validate((string) ($body['csrf_token'] ?? ''))) {
    api_error('Permintaan tidak valid. Silakan muat ulang halaman.', 419, 'invalid_csrf');
}

$action = strtolower(trim((string) ($body['action'] ?? '')));
$threadId = max(0, (int) ($body['thread_id'] ?? 0));
$filters = support_chat_api_filters(is_array($body['filters'] ?? null) ? $body['filters'] : []);

try {
    if ($action === 'send_message') {
        if ($threadId <= 0) {
            api_error('Thread support tidak valid.', 422, 'validation_error');
        }

        support_chat_send_user_message($pdo, $user, $threadId, (string) ($body['message'] ?? ''));
        api_ok(support_chat_api_state($pdo, $user, $filters, $threadId));
    }

    if ($action === 'set_priority') {
        if ($threadId <= 0) {
            api_error('Thread support tidak valid.', 422, 'validation_error');
        }

        support_chat_set_thread_priority($pdo, $user, $threadId, (string) ($body['priority'] ?? ''));
        api_ok(support_chat_api_state($pdo, $user, $filters, $threadId));
    }

    if ($action === 'set_status') {
        if ($threadId <= 0) {
            api_error('Thread support tidak valid.', 422, 'validation_error');
        }

        support_chat_set_thread_status($pdo, $user, $threadId, (string) ($body['status'] ?? ''));
        api_ok(support_chat_api_state($pdo, $user, $filters, $threadId));
    }

    if ($action === 'set_mode') {
        if ($threadId <= 0) {
            api_error('Thread support tidak valid.', 422, 'validation_error');
        }

        support_chat_set_thread_mode($pdo, $user, $threadId, (string) ($body['mode'] ?? ''));
        api_ok(support_chat_api_state($pdo, $user, $filters, $threadId));
    }

    if ($action === 'assign_to_me') {
        if ($threadId <= 0) {
            api_error('Thread support tidak valid.', 422, 'validation_error');
        }

        support_chat_assign_thread($pdo, $user, $threadId, (int) ($user['id'] ?? 0));
        api_ok(support_chat_api_state($pdo, $user, $filters, $threadId));
    }

    if ($action === 'unassign') {
        if ($threadId <= 0) {
            api_error('Thread support tidak valid.', 422, 'validation_error');
        }

        support_chat_assign_thread($pdo, $user, $threadId, null);
        api_ok(support_chat_api_state($pdo, $user, $filters, $threadId));
    }

    api_error('Action support chat tidak valid.', 404, 'not_found');
} catch (Throwable $e) {
    api_error($e->getMessage() !== '' ? $e->getMessage() : 'Gagal memproses support chat.', 500, 'server_error');
}
