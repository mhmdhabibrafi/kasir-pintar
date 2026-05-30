<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db_migration_helper.php';
require_once __DIR__ . '/maintenance_helper.php';
require_once __DIR__ . '/store_registration_helper.php';
require_once __DIR__ . '/user_permission_helper.php';
require_once __DIR__ . '/../models/User.php';

function api_send(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    send_security_headers(false);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"ok":false,"message":"Internal server error"}';
    }

    echo $json;
    exit;
}

function api_ok(array $data = [], int $statusCode = 200): void
{
    api_send($statusCode, array_merge(['ok' => true], $data));
}

function api_error(string $message, int $statusCode = 400, ?string $code = null, array $extra = []): void
{
    $payload = ['ok' => false, 'message' => $message];
    if ($code !== null) {
        $payload['code'] = $code;
    }
    if (!empty($extra)) {
        $payload = array_merge($payload, $extra);
    }

    api_send($statusCode, $payload);
}

function api_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        api_error('Method tidak diizinkan.', 405, 'method_not_allowed');
    }
}

function api_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_error('Format JSON tidak valid.', 400, 'invalid_json');
    }

    return $data;
}

function api_ensure_token_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS api_tokens (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            device_name VARCHAR(120) DEFAULT NULL,
            last_used_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            revoked_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_api_tokens_user_id (user_id),
            INDEX idx_api_tokens_expires_at (expires_at),
            INDEX idx_api_tokens_revoked_at (revoked_at),
            CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $ready = true;
}

function api_extract_bearer_token(): ?string
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

    if (!$authHeader && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $authHeader;
        }
    }

    if (!$authHeader) {
        return null;
    }

    if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        return null;
    }

    $token = trim((string) ($matches[1] ?? ''));
    return $token !== '' ? $token : null;
}

function api_issue_token(PDO $pdo, int $userId, string $deviceName = 'android', int $ttlDays = 30): array
{
    api_ensure_token_table($pdo);

    $token = bin2hex(random_bytes(40));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('now'))->modify('+' . max(1, $ttlDays) . ' days')->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO api_tokens (user_id, token_hash, device_name, expires_at, last_used_at)
         VALUES (:user_id, :token_hash, :device_name, :expires_at, NOW())'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':token_hash' => $tokenHash,
        ':device_name' => $deviceName !== '' ? $deviceName : null,
        ':expires_at' => $expiresAt,
    ]);

    return ['token' => $token, 'expires_at' => $expiresAt];
}

function api_find_auth(PDO $pdo, string $plainToken): ?array
{
    api_ensure_token_table($pdo);

    $tokenHash = hash('sha256', $plainToken);
    $activeColumn = User::activeColumn($pdo);
    $selectActive = $activeColumn ? ', users.' . $activeColumn . ' AS is_active' : '';
    $storeIdColumn = User::storeIdColumn($pdo);
    $selectStoreId = $storeIdColumn ? ', users.' . $storeIdColumn . ' AS store_id' : '';
    $permissionColumn = User::permissionColumn($pdo);
    $selectPermissions = $permissionColumn ? ', users.' . $permissionColumn . ' AS permissions_json' : '';

    $stmt = $pdo->prepare(
        'SELECT api_tokens.id AS token_id,
                api_tokens.user_id,
                users.name,
                users.username,
                roles.name AS role_name' . $selectActive . $selectStoreId . $selectPermissions . '
         FROM api_tokens
         INNER JOIN users ON users.id = api_tokens.user_id
         INNER JOIN roles ON roles.id = users.role_id
         WHERE api_tokens.token_hash = :token_hash
           AND api_tokens.revoked_at IS NULL
           AND (api_tokens.expires_at IS NULL OR api_tokens.expires_at > NOW())
         LIMIT 1'
    );
    $stmt->execute([':token_hash' => $tokenHash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'token_id' => (int) $row['token_id'],
        'user' => [
            'id' => (int) $row['user_id'],
            'name' => (string) ($row['name'] ?? ''),
            'username' => (string) ($row['username'] ?? ''),
            'role' => (string) ($row['role_name'] ?? ''),
            'is_active' => $activeColumn ? (int) ($row['is_active'] ?? 1) : 1,
            'store_id' => $storeIdColumn ? (int) ($row['store_id'] ?? 0) : 0,
            'permissions_json' => $row['permissions_json'] ?? null,
            'permissions' => user_permissions([
                'role' => (string) ($row['role_name'] ?? ''),
                'permissions_json' => $row['permissions_json'] ?? null,
            ]),
        ],
    ];
}

function api_touch_token(PDO $pdo, int $tokenId): void
{
    $stmt = $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $tokenId]);
}

function api_revoke_token(PDO $pdo, string $plainToken): void
{
    api_ensure_token_table($pdo);
    $tokenHash = hash('sha256', $plainToken);
    $stmt = $pdo->prepare('UPDATE api_tokens SET revoked_at = NOW() WHERE token_hash = :token_hash');
    $stmt->execute([':token_hash' => $tokenHash]);
}

function api_require_auth(array $allowedRoles = []): array
{
    ensure_update_schema();
    $domainHelperPath = __DIR__ . '/domain_helper.php';
    if (is_file($domainHelperPath)) {
        require_once $domainHelperPath;
    }

    $token = api_extract_bearer_token();
    if ($token === null) {
        api_error('Token autentikasi tidak ditemukan.', 401, 'missing_token');
    }

    $pdo = db();
    $auth = api_find_auth($pdo, $token);
    if ($auth === null) {
        api_error('Token tidak valid atau sudah kedaluwarsa.', 401, 'invalid_token');
    }

    $user = $auth['user'];
    if ((int) ($user['is_active'] ?? 1) !== 1) {
        api_error('Akun nonaktif.', 403, 'inactive_user');
    }
    if (maintenance_is_active() && !maintenance_allows_user($user)) {
        api_error('Sistem sedang maintenance. Hanya super admin yang diizinkan masuk.', 503, 'maintenance_mode');
    }

    $accessIssue = store_registration_access_issue($pdo, $user);
    if ($accessIssue !== null) {
        api_error((string) ($accessIssue['message'] ?? 'Akses toko sedang dibatasi.'), 403, (string) ($accessIssue['code'] ?? 'store_access_denied'));
    }
    if (function_exists('store_domain_access_issue')) {
        $domainIssue = store_domain_access_issue($pdo, $user);
        if ($domainIssue !== null) {
            api_error((string) ($domainIssue['message'] ?? 'Akun tidak sesuai dengan domain toko ini.'), 403, (string) ($domainIssue['code'] ?? 'domain_access_denied'));
        }
    }

    if (!empty($allowedRoles) && !in_array($user['role'] ?? '', $allowedRoles, true)) {
        api_error('Anda tidak punya akses untuk endpoint ini.', 403, 'forbidden');
    }

    api_touch_token($pdo, (int) $auth['token_id']);
    $auth['token'] = $token;

    return $auth;
}

function api_attempt_login(PDO $pdo, string $username, string $password): array
{
    ensure_update_schema();

    $passwordColumn = User::passwordColumn($pdo);
    $activeColumn = User::activeColumn($pdo);
    $storeIdColumn = User::storeIdColumn($pdo);
    $permissionColumn = User::permissionColumn($pdo);
    if (!$passwordColumn) {
        return ['ok' => false, 'reason' => 'password_column_missing'];
    }

    $passwordColumn = $passwordColumn === 'password_hash' ? 'password_hash' : 'password';
    $selectActive = $activeColumn ? ', users.' . $activeColumn . ' AS is_active' : '';
    $selectStoreId = $storeIdColumn ? ', users.' . $storeIdColumn . ' AS store_id' : '';
    $selectPermissions = $permissionColumn ? ', users.' . $permissionColumn . ' AS permissions_json' : '';

    $stmt = $pdo->prepare(
        'SELECT users.id, users.name, users.username, users.' . $passwordColumn . ' AS password_hash, roles.name AS role_name' . $selectActive . $selectStoreId . $selectPermissions . '
         FROM users
         INNER JOIN roles ON roles.id = users.role_id
         WHERE users.username = :username
         LIMIT 1'
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    $passwordHash = (string) ($user['password_hash'] ?? '');
    $verified = $user ? password_verify($password, $passwordHash) : false;
    $legacyMatched = false;

    if ($user && !$verified) {
        if (strlen($passwordHash) === 32 && ctype_xdigit($passwordHash)) {
            $legacyMatched = hash_equals($passwordHash, md5($password));
        } elseif ($passwordHash !== '' && hash_equals($passwordHash, $password)) {
            $legacyMatched = true;
        }
    }

    if (!$user || (!$verified && !$legacyMatched)) {
        return ['ok' => false, 'reason' => 'invalid_credentials'];
    }

    if ($activeColumn && (int) ($user['is_active'] ?? 1) !== 1) {
        return ['ok' => false, 'reason' => 'inactive_user'];
    }
    if (maintenance_is_active() && !maintenance_allows_user(['role' => (string) ($user['role_name'] ?? '')])) {
        return ['ok' => false, 'reason' => 'maintenance_mode'];
    }

    $storeAccessIssue = store_registration_access_issue($pdo, [
        'role' => (string) ($user['role_name'] ?? ''),
        'store_id' => $storeIdColumn ? (int) ($user['store_id'] ?? 0) : 0,
    ]);
    if ($storeAccessIssue !== null) {
        return [
            'ok' => false,
            'reason' => (string) ($storeAccessIssue['code'] ?? 'store_access_denied'),
            'message' => (string) ($storeAccessIssue['message'] ?? 'Akses toko sedang dibatasi.'),
        ];
    }

    if ($legacyMatched || password_needs_rehash($passwordHash, PASSWORD_DEFAULT)) {
        User::update($pdo, (int) $user['id'], [
            $passwordColumn => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    return [
        'ok' => true,
        'user' => [
            'id' => (int) $user['id'],
            'name' => (string) ($user['name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'role' => (string) ($user['role_name'] ?? ''),
            'store_id' => $storeIdColumn ? (int) ($user['store_id'] ?? 0) : 0,
            'permissions' => user_permissions([
                'role' => (string) ($user['role_name'] ?? ''),
                'permissions_json' => $user['permissions_json'] ?? null,
            ]),
        ],
    ];
}
