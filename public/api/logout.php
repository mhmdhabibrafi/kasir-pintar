<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/api_helper.php';

api_require_method('POST');

$auth = api_require_auth(['admin', 'bos', 'karyawan']);
$token = (string) ($auth['token'] ?? '');
if ($token === '') {
    api_error('Token tidak ditemukan.', 401, 'missing_token');
}

$pdo = db();
api_revoke_token($pdo, $token);

audit_log('api_logout', [
    'user_id' => (int) ($auth['user']['id'] ?? 0),
    'role' => (string) ($auth['user']['role'] ?? ''),
]);

api_ok(['message' => 'Logout berhasil.']);
