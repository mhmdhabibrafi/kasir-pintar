<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_migration_helper.php';
require_once __DIR__ . '/tenant_helper.php';

function transaction_meta_store(): array
{
    ensure_update_schema();
    $pdo = db();
    $where = tenant_where_clause($pdo, 'transaction_meta', 'transaction_meta');
    $stmt = $where !== '' ? $pdo->prepare('SELECT transaction_id, meta_json FROM transaction_meta' . $where) : $pdo->query('SELECT transaction_id, meta_json FROM transaction_meta');
    if ($where !== '' && $stmt instanceof PDOStatement) {
        $stmt->execute(tenant_bind([], $pdo));
    }
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $meta = json_decode((string) $row['meta_json'], true);
        if (!is_array($meta)) {
            $meta = [];
        }
        $items[(string) $row['transaction_id']] = $meta;
    }
    return ['items' => $items, 'updated_at' => date('Y-m-d H:i:s')];
}

function transaction_meta_get(int $transactionId): ?array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare('SELECT meta_json FROM transaction_meta WHERE transaction_id = :id' . tenant_where_clause($pdo, 'transaction_meta', 'transaction_meta', 'AND') . ' LIMIT 1');
    $stmt->execute(tenant_bind([':id' => $transactionId], $pdo));
    $metaJson = $stmt->fetchColumn();
    if ($metaJson === false) {
        return null;
    }
    $meta = json_decode((string) $metaJson, true);
    return is_array($meta) ? $meta : null;
}

function transaction_meta_bulk(array $ids): array
{
    if (empty($ids)) {
        return [];
    }
    ensure_update_schema();
    $pdo = db();
    $params = [];
    $placeholders = [];
    foreach (array_values($ids) as $index => $id) {
        $key = ':transaction_id_' . $index;
        $placeholders[] = $key;
        $params[$key] = (int) $id;
    }
    $stmt = $pdo->prepare('SELECT transaction_id, meta_json FROM transaction_meta WHERE transaction_id IN (' . implode(',', $placeholders) . ')' . tenant_where_clause($pdo, 'transaction_meta', 'transaction_meta', 'AND'));
    $stmt->execute(tenant_bind($params, $pdo));
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $meta = json_decode((string) $row['meta_json'], true);
        if (!is_array($meta)) {
            $meta = [];
        }
        $result[(string) $row['transaction_id']] = $meta;
    }
    return $result;
}

function transaction_meta_set(int $transactionId, array $meta): void
{
    ensure_update_schema();
    $pdo = db();
    $shiftCode = (string) ($meta['shift_id'] ?? '');
    $paymentMethod = (string) ($meta['payment_method'] ?? '');
    $grandTotal = (float) ($meta['grand_total'] ?? ($meta['total'] ?? 0));
    $totalBeforeRounding = (float) ($meta['total_before_rounding'] ?? 0);

    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($metaJson === false) {
        $metaJson = json_encode(['error' => 'encode_failed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $hasStore = tenant_table_has_column($pdo, 'transaction_meta', 'store_id');
    $stmt = $pdo->prepare(
        'INSERT INTO transaction_meta
            (transaction_id, shift_code, payment_method, grand_total, total_before_rounding, meta_json' . ($hasStore ? ', store_id' : '') . ')
         VALUES
            (:transaction_id, :shift_code, :payment_method, :grand_total, :total_before_rounding, :meta_json' . ($hasStore ? ', :store_id' : '') . ')
         ON DUPLICATE KEY UPDATE
            shift_code = VALUES(shift_code),
            payment_method = VALUES(payment_method),
            grand_total = VALUES(grand_total),
            total_before_rounding = VALUES(total_before_rounding),
            meta_json = VALUES(meta_json)'
    );
    $params = [
        ':transaction_id' => $transactionId,
        ':shift_code' => $shiftCode !== '' ? $shiftCode : null,
        ':payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
        ':grand_total' => $grandTotal,
        ':total_before_rounding' => $totalBeforeRounding,
        ':meta_json' => $metaJson,
    ];
    if ($hasStore) {
        $params[':store_id'] = tenant_active_store_id($pdo);
    }
    $stmt->execute($params);
}
