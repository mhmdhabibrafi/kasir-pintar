<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/api_helper.php';
require_once __DIR__ . '/../../app/helpers/shift_helper.php';
require_once __DIR__ . '/../../app/helpers/user_permission_helper.php';

api_require_method('GET');

$auth = api_require_auth(['admin', 'bos', 'karyawan']);
$user = $auth['user'];
$activeShift = shift_get_active((int) $user['id']);
$shiftSummary = $activeShift ? shift_summary((string) ($activeShift['shift_id'] ?? '')) : null;

api_ok([
    'user' => [
        'id' => (int) $user['id'],
        'name' => (string) ($user['name'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'permissions' => user_permissions($user),
    ],
    'active_shift' => $activeShift,
    'shift_summary' => $shiftSummary,
    'shift_cash_balance' => $activeShift ? shift_expected_cash($activeShift, $shiftSummary) : 0.0,
]);
