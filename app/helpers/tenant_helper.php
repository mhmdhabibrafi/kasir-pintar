<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/domain_helper.php';

function tenant_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table
               AND column_name = :column'
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        $cache[$key] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function tenant_default_store_id(?PDO $pdo = null): int
{
    $pdo = $pdo instanceof PDO ? $pdo : db();

    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE store_code = 'KSPDMO0001' LIMIT 1");
        $stmt->execute();
        $storeId = (int) $stmt->fetchColumn();
        if ($storeId > 0) {
            return $storeId;
        }

        $stmt = $pdo->query('SELECT id FROM stores ORDER BY id ASC LIMIT 1');
        $storeId = (int) $stmt->fetchColumn();
        if ($storeId > 0) {
            return $storeId;
        }
    } catch (Throwable $e) {
        // Keep callers resilient during early install/migration.
    }

    return 1;
}

function tenant_user_store_id(?array $user = null, ?PDO $pdo = null): ?int
{
    $user = $user ?? current_user();
    if (!$user) {
        $domainStoreId = null;
        try {
            $domainStoreId = store_domain_current_store_id($pdo);
        } catch (Throwable $e) {
            $domainStoreId = null;
        }

        return $domainStoreId !== null && $domainStoreId > 0 ? $domainStoreId : tenant_default_store_id($pdo);
    }

    if ((string) ($user['role'] ?? '') === 'superadmin') {
        return null;
    }

    $storeId = (int) ($user['store_id'] ?? 0);
    return $storeId > 0 ? $storeId : tenant_default_store_id($pdo);
}

function tenant_active_store_id(?PDO $pdo = null, ?array $user = null): int
{
    $storeId = tenant_user_store_id($user, $pdo);
    return $storeId !== null && $storeId > 0 ? $storeId : tenant_default_store_id($pdo);
}

function tenant_filter_sql(PDO $pdo, string $table, string $alias = '', ?int $storeId = null): string
{
    if (!tenant_table_has_column($pdo, $table, 'store_id')) {
        return '';
    }

    $ref = $alias !== '' ? $alias : $table;
    return $ref . '.store_id = :tenant_store_id';
}

function tenant_bind(array $params, PDO $pdo, ?array $user = null): array
{
    $storeId = tenant_user_store_id($user, $pdo);
    if ($storeId !== null) {
        $params[':tenant_store_id'] = $storeId;
    }

    return $params;
}

function tenant_apply_insert_store(PDO $pdo, string $table, array $data, ?array $user = null): array
{
    if (tenant_table_has_column($pdo, $table, 'store_id') && !array_key_exists('store_id', $data)) {
        $data['store_id'] = tenant_active_store_id($pdo, $user);
    }

    return $data;
}

function tenant_where_clause(PDO $pdo, string $table, string $alias = '', string $prefix = 'WHERE', ?array $user = null): string
{
    $storeId = tenant_user_store_id($user, $pdo);
    if ($storeId === null || !tenant_table_has_column($pdo, $table, 'store_id')) {
        return '';
    }

    $ref = $alias !== '' ? $alias : $table;
    return ' ' . $prefix . ' ' . $ref . '.store_id = :tenant_store_id ';
}

function tenant_multi_where_clause(PDO $pdo, array $tables, string $prefix = 'AND', ?array $user = null): array
{
    $storeId = tenant_user_store_id($user, $pdo);
    if ($storeId === null) {
        return ['sql' => '', 'params' => []];
    }

    $clauses = [];
    $params = [];
    $index = 0;

    foreach ($tables as $table => $alias) {
        if (is_int($table)) {
            $table = (string) $alias;
            $alias = (string) $alias;
        }

        $table = (string) $table;
        $alias = (string) $alias;
        if ($table === '' || !tenant_table_has_column($pdo, $table, 'store_id')) {
            continue;
        }

        $placeholder = ':tenant_store_id_' . $index;
        $ref = $alias !== '' ? $alias : $table;
        $clauses[] = ' ' . ($index === 0 ? $prefix : 'AND') . ' ' . $ref . '.store_id = ' . $placeholder . ' ';
        $params[$placeholder] = $storeId;
        $index++;
    }

    return ['sql' => implode('', $clauses), 'params' => $params];
}
