<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth_helper.php';

$user = current_user();
if ($user) {
    audit_log('logout', [
        'user_id' => $user['id'] ?? null,
        'username' => $user['username'] ?? null,
        'role' => $user['role'] ?? null,
        'store_id' => $user['store_id'] ?? null,
    ]);
}
logout_user();
send_security_headers();
header('Location: ' . base_url('login.php'));
exit;
