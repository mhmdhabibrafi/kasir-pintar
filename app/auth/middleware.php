<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth_helper.php';

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

function require_role(array $allowedRoles): void
{
    require_login();
    $user = current_user();
    $role = $user['role'] ?? '';

    if (!in_array($role, $allowedRoles, true)) {
        header('Location: ' . role_redirect($role));
        exit;
    }
}
