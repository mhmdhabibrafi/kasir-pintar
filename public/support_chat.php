<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/support_chat_helper.php';

require_login();

$pdo = db();
$user = current_user() ?? [];
if (!support_chat_enabled()) {
    send_security_headers();
    http_response_code(404);
    echo 'Fitur support chat sedang dinonaktifkan.';
    exit;
}
if (!support_chat_can_access($user)) {
    send_security_headers();
    http_response_code(403);
    echo 'Akses support chat tidak tersedia untuk akun ini.';
    exit;
}

support_chat_ensure_schema($pdo);

$initialFilters = ['search' => '', 'status' => 'all', 'priority' => 'all', 'assigned' => 'all', 'unread_only' => false];
$threads = support_chat_list_threads($pdo, $user, $initialFilters);
$activeThread = $threads[0] ?? null;
$messages = $activeThread ? support_chat_list_messages($pdo, $user, (int) $activeThread['id'], 0, 100) : [];
$threadStats = support_chat_thread_stats(support_chat_list_threads($pdo, $user));
$title = 'Live Support';

require_once __DIR__ . '/../app/views/support/chat.php';
