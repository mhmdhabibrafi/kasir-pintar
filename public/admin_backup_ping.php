<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/backup_helper.php';

require_role(['superadmin']);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'status' => 'method_not_allowed',
        'message' => 'Method tidak didukung.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!csrf_validate($csrfToken)) {
    http_response_code(419);
    echo json_encode([
        'ok' => false,
        'status' => 'csrf_failed',
        'message' => 'CSRF token tidak valid.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$result = backup_run([
    'force' => false,
    'trigger' => 'superadmin_dashboard_ping',
]);

$httpCode = 200;
if (($result['status'] ?? '') === 'error') {
    $httpCode = 500;
}
http_response_code($httpCode);
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
