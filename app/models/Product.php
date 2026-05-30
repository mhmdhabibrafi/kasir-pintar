<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/tenant_helper.php';

class Product
{
    public static function all(PDO $pdo): array
    {
        $activeColumn = self::activeColumn($pdo);
        $categoryColumn = self::categoryColumn($pdo);
        $skuColumn = self::skuColumn($pdo);
        $imageColumn = self::imageColumn($pdo);
        $costColumn = self::costColumn($pdo);
        $stockColumn = self::stockColumn($pdo);
        $conditions = [];
        if ($activeColumn) {
            $conditions[] = 'products.' . $activeColumn . ' = 1';
        }
        $tenantCondition = tenant_filter_sql($pdo, 'products', 'products');
        if ($tenantCondition !== '' && tenant_user_store_id(null, $pdo) !== null) {
            $conditions[] = $tenantCondition;
        }
        $whereActive = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $selectSku = $skuColumn ? ', products.' . $skuColumn . ' AS sku' : '';
        $selectImage = $imageColumn ? ', products.' . $imageColumn . ' AS product_image' : '';
        $selectCost = $costColumn ? ', products.' . $costColumn . ' AS cost_price' : '';
        $selectStock = $stockColumn ? ', products.' . $stockColumn . ' AS stock' : '';
        $categoryJoinStore = tenant_table_has_column($pdo, 'products', 'store_id') && tenant_table_has_column($pdo, 'categories', 'store_id')
            ? ' AND categories.store_id = products.store_id'
            : '';

        if ($categoryColumn) {
            $stmt = $pdo->prepare(
                'SELECT products.id, products.name' . $selectSku . $selectImage . ', products.price' . $selectCost . $selectStock . ',
                        products.' . $categoryColumn . ' AS category_id,
                        categories.name AS category_name
                 FROM products
                 LEFT JOIN categories ON categories.id = products.' . $categoryColumn . $categoryJoinStore . '
                 ' . $whereActive . '
                 ORDER BY products.name'
            );
            $stmt->execute(tenant_bind([], $pdo));
            return $stmt->fetchAll();
        }

        $stmt = $pdo->prepare('SELECT products.id, products.name' . $selectSku . $selectImage . ', products.price' . $selectCost . $selectStock . ' FROM products ' . $whereActive . ' ORDER BY products.name');
        $stmt->execute(tenant_bind([], $pdo));
        return $stmt->fetchAll();
    }

    public static function allForAdmin(PDO $pdo): array
    {
        $categoryColumn = self::categoryColumn($pdo);
        $skuColumn = self::skuColumn($pdo);
        $imageColumn = self::imageColumn($pdo);
        $selectSku = $skuColumn ? ', products.' . $skuColumn . ' AS sku' : '';
        $selectImage = $imageColumn ? ', products.' . $imageColumn . ' AS product_image' : '';
        $activeColumn = self::activeColumn($pdo);
        $selectActive = $activeColumn ? ', products.' . $activeColumn . ' AS is_active' : '';
        $costColumn = self::costColumn($pdo);
        $selectCost = $costColumn ? ', products.' . $costColumn . ' AS cost_price' : '';
        $stockColumn = self::stockColumn($pdo);
        $selectStock = $stockColumn ? ', products.' . $stockColumn . ' AS stock' : '';
        $tenantCondition = tenant_filter_sql($pdo, 'products', 'products');
        $where = $tenantCondition !== '' && tenant_user_store_id(null, $pdo) !== null ? 'WHERE ' . $tenantCondition : '';
        $categoryJoinStore = tenant_table_has_column($pdo, 'products', 'store_id') && tenant_table_has_column($pdo, 'categories', 'store_id')
            ? ' AND categories.store_id = products.store_id'
            : '';

        if ($categoryColumn) {
            $stmt = $pdo->prepare(
                'SELECT products.id, products.name' . $selectSku . $selectImage . $selectActive . ', products.price' . $selectCost . $selectStock . ',
                        products.' . $categoryColumn . ' AS category_id,
                        categories.name AS category_name
                 FROM products
                 LEFT JOIN categories ON categories.id = products.' . $categoryColumn . $categoryJoinStore . '
                 ' . $where . '
                 ORDER BY products.name'
            );
            $stmt->execute(tenant_bind([], $pdo));
            return $stmt->fetchAll();
        }

        $stmt = $pdo->prepare('SELECT id, name' . $selectSku . $selectImage . $selectActive . ', price' . $selectCost . $selectStock . ' FROM products ' . $where . ' ORDER BY name');
        $stmt->execute(tenant_bind([], $pdo));
        return $stmt->fetchAll();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $tenantWhere = tenant_where_clause($pdo, 'products', 'products', 'AND');
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id' . $tenantWhere . ' LIMIT 1');
        $stmt->execute(tenant_bind([':id' => $id], $pdo));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function count(PDO $pdo): int
    {
        $where = tenant_where_clause($pdo, 'products', 'products');
        $stmt = $where !== '' ? $pdo->prepare('SELECT COUNT(*) FROM products' . $where) : $pdo->query('SELECT COUNT(*) FROM products');
        if ($where !== '' && $stmt instanceof PDOStatement) {
            $stmt->execute(tenant_bind([], $pdo));
        }
        return (int) $stmt->fetchColumn();
    }

    public static function create(PDO $pdo, array $data): int
    {
        $data = tenant_apply_insert_store($pdo, 'products', $data);
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

        $sql = 'UPDATE products SET ' . implode(', ', $setParts) . ' WHERE id = :id' . tenant_where_clause($pdo, 'products', 'products', 'AND');
        $stmt = $pdo->prepare($sql);
        $stmt->execute(tenant_bind($params, $pdo));
    }

    public static function delete(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id' . tenant_where_clause($pdo, 'products', 'products', 'AND'));
        $stmt->execute(tenant_bind([':id' => $id], $pdo));
    }

    public static function findByIds(PDO $pdo, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $costColumn = self::costColumn($pdo);
        $stockColumn = self::stockColumn($pdo);
        $imageColumn = self::imageColumn($pdo);
        $selectCost = $costColumn ? ', ' . $costColumn . ' AS cost_price' : '';
        $selectStock = $stockColumn ? ', ' . $stockColumn . ' AS stock' : '';
        $selectImage = $imageColumn ? ', ' . $imageColumn . ' AS product_image' : '';
        $idParams = [];
        $placeholders = [];
        foreach (array_values($ids) as $index => $id) {
            $key = ':product_id_' . $index;
            $placeholders[] = $key;
            $idParams[$key] = (int) $id;
        }
        $placeholders = implode(',', $placeholders);
        $tenantCondition = tenant_filter_sql($pdo, 'products', 'products');
        $tenantSql = $tenantCondition !== '' && tenant_user_store_id(null, $pdo) !== null ? ' AND ' . $tenantCondition : '';
        $stmt = $pdo->prepare("SELECT id, name, price{$selectCost}{$selectStock}{$selectImage} FROM products WHERE id IN ($placeholders)" . $tenantSql);
        $params = $idParams;
        if ($tenantSql !== '') {
            $params[':tenant_store_id'] = tenant_active_store_id($pdo);
        }
        $stmt->execute($params);
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
             WHERE id = :id AND ' . $stockColumn . ' >= :qty' . tenant_where_clause($pdo, 'products', 'products', 'AND')
        );
        $stmt->execute(tenant_bind([':qty' => $quantity, ':id' => $productId], $pdo));
        return $stmt->rowCount() > 0;
    }

    public static function increaseStock(PDO $pdo, int $productId, int $quantity): void
    {
        $stockColumn = self::stockColumn($pdo);
        if (!$stockColumn || $quantity <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE products SET ' . $stockColumn . ' = ' . $stockColumn . ' + :qty
             WHERE id = :id' . tenant_where_clause($pdo, 'products', 'products', 'AND')
        );
        $stmt->execute(tenant_bind([':qty' => $quantity, ':id' => $productId], $pdo));
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

    public static function imageColumn(PDO $pdo): ?string
    {
        static $checked = false;
        static $cached = null;

        if ($checked) {
            return $cached;
        }
        $checked = true;

        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'products'
               AND COLUMN_NAME IN ('image_path', 'product_image', 'image', 'photo')
             ORDER BY FIELD(COLUMN_NAME, 'image_path', 'product_image', 'image', 'photo')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();
        if ($column) {
            $cached = (string) $column;
            return $cached;
        }

        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
        } catch (Throwable $e) {
            // Ignore to keep compatibility on DB without alter privilege.
        }

        $stmt->execute();
        $column = $stmt->fetchColumn();
        $cached = $column ? (string) $column : null;
        return $cached;
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
