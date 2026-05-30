<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_migration_helper.php';
require_once __DIR__ . '/tenant_helper.php';

function inventory_pdo(?PDO $pdo = null): PDO
{
    ensure_update_schema();
    return $pdo instanceof PDO ? $pdo : db();
}

function inventory_store(?PDO $pdo = null): array
{
    $pdo = inventory_pdo($pdo);
    $where = tenant_where_clause($pdo, 'inventory_items', 'inventory_items');
    $stmt = $where !== '' ? $pdo->prepare('SELECT product_id, stock, min_stock FROM inventory_items' . $where) : $pdo->query('SELECT product_id, stock, min_stock FROM inventory_items');
    if ($where !== '' && $stmt instanceof PDOStatement) {
        $stmt->execute(tenant_bind([], $pdo));
    }
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[(int) $row['product_id']] = [
            'stock' => $row['stock'] === null ? null : (int) $row['stock'],
            'min' => (int) $row['min_stock'],
        ];
    }
    return ['items' => $items, 'history' => [], 'updated_at' => date('Y-m-d H:i:s')];
}

function inventory_save(array $store): void
{
    // Deprecated: inventory now stored in database.
}

function inventory_get_item(int $productId, ?PDO $pdo = null): ?array
{
    $pdo = inventory_pdo($pdo);
    $stmt = $pdo->prepare('SELECT stock, min_stock FROM inventory_items WHERE product_id = :product_id' . tenant_where_clause($pdo, 'inventory_items', 'inventory_items', 'AND') . ' LIMIT 1');
    $stmt->execute(tenant_bind([':product_id' => $productId], $pdo));
    $item = $stmt->fetch();
    if (!$item) {
        return null;
    }
    return [
        'stock' => $item['stock'] === null ? null : (int) $item['stock'],
        'min' => (int) $item['min_stock'],
    ];
}

function inventory_set_item(int $productId, ?int $stock, ?int $min, int $userId, string $note = '', ?PDO $pdo = null): void
{
    $pdo = inventory_pdo($pdo);
    $minValue = $min ?? 0;
    $hasStore = tenant_table_has_column($pdo, 'inventory_items', 'store_id');
    $stmt = $pdo->prepare(
        'INSERT INTO inventory_items (product_id, stock, min_stock' . ($hasStore ? ', store_id' : '') . ')
         VALUES (:product_id, :stock, :min_stock' . ($hasStore ? ', :store_id' : '') . ')
         ON DUPLICATE KEY UPDATE stock = VALUES(stock), min_stock = VALUES(min_stock)'
    );
    $params = [
        ':product_id' => $productId,
        ':stock' => $stock,
        ':min_stock' => $minValue,
    ];
    if ($hasStore) {
        $params[':store_id'] = tenant_active_store_id($pdo);
    }
    $stmt->execute($params);

    $hasLogStore = tenant_table_has_column($pdo, 'inventory_logs', 'store_id');
    $logStmt = $pdo->prepare(
        'INSERT INTO inventory_logs (product_id, delta, stock, min_stock, reason, ref, note, user_id' . ($hasLogStore ? ', store_id' : '') . ')
         VALUES (:product_id, :delta, :stock, :min_stock, :reason, :ref, :note, :user_id' . ($hasLogStore ? ', :store_id' : '') . ')'
    );
    $logParams = [
        ':product_id' => $productId,
        ':delta' => null,
        ':stock' => $stock,
        ':min_stock' => $minValue,
        ':reason' => 'set',
        ':ref' => null,
        ':note' => $note,
        ':user_id' => $userId,
    ];
    if ($hasLogStore) {
        $logParams[':store_id'] = tenant_active_store_id($pdo);
    }
    $logStmt->execute($logParams);
}

function inventory_adjust(int $productId, int $delta, string $reason, string $ref, int $userId, string $note = '', ?PDO $pdo = null): void
{
    $pdo = inventory_pdo($pdo);
    $stmt = $pdo->prepare('SELECT stock, min_stock FROM inventory_items WHERE product_id = :product_id' . tenant_where_clause($pdo, 'inventory_items', 'inventory_items', 'AND') . ' LIMIT 1 FOR UPDATE');
    $stmt->execute(tenant_bind([':product_id' => $productId], $pdo));
    $row = $stmt->fetch();

    $current = $row ? $row['stock'] : null;
    $minStock = $row ? (int) $row['min_stock'] : 0;
    if ($current === null) {
        $current = 0;
    }
    $newStock = (int) $current + $delta;

    $hasStore = tenant_table_has_column($pdo, 'inventory_items', 'store_id');
    $upsert = $pdo->prepare(
        'INSERT INTO inventory_items (product_id, stock, min_stock' . ($hasStore ? ', store_id' : '') . ')
         VALUES (:product_id, :stock, :min_stock' . ($hasStore ? ', :store_id' : '') . ')
         ON DUPLICATE KEY UPDATE stock = VALUES(stock), min_stock = VALUES(min_stock)'
    );
    $params = [
        ':product_id' => $productId,
        ':stock' => $newStock,
        ':min_stock' => $minStock,
    ];
    if ($hasStore) {
        $params[':store_id'] = tenant_active_store_id($pdo);
    }
    $upsert->execute($params);

    $hasLogStore = tenant_table_has_column($pdo, 'inventory_logs', 'store_id');
    $logStmt = $pdo->prepare(
        'INSERT INTO inventory_logs (product_id, delta, stock, min_stock, reason, ref, note, user_id' . ($hasLogStore ? ', store_id' : '') . ')
         VALUES (:product_id, :delta, :stock, :min_stock, :reason, :ref, :note, :user_id' . ($hasLogStore ? ', :store_id' : '') . ')'
    );
    $logParams = [
        ':product_id' => $productId,
        ':delta' => $delta,
        ':stock' => $newStock,
        ':min_stock' => $minStock,
        ':reason' => $reason,
        ':ref' => $ref,
        ':note' => $note,
        ':user_id' => $userId,
    ];
    if ($hasLogStore) {
        $logParams[':store_id'] = tenant_active_store_id($pdo);
    }
    $logStmt->execute($logParams);
}

function inventory_reduce(int $productId, int $quantity, string $reason, string $ref, int $userId, string $note = '', ?PDO $pdo = null): bool
{
    if ($quantity <= 0) {
        return true;
    }

    $pdo = inventory_pdo($pdo);
    $stmt = $pdo->prepare('SELECT stock, min_stock FROM inventory_items WHERE product_id = :product_id' . tenant_where_clause($pdo, 'inventory_items', 'inventory_items', 'AND') . ' LIMIT 1 FOR UPDATE');
    $stmt->execute(tenant_bind([':product_id' => $productId], $pdo));
    $row = $stmt->fetch();

    if (!$row || $row['stock'] === null) {
        return true;
    }

    $current = (int) $row['stock'];
    if ($current < $quantity) {
        return false;
    }

    $minStock = (int) ($row['min_stock'] ?? 0);
    $newStock = $current - $quantity;

    $hasStore = tenant_table_has_column($pdo, 'inventory_items', 'store_id');
    $upsert = $pdo->prepare(
        'INSERT INTO inventory_items (product_id, stock, min_stock' . ($hasStore ? ', store_id' : '') . ')
         VALUES (:product_id, :stock, :min_stock' . ($hasStore ? ', :store_id' : '') . ')
         ON DUPLICATE KEY UPDATE stock = VALUES(stock), min_stock = VALUES(min_stock)'
    );
    $params = [
        ':product_id' => $productId,
        ':stock' => $newStock,
        ':min_stock' => $minStock,
    ];
    if ($hasStore) {
        $params[':store_id'] = tenant_active_store_id($pdo);
    }
    $upsert->execute($params);

    $hasLogStore = tenant_table_has_column($pdo, 'inventory_logs', 'store_id');
    $logStmt = $pdo->prepare(
        'INSERT INTO inventory_logs (product_id, delta, stock, min_stock, reason, ref, note, user_id' . ($hasLogStore ? ', store_id' : '') . ')
         VALUES (:product_id, :delta, :stock, :min_stock, :reason, :ref, :note, :user_id' . ($hasLogStore ? ', :store_id' : '') . ')'
    );
    $logParams = [
        ':product_id' => $productId,
        ':delta' => -1 * $quantity,
        ':stock' => $newStock,
        ':min_stock' => $minStock,
        ':reason' => $reason,
        ':ref' => $ref,
        ':note' => $note,
        ':user_id' => $userId,
    ];
    if ($hasLogStore) {
        $logParams[':store_id'] = tenant_active_store_id($pdo);
    }
    $logStmt->execute($logParams);

    return true;
}

function inventory_get_all(?PDO $pdo = null): array
{
    $pdo = inventory_pdo($pdo);
    $where = tenant_where_clause($pdo, 'inventory_items', 'inventory_items');
    $stmt = $where !== '' ? $pdo->prepare('SELECT product_id, stock, min_stock FROM inventory_items' . $where) : $pdo->query('SELECT product_id, stock, min_stock FROM inventory_items');
    if ($where !== '' && $stmt instanceof PDOStatement) {
        $stmt->execute(tenant_bind([], $pdo));
    }
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[(int) $row['product_id']] = [
            'stock' => $row['stock'] === null ? null : (int) $row['stock'],
            'min' => (int) $row['min_stock'],
        ];
    }
    return $items;
}

function inventory_low_stock(array $products, ?PDO $pdo = null): array
{
    $items = inventory_get_all($pdo);
    $low = [];
    foreach ($products as $product) {
        $id = (int) ($product['id'] ?? 0);
        if ($id <= 0 || !isset($items[$id])) {
            continue;
        }
        $rawStock = $items[$id]['stock'] ?? null;
        if ($rawStock === null) {
            continue;
        }
        $stock = (int) $rawStock;
        $min = (int) ($items[$id]['min'] ?? 0);
        if ($stock <= $min) {
            $low[] = [
                'id' => $id,
                'name' => $product['name'] ?? '',
                'stock' => $stock,
                'min' => $min,
            ];
        }
    }
    return $low;
}
