<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth_helper.php';

if (is_logged_in()) {
    $user = current_user();
    header('Location: ' . role_redirect($user['role'] ?? ''));
    exit;
}

header('Location: ' . base_url('login.php'));
exit;
