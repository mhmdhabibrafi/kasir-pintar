<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_migration_helper.php';

function refund_store(): array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->query(
        'SELECT refund_code AS id, transaction_id, amount, method, reason, restock, user_id, shift_code AS shift_id, created_at
         FROM refunds
         ORDER BY created_at ASC'
    );
    return ['items' => $stmt->fetchAll(), 'updated_at' => date('Y-m-d H:i:s')];
}

function refund_save(array $store): void
{
    // Deprecated: refunds now stored in database.
}

function refund_add(array $refund): array
{
    ensure_update_schema();
    $pdo = db();
    $refundCode = $refund['id'] ?? ('R' . date('YmdHis') . '_' . bin2hex(random_bytes(2)));
    $createdAt = $refund['created_at'] ?? date('Y-m-d H:i:s');
    $shiftCode = $refund['shift_id'] ?? null;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO refunds (refund_code, transaction_id, amount, method, reason, restock, user_id, shift_code, created_at)
             VALUES (:refund_code, :transaction_id, :amount, :method, :reason, :restock, :user_id, :shift_code, :created_at)'
        );
        $stmt->execute([
            ':refund_code' => $refundCode,
            ':transaction_id' => (int) ($refund['transaction_id'] ?? 0),
            ':amount' => (float) ($refund['amount'] ?? 0),
            ':method' => (string) ($refund['method'] ?? 'cash'),
            ':reason' => (string) ($refund['reason'] ?? ''),
            ':restock' => !empty($refund['restock']) ? 1 : 0,
            ':user_id' => (int) ($refund['user_id'] ?? 0),
            ':shift_code' => $shiftCode !== '' ? $shiftCode : null,
            ':created_at' => $createdAt,
        ]);

        $refundId = (int) $pdo->lastInsertId();
        if (!empty($refund['items']) && is_array($refund['items'])) {
            $itemStmt = $pdo->prepare(
                'INSERT INTO refund_items (refund_id, product_id, qty)
                 VALUES (:refund_id, :product_id, :qty)'
            );
            foreach ($refund['items'] as $item) {
                $itemStmt->execute([
                    ':refund_id' => $refundId,
                    ':product_id' => (int) ($item['product_id'] ?? 0),
                    ':qty' => (int) ($item['qty'] ?? 0),
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $refund['id'] = $refundCode;
    $refund['created_at'] = $createdAt;
    if ($shiftCode !== null && $shiftCode !== '') {
        $refund['shift_id'] = $shiftCode;
    }
    return $refund;
}

function refund_list(array $filters = []): array
{
    ensure_update_schema();
    $pdo = db();
    $start = $filters['start_date'] ?? null;
    $end = $filters['end_date'] ?? null;
    $method = $filters['method'] ?? null;
    $shiftId = $filters['shift_id'] ?? null;
    $transactionId = $filters['transaction_id'] ?? null;

    $conditions = [];
    $params = [];
    if ($start) {
        $conditions[] = 'DATE(created_at) >= :start_date';
        $params[':start_date'] = $start;
    }
    if ($end) {
        $conditions[] = 'DATE(created_at) <= :end_date';
        $params[':end_date'] = $end;
    }
    if ($method && $method !== 'all') {
        $conditions[] = 'method = :method';
        $params[':method'] = $method;
    }
    if ($shiftId) {
        $conditions[] = 'shift_code = :shift_code';
        $params[':shift_code'] = (string) $shiftId;
    }
    if ($transactionId) {
        $conditions[] = 'transaction_id = :transaction_id';
        $params[':transaction_id'] = (int) $transactionId;
    }

    $sql = 'SELECT refund_code AS id, transaction_id, amount, method, reason, restock, user_id,
                   shift_code AS shift_id, created_at
            FROM refunds';
    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    foreach ($items as $index => $item) {
        $items[$index]['restock'] = !empty($item['restock']);
    }
    return $items;
}

function refund_sum_by_date_range(string $startDate, string $endDate, ?string $method = null): float
{
    ensure_update_schema();
    $pdo = db();
    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ];
    $sql = 'SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE DATE(created_at) BETWEEN :start_date AND :end_date';
    if ($method && $method !== 'all') {
        $sql .= ' AND method = :method';
        $params[':method'] = $method;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float) $stmt->fetchColumn();
}

function refund_group_by_date(string $startDate, string $endDate): array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT DATE(created_at) AS day, COALESCE(SUM(amount), 0) AS total
         FROM refunds
         WHERE DATE(created_at) BETWEEN :start_date AND :end_date
         GROUP BY DATE(created_at)'
    );
    $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $day = (string) $row['day'];
        if ($day !== '') {
            $grouped[$day] = (float) $row['total'];
        }
    }
    return $grouped;
}
