<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function store_domain_normalize(string $host): string
{
    $host = strtolower(trim($host));
    $host = preg_replace('#^https?://#', '', $host) ?? $host;
    $host = preg_replace('#/.*$#', '', $host) ?? $host;
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $host = trim($host, " \t\n\r\0\x0B.");

    if (function_exists('idn_to_ascii') && $host !== '') {
        $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (is_string($ascii) && $ascii !== '') {
            $host = strtolower($ascii);
        }
    }

    return $host;
}

function store_domain_current_host(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }

    return store_domain_normalize($host);
}

function store_domain_platform_hosts(): array
{
    $hosts = ['localhost', '127.0.0.1', '::1'];

    $envHosts = [
        getenv('APP_HOST') ?: '',
        getenv('APP_PRIMARY_HOST') ?: '',
        getenv('KASPINDO_PRIMARY_HOST') ?: '',
    ];

    foreach ($envHosts as $host) {
        $normalized = store_domain_normalize((string) $host);
        if ($normalized !== '') {
            $hosts[] = $normalized;
        }
    }

    $platformHosts = (string) (getenv('APP_PLATFORM_HOSTS') ?: '');
    foreach (preg_split('/[\s,]+/', $platformHosts) ?: [] as $host) {
        $normalized = store_domain_normalize((string) $host);
        if ($normalized !== '') {
            $hosts[] = $normalized;
        }
    }

    $appUrl = trim((string) (getenv('APP_URL') ?: ''));
    if ($appUrl !== '') {
        $appUrlHost = parse_url($appUrl, PHP_URL_HOST);
        if (is_string($appUrlHost) && $appUrlHost !== '') {
            $hosts[] = store_domain_normalize($appUrlHost);
        }
    }

    if (defined('BASE_URL') && BASE_URL !== '') {
        $baseHost = parse_url(BASE_URL, PHP_URL_HOST);
        if (is_string($baseHost) && $baseHost !== '') {
            $hosts[] = store_domain_normalize($baseHost);
        }
    }

    return array_values(array_unique(array_filter($hosts)));
}

function store_domain_dns_target(): string
{
    $target = store_domain_normalize((string) (getenv('APP_DOMAIN_TARGET') ?: ''));
    if ($target !== '') {
        return $target;
    }

    $primary = store_domain_normalize((string) (getenv('APP_PRIMARY_HOST') ?: getenv('KASPINDO_PRIMARY_HOST') ?: ''));
    if ($primary !== '') {
        return $primary;
    }

    $appUrl = trim((string) (getenv('APP_URL') ?: ''));
    if ($appUrl !== '') {
        $host = parse_url($appUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return store_domain_normalize($host);
        }
    }

    return '';
}

function store_domain_is_platform_host(string $host): bool
{
    $host = store_domain_normalize($host);
    return $host !== '' && in_array($host, store_domain_platform_hosts(), true);
}

function store_domain_schema_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table'
        );
        $stmt->execute([':table' => 'store_domains']);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function store_domain_find_by_host(PDO $pdo, string $host): ?array
{
    $domain = store_domain_normalize($host);
    if ($domain === '' || store_domain_is_platform_host($domain) || !store_domain_schema_ready($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT store_domains.*, stores.store_name, stores.store_code, stores.status AS store_status,
                COALESCE(stores.operational_status, 'active') AS operational_status
         FROM store_domains
         INNER JOIN stores ON stores.id = store_domains.store_id
         WHERE store_domains.domain = :domain
           AND store_domains.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([':domain' => $domain]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function store_domain_current(PDO $pdo): ?array
{
    static $cache = [];

    $host = store_domain_current_host();
    if ($host === '') {
        return null;
    }
    if (array_key_exists($host, $cache)) {
        return $cache[$host];
    }

    $cache[$host] = store_domain_find_by_host($pdo, $host);
    return $cache[$host];
}

function store_domain_current_store_id(?PDO $pdo = null): ?int
{
    $pdo = $pdo instanceof PDO ? $pdo : db();
    $domain = store_domain_current($pdo);
    $storeId = (int) ($domain['store_id'] ?? 0);

    return $storeId > 0 ? $storeId : null;
}

function store_domain_access_issue(PDO $pdo, array $user): ?array
{
    $domain = store_domain_current($pdo);
    if (!$domain) {
        return null;
    }

    if ((string) ($domain['store_status'] ?? '') !== 'approved') {
        return [
            'code' => 'domain_store_not_approved',
            'message' => 'Domain toko ini belum aktif. Hubungi super admin KASPINDO.',
        ];
    }

    if ((string) ($domain['operational_status'] ?? 'active') === 'suspended') {
        return [
            'code' => 'domain_store_suspended',
            'message' => 'Akses toko pada domain ini sedang disuspend oleh super admin.',
        ];
    }

    $role = (string) ($user['role'] ?? $user['role_name'] ?? '');
    if ($role === 'superadmin') {
        return [
            'code' => 'superadmin_custom_domain_denied',
            'message' => 'Super admin hanya dapat login melalui domain pusat KASPINDO.',
        ];
    }

    $domainStoreId = (int) ($domain['store_id'] ?? 0);
    $userStoreId = (int) ($user['store_id'] ?? 0);
    if ($domainStoreId > 0 && $userStoreId > 0 && $domainStoreId !== $userStoreId) {
        return [
            'code' => 'domain_store_mismatch',
            'message' => 'Akun ini bukan milik toko pada domain yang sedang dibuka.',
        ];
    }

    if ($domainStoreId > 0 && $userStoreId <= 0) {
        return [
            'code' => 'domain_store_missing_user_scope',
            'message' => 'Akun ini belum terhubung ke toko pada domain ini.',
        ];
    }

    return null;
}

function store_domain_all_by_store(PDO $pdo, int $storeId): array
{
    if ($storeId <= 0 || !store_domain_schema_ready($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM store_domains
         WHERE store_id = :store_id
         ORDER BY status = "active" DESC, created_at DESC'
    );
    $stmt->execute([':store_id' => $storeId]);

    return $stmt->fetchAll();
}

function store_domain_create(PDO $pdo, int $storeId, string $domain, int $actorId = 0): array
{
    $domain = store_domain_normalize($domain);
    if ($storeId <= 0) {
        throw new RuntimeException('Toko tidak valid.');
    }
    if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
        throw new RuntimeException('Domain tidak valid. Isi tanpa http:// dan tanpa path.');
    }
    if (str_contains($domain, '..') || str_starts_with($domain, '.') || str_ends_with($domain, '.')) {
        throw new RuntimeException('Domain tidak valid.');
    }
    if (store_domain_is_platform_host($domain)) {
        throw new RuntimeException('Domain pusat KASPINDO tidak boleh dipakai sebagai domain toko.');
    }

    $storeStmt = $pdo->prepare("SELECT id FROM stores WHERE id = :id AND status = 'approved' LIMIT 1");
    $storeStmt->execute([':id' => $storeId]);
    if ((int) $storeStmt->fetchColumn() <= 0) {
        throw new RuntimeException('Domain hanya bisa ditambahkan untuk toko yang sudah approved.');
    }

    $existing = store_domain_find_any($pdo, $domain);
    if ($existing && (int) ($existing['store_id'] ?? 0) !== $storeId) {
        throw new RuntimeException('Domain sudah dipakai toko lain.');
    }

    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE store_domains
             SET status = 'active',
                 verified_at = COALESCE(verified_at, NOW()),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':id' => (int) $existing['id']]);
        return store_domain_find_any($pdo, $domain) ?: $existing;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO store_domains (store_id, domain, status, verified_at, created_by, created_at, updated_at)
         VALUES (:store_id, :domain, 'active', NOW(), :created_by, NOW(), NOW())"
    );
    $stmt->execute([
        ':store_id' => $storeId,
        ':domain' => $domain,
        ':created_by' => $actorId > 0 ? $actorId : null,
    ]);

    return store_domain_find_any($pdo, $domain) ?: ['id' => (int) $pdo->lastInsertId(), 'store_id' => $storeId, 'domain' => $domain];
}

function store_domain_find_any(PDO $pdo, string $domain): ?array
{
    if (!store_domain_schema_ready($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM store_domains WHERE domain = :domain LIMIT 1');
    $stmt->execute([':domain' => store_domain_normalize($domain)]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function store_domain_deactivate(PDO $pdo, int $domainId, int $storeId): void
{
    if ($domainId <= 0 || $storeId <= 0) {
        throw new RuntimeException('Domain tidak valid.');
    }

    $stmt = $pdo->prepare(
        "UPDATE store_domains
         SET status = 'inactive', updated_at = NOW()
         WHERE id = :id AND store_id = :store_id"
    );
    $stmt->execute([':id' => $domainId, ':store_id' => $storeId]);
}
