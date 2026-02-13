<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_migration_helper.php';

function inventory_store(): array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->query('SELECT product_id, stock, min_stock FROM inventory_items');
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

function inventory_get_item(int $productId): ?array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare('SELECT stock, min_stock FROM inventory_items WHERE product_id = :product_id LIMIT 1');
    $stmt->execute([':product_id' => $productId]);
    $item = $stmt->fetch();
    if (!$item) {
        return null;
    }
    return [
        'stock' => $item['stock'] === null ? null : (int) $item['stock'],
        'min' => (int) $item['min_stock'],
    ];
}

function inventory_set_item(int $productId, ?int $stock, ?int $min, int $userId, string $note = ''): void
{
    ensure_update_schema();
    $pdo = db();
    $minValue = $min ?? 0;
    $stmt = $pdo->prepare(
        'INSERT INTO inventory_items (product_id, stock, min_stock)
         VALUES (:product_id, :stock, :min_stock)
         ON DUPLICATE KEY UPDATE stock = VALUES(stock), min_stock = VALUES(min_stock)'
    );
    $stmt->execute([
        ':product_id' => $productId,
        ':stock' => $stock,
        ':min_stock' => $minValue,
    ]);

    $logStmt = $pdo->prepare(
        'INSERT INTO inventory_logs (product_id, delta, stock, min_stock, reason, ref, note, user_id)
         VALUES (:product_id, :delta, :stock, :min_stock, :reason, :ref, :note, :user_id)'
    );
    $logStmt->execute([
        ':product_id' => $productId,
        ':delta' => null,
        ':stock' => $stock,
        ':min_stock' => $minValue,
        ':reason' => 'set',
        ':ref' => null,
        ':note' => $note,
        ':user_id' => $userId,
    ]);
}

function inventory_adjust(int $productId, int $delta, string $reason, string $ref, int $userId, string $note = ''): void
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare('SELECT stock, min_stock FROM inventory_items WHERE product_id = :product_id LIMIT 1 FOR UPDATE');
    $stmt->execute([':product_id' => $productId]);
    $row = $stmt->fetch();

    $current = $row ? $row['stock'] : null;
    $minStock = $row ? (int) $row['min_stock'] : 0;
    if ($current === null) {
        $current = 0;
    }
    $newStock = (int) $current + $delta;

    $upsert = $pdo->prepare(
        'INSERT INTO inventory_items (product_id, stock, min_stock)
         VALUES (:product_id, :stock, :min_stock)
         ON DUPLICATE KEY UPDATE stock = VALUES(stock), min_stock = VALUES(min_stock)'
    );
    $upsert->execute([
        ':product_id' => $productId,
        ':stock' => $newStock,
        ':min_stock' => $minStock,
    ]);

    $logStmt = $pdo->prepare(
        'INSERT INTO inventory_logs (product_id, delta, stock, min_stock, reason, ref, note, user_id)
         VALUES (:product_id, :delta, :stock, :min_stock, :reason, :ref, :note, :user_id)'
    );
    $logStmt->execute([
        ':product_id' => $productId,
        ':delta' => $delta,
        ':stock' => $newStock,
        ':min_stock' => $minStock,
        ':reason' => $reason,
        ':ref' => $ref,
        ':note' => $note,
        ':user_id' => $userId,
    ]);
}

function inventory_get_all(): array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->query('SELECT product_id, stock, min_stock FROM inventory_items');
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[(int) $row['product_id']] = [
            'stock' => $row['stock'] === null ? null : (int) $row['stock'],
            'min' => (int) $row['min_stock'],
        ];
    }
    return $items;
}

function inventory_low_stock(array $products): array
{
    $items = inventory_get_all();
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
