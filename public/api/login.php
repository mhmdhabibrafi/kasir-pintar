<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/api_helper.php';

api_require_method('POST');

$body = api_json_body();
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');
$deviceName = trim((string) ($body['device_name'] ?? 'android'));

if ($username === '' || $password === '') {
    api_error('Username dan password wajib diisi.', 422, 'validation_error');
}

$rateKey = login_rate_limit_key($username);
$rate = login_rate_limit_check($rateKey);
if (!(bool) ($rate['allowed'] ?? false)) {
    api_error(
        'Terlalu banyak percobaan login. Coba lagi nanti.',
        429,
        'rate_limited',
        ['retry_after' => (int) ($rate['retry_after'] ?? 30)]
    );
}

$pdo = db();
$result = api_attempt_login($pdo, $username, $password);
if (!(bool) ($result['ok'] ?? false)) {
    login_rate_limit_record($rateKey, false);
    security_log('api_login_failed', ['username' => $username, 'reason' => $result['reason'] ?? 'unknown']);

    if (($result['reason'] ?? '') === 'inactive_user') {
        api_error('Akun nonaktif. Hubungi admin.', 403, 'inactive_user');
    }
    if (($result['reason'] ?? '') === 'maintenance_mode') {
        api_error('Sistem sedang maintenance. Sementara hanya super admin yang bisa masuk.', 503, 'maintenance_mode');
    }
    if (in_array(($result['reason'] ?? ''), ['store_suspended', 'store_not_ready', 'store_not_found'], true)) {
        api_error((string) ($result['message'] ?? 'Akses toko sedang dibatasi.'), 403, (string) ($result['reason'] ?? 'store_access_denied'));
    }

    if (($result['reason'] ?? '') === 'password_column_missing') {
        api_error('Konfigurasi login tidak valid pada server.', 500, 'server_misconfigured');
    }

    api_error('Username atau password salah.', 401, 'invalid_credentials');
}

login_rate_limit_record($rateKey, true);

$user = $result['user'];
$tokenData = api_issue_token($pdo, (int) $user['id'], $deviceName !== '' ? $deviceName : 'android', 30);
if ((int) ($user['store_id'] ?? 0) > 0) {
    store_registration_touch_login($pdo, (int) $user['store_id']);
}

audit_log('api_login_success', [
    'user_id' => (int) $user['id'],
    'role' => $user['role'],
]);

api_ok([
    'token' => $tokenData['token'],
    'token_type' => 'Bearer',
    'expires_at' => $tokenData['expires_at'],
    'user' => $user,
]);
