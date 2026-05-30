<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/tenant_helper.php';

class User
{
    public static function roles(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT id, name FROM roles ORDER BY name');
        return $stmt->fetchAll();
    }

    public static function all(PDO $pdo): array
    {
        $activeColumn = self::activeColumn($pdo);
        $selectActive = $activeColumn ? ', users.' . $activeColumn : '';
        $permissionColumn = self::permissionColumn($pdo);
        $selectPermissions = $permissionColumn ? ', users.' . $permissionColumn . ' AS permissions_json' : '';

        $where = self::tenantWhere($pdo, 'WHERE');
        $sql =
            'SELECT users.id, users.name, users.username, roles.name AS role_name, users.role_id' . $selectActive . $selectPermissions . '
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             ' . $where . '
             ORDER BY users.name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(self::tenantBind([], $pdo));
        return $stmt->fetchAll();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $activeColumn = self::activeColumn($pdo);
        $selectActive = $activeColumn ? ', users.' . $activeColumn : '';
        $permissionColumn = self::permissionColumn($pdo);
        $selectPermissions = $permissionColumn ? ', users.' . $permissionColumn . ' AS permissions_json' : '';

        $stmt = $pdo->prepare(
            'SELECT users.id, users.name, users.username, users.role_id, roles.name AS role_name' . $selectActive . $selectPermissions . '
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id
             ' . self::tenantWhere($pdo, 'AND') . '
             LIMIT 1'
        );
        $stmt->execute(self::tenantBind([':id' => $id], $pdo));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function count(PDO $pdo): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users' . self::tenantWhere($pdo, 'WHERE'));
        $stmt->execute(self::tenantBind([], $pdo));
        return (int) $stmt->fetchColumn();
    }

    public static function passwordColumn(PDO $pdo): ?string
    {
        return self::pickColumn($pdo, 'users', ['password_hash', 'password']);
    }

    public static function activeColumn(PDO $pdo): ?string
    {
        return self::pickColumn($pdo, 'users', ['is_active', 'active', 'status']);
    }

    public static function storeIdColumn(PDO $pdo): ?string
    {
        return self::pickColumn($pdo, 'users', ['store_id']);
    }

    public static function permissionColumn(PDO $pdo): ?string
    {
        return self::pickColumn($pdo, 'users', ['permissions_json']);
    }

    public static function create(PDO $pdo, array $data): int
    {
        $data = tenant_apply_insert_store($pdo, 'users', $data);
        $columns = array_keys($data);
        $placeholders = array_map(static fn ($col) => ':' . $col, $columns);

        $sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }
        $stmt->execute($params);

        return (int) $pdo->lastInsertId();
    }

    public static function update(PDO $pdo, int $id, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $setParts = [];
        $params = [':id' => $id];

        foreach ($data as $column => $value) {
            $setParts[] = $column . ' = :' . $column;
            $params[':' . $column] = $value;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id' . self::tenantWhere($pdo, 'AND');
        $stmt = $pdo->prepare($sql);
        $stmt->execute(self::tenantBind($params, $pdo));
    }

    public static function setActive(PDO $pdo, int $id, int $active): void
    {
        $column = self::activeColumn($pdo);
        if ($column === null) {
            return;
        }

        $stmt = $pdo->prepare('UPDATE users SET ' . $column . ' = :active WHERE id = :id' . self::tenantWhere($pdo, 'AND'));
        $stmt->execute(self::tenantBind([':active' => $active, ':id' => $id], $pdo));
    }

    public static function usernameExists(PDO $pdo, string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $params = [':username' => $username];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function pickColumn(PDO $pdo, string $table, array $candidates): ?string
    {
        if (empty($candidates)) {
            return null;
        }

        $placeholders = implode(', ', array_fill(0, count($candidates), '?'));
        $order = implode("','", $candidates);
        $sql = "SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME IN ($placeholders)
                ORDER BY FIELD(COLUMN_NAME, '$order')
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$table], $candidates));
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }

    private static function hasActiveSessionUser(): bool
    {
        if (!function_exists('current_user')) {
            return false;
        }

        $user = current_user();
        return is_array($user) && !empty($user);
    }

    private static function tenantWhere(PDO $pdo, string $prefix): string
    {
        if (!self::hasActiveSessionUser()) {
            return '';
        }

        return tenant_where_clause($pdo, 'users', 'users', $prefix);
    }

    private static function tenantBind(array $params, PDO $pdo): array
    {
        if (!self::hasActiveSessionUser()) {
            return $params;
        }

        return tenant_bind($params, $pdo);
    }
}
