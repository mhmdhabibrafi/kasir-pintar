<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/db_migration_helper.php';
require_once __DIR__ . '/../helpers/domain_helper.php';
require_once __DIR__ . '/../helpers/maintenance_helper.php';
require_once __DIR__ . '/../helpers/store_registration_helper.php';

function require_login(): void
{
    if (!is_logged_in()) {
        send_security_headers();
        header('Location: ' . base_url('login.php'));
        exit;
    }

    maintenance_guard(current_user());

    ensure_update_schema();
    $user = current_user();
    if (!$user) {
        send_security_headers();
        header('Location: ' . base_url('login.php'));
        exit;
    }

    $accessIssue = store_registration_access_issue(db(), $user);
    if ($accessIssue !== null) {
        logout_user();
        send_security_headers();
        header('Location: ' . base_url('login.php?blocked=store_access'));
        exit;
    }

    $domainIssue = store_domain_access_issue(db(), $user);
    if ($domainIssue !== null) {
        audit_log('domain_access_blocked', [
            'reason' => (string) ($domainIssue['code'] ?? 'domain_access'),
            'host' => store_domain_current_host(),
        ]);
        logout_user();
        send_security_headers();
        header('Location: ' . base_url('login.php?blocked=domain_access'));
        exit;
    }
}

function require_role(array $allowedRoles): void
{
    require_login();
    $user = current_user();
    $role = $user['role'] ?? '';

    if (!in_array($role, $allowedRoles, true)) {
        send_security_headers();
        header('Location: ' . role_redirect($role));
        exit;
    }
}
