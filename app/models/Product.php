<?php

declare(strict_types=1);

class Product
{
    public static function all(PDO $pdo): array
    {
        $activeColumn = self::activeColumn($pdo);
        $categoryColumn = self::categoryColumn($pdo);
        $skuColumn = self::skuColumn($pdo);
        $costColumn = self::costColumn($pdo);
        $stockColumn = self::stockColumn($pdo);
        $whereActive = $activeColumn ? 'WHERE products.' . $activeColumn . ' = 1' : '';
        $selectSku = $skuColumn ? ', products.' . $skuColumn . ' AS sku' : '';
        $selectCost = $costColumn ? ', products.' . $costColumn . ' AS cost_price' : '';
        $selectStock = $stockColumn ? ', products.' . $stockColumn . ' AS stock' : '';

        if ($categoryColumn) {
            $stmt = $pdo->query(
                'SELECT products.id, products.name' . $selectSku . ', products.price' . $selectCost . $selectStock . ',
                        products.' . $categoryColumn . ' AS category_id,
                        categories.name AS category_name
                 FROM products
                 LEFT JOIN categories ON categories.id = products.' . $categoryColumn . '
                 ' . $whereActive . '
                 ORDER BY products.name'
            );
            return $stmt->fetchAll();
        }

        $stmt = $pdo->query('SELECT products.id, products.name' . $selectSku . ', products.price' . $selectCost . $selectStock . ' FROM products ' . $whereActive . ' ORDER BY products.name');
        return $stmt->fetchAll();
    }

    public static function allForAdmin(PDO $pdo): array
    {
        $categoryColumn = self::categoryColumn($pdo);
        $skuColumn = self::skuColumn($pdo);
        $selectSku = $skuColumn ? ', products.' . $skuColumn . ' AS sku' : '';
        $activeColumn = self::activeColumn($pdo);
        $selectActive = $activeColumn ? ', products.' . $activeColumn . ' AS is_active' : '';
        $costColumn = self::costColumn($pdo);
        $selectCost = $costColumn ? ', products.' . $costColumn . ' AS cost_price' : '';
        $stockColumn = self::stockColumn($pdo);
        $selectStock = $stockColumn ? ', products.' . $stockColumn . ' AS stock' : '';

        if ($categoryColumn) {
            $stmt = $pdo->query(
                'SELECT products.id, products.name' . $selectSku . $selectActive . ', products.price' . $selectCost . $selectStock . ',
                        categories.name AS category_name
                 FROM products
                 LEFT JOIN categories ON categories.id = products.' . $categoryColumn . '
                 ORDER BY products.name'
            );
            return $stmt->fetchAll();
        }

        $stmt = $pdo->query('SELECT id, name' . $selectSku . $selectActive . ', price' . $selectCost . $selectStock . ' FROM products ORDER BY name');
        return $stmt->fetchAll();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function count(PDO $pdo): int
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM products');
        return (int) $stmt->fetchColumn();
    }

    public static function create(PDO $pdo, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn ($col) => ':' . $col, $columns);
        $sql = 'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

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

        $sql = 'UPDATE products SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    public static function delete(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function findByIds(PDO $pdo, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $costColumn = self::costColumn($pdo);
        $stockColumn = self::stockColumn($pdo);
        $selectCost = $costColumn ? ', ' . $costColumn . ' AS cost_price' : '';
        $selectStock = $stockColumn ? ', ' . $stockColumn . ' AS stock' : '';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, name, price{$selectCost}{$selectStock} FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $products = [];
        foreach ($rows as $row) {
            $products[(int) $row['id']] = $row;
        }

        return $products;
    }

    public static function reduceStock(PDO $pdo, int $productId, int $quantity): bool
    {
        $stockColumn = self::stockColumn($pdo);
        if (!$stockColumn) {
            return true;
        }

        $stmt = $pdo->prepare(
            'UPDATE products SET ' . $stockColumn . ' = ' . $stockColumn . ' - :qty
             WHERE id = :id AND ' . $stockColumn . ' >= :qty'
        );
        $stmt->execute([':qty' => $quantity, ':id' => $productId]);
        return $stmt->rowCount() > 0;
    }

    public static function categoryColumn(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'products'
               AND COLUMN_NAME IN ('category_id', 'categoryId')
             ORDER BY FIELD(COLUMN_NAME, 'category_id', 'categoryId')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }

    public static function activeColumn(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'products'
               AND COLUMN_NAME IN ('is_active', 'active', 'status')
             ORDER BY FIELD(COLUMN_NAME, 'is_active', 'active', 'status')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }

    public static function skuColumn(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'products'
               AND COLUMN_NAME IN ('sku', 'code', 'barcode')
             ORDER BY FIELD(COLUMN_NAME, 'sku', 'code', 'barcode')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }

    public static function costColumn(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'products'
               AND COLUMN_NAME IN ('cost_price', 'modal_price', 'harga_modal', 'cost')
             ORDER BY FIELD(COLUMN_NAME, 'cost_price', 'modal_price', 'harga_modal', 'cost')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }

    public static function stockColumn(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'products'
               AND COLUMN_NAME IN ('stock', 'stok')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }
}
