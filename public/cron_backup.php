<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/backup_helper.php';

send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$config = backup_get_config();
$receivedToken = trim((string) ($_GET['token'] ?? ($_SERVER['HTTP_X_BACKUP_TOKEN'] ?? '')));
$expectedToken = (string) ($config['cron_token'] ?? '');

if ($receivedToken === '' || $expectedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'status' => 'forbidden',
        'message' => 'Token tidak valid.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$force = isset($_GET['force']) && $_GET['force'] === '1';
$result = backup_run([
    'force' => $force,
    'trigger' => 'cron',
]);

$httpCode = 200;
if (($result['status'] ?? '') === 'error') {
    $httpCode = 500;
}
http_response_code($httpCode);

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
