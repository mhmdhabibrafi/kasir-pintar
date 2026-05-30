<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/tenant_helper.php';

class Category
{
    public static function all(PDO $pdo): array
    {
        $where = tenant_where_clause($pdo, 'categories', 'categories');
        $sql = 'SELECT id, name FROM categories' . $where . ' ORDER BY name';
        $stmt = $where !== '' ? $pdo->prepare($sql) : $pdo->query($sql);
        if ($stmt instanceof PDOStatement && $where !== '') {
            $stmt->execute(tenant_bind([], $pdo));
        }
        return $stmt->fetchAll();
    }

    public static function allWithTotals(PDO $pdo, ?string $categoryColumn): array
    {
        if ($categoryColumn) {
            $where = tenant_where_clause($pdo, 'categories', 'categories');
            $joinStore = tenant_table_has_column($pdo, 'products', 'store_id') && tenant_table_has_column($pdo, 'categories', 'store_id')
                ? ' AND products.store_id = categories.store_id'
                : '';
            $sql =
                'SELECT categories.id, categories.name, COUNT(products.id) AS total_products
                 FROM categories
                 LEFT JOIN products ON products.' . $categoryColumn . ' = categories.id' . $joinStore . '
                 ' . $where . '
                 GROUP BY categories.id, categories.name
                 ORDER BY categories.name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(tenant_bind([], $pdo));
            return $stmt->fetchAll();
        }

        $where = tenant_where_clause($pdo, 'categories', 'categories');
        $sql = 'SELECT id, name, 0 AS total_products FROM categories' . $where . ' ORDER BY name';
        $stmt = $where !== '' ? $pdo->prepare($sql) : $pdo->query($sql);
        if ($stmt instanceof PDOStatement && $where !== '') {
            $stmt->execute(tenant_bind([], $pdo));
        }
        return $stmt->fetchAll();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $tenantWhere = tenant_where_clause($pdo, 'categories', 'categories', 'AND');
        $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE id = :id' . $tenantWhere . ' LIMIT 1');
        $stmt->execute(tenant_bind([':id' => $id], $pdo));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(PDO $pdo, string $name): ?array
    {
        $normalized = trim($name);
        if ($normalized === '') {
            return null;
        }

        $tenantWhere = tenant_where_clause($pdo, 'categories', 'categories', 'AND');
        $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE LOWER(name) = LOWER(:name)' . $tenantWhere . ' LIMIT 1');
        $stmt->execute(tenant_bind([':name' => $normalized], $pdo));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(PDO $pdo, string $name): int
    {
        $data = tenant_apply_insert_store($pdo, 'categories', ['name' => $name]);
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $stmt = $pdo->prepare('INSERT INTO categories (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    }

    public static function findOrCreate(PDO $pdo, string $name): int
    {
        $normalized = trim($name);
        if ($normalized === '') {
            throw new InvalidArgumentException('Nama kategori wajib diisi.');
        }

        $existing = self::findByName($pdo, $normalized);
        if ($existing) {
            return (int) $existing['id'];
        }

        return self::create($pdo, $normalized);
    }

    public static function ensureDefault(PDO $pdo, string $name = 'Umum'): int
    {
        return self::findOrCreate($pdo, $name);
    }

    public static function update(PDO $pdo, int $id, string $name): void
    {
        $tenantWhere = tenant_where_clause($pdo, 'categories', 'categories', 'AND');
        $stmt = $pdo->prepare('UPDATE categories SET name = :name WHERE id = :id' . $tenantWhere);
        $stmt->execute(tenant_bind([':name' => $name, ':id' => $id], $pdo));
    }

    public static function delete(PDO $pdo, int $id): void
    {
        $tenantWhere = tenant_where_clause($pdo, 'categories', 'categories', 'AND');
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id' . $tenantWhere);
        $stmt->execute(tenant_bind([':id' => $id], $pdo));
    }
}
