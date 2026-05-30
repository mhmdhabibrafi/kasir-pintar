<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/db_migration_helper.php';
require_once __DIR__ . '/../helpers/tenant_helper.php';

class Customer
{
    public static function all(PDO $pdo): array
    {
        self::ensureSchema();
        $where = tenant_where_clause($pdo, 'customers', 'customers');
        $stmt = $where !== '' ? $pdo->prepare(
            'SELECT id, name, phone, email, address, is_active, points, total_spent, total_visits, last_transaction_at, created_at
             FROM customers
             ' . $where . '
             ORDER BY name ASC'
        ) : $pdo->query(
            'SELECT id, name, phone, email, address, is_active, points, total_spent, total_visits, last_transaction_at, created_at
             FROM customers
             ORDER BY name ASC'
        );
        if ($where !== '' && $stmt instanceof PDOStatement) {
            $stmt->execute(tenant_bind([], $pdo));
        }
        return $stmt->fetchAll();
    }

    public static function allActive(PDO $pdo): array
    {
        self::ensureSchema();
        $tenantWhere = tenant_where_clause($pdo, 'customers', 'customers', 'AND');
        $stmt = $pdo->prepare(
            'SELECT id, name, phone, points
             FROM customers
             WHERE is_active = 1' . $tenantWhere . '
             ORDER BY name ASC'
        );
        $stmt->execute(tenant_bind([], $pdo));
        return $stmt->fetchAll();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        self::ensureSchema();
        $stmt = $pdo->prepare(
            'SELECT id, name, phone, email, address, is_active, points, total_spent, total_visits, last_transaction_at, created_at
             FROM customers
             WHERE id = :id' . tenant_where_clause($pdo, 'customers', 'customers', 'AND') . '
             LIMIT 1'
        );
        $stmt->execute(tenant_bind([':id' => $id], $pdo));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(PDO $pdo, array $data): int
    {
        self::ensureSchema();
        $data = tenant_apply_insert_store($pdo, 'customers', [
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'address' => $data['address'] ?: null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $stmt = $pdo->prepare('INSERT INTO customers (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    }

    public static function update(PDO $pdo, int $id, array $data): void
    {
        self::ensureSchema();
        $stmt = $pdo->prepare(
            'UPDATE customers
             SET name = :name,
                 phone = :phone,
                 email = :email,
                 address = :address,
                 is_active = :is_active
             WHERE id = :id' . tenant_where_clause($pdo, 'customers', 'customers', 'AND')
        );
        $stmt->execute(tenant_bind([
            ':name' => $data['name'],
            ':phone' => $data['phone'] ?: null,
            ':email' => $data['email'] ?: null,
            ':address' => $data['address'] ?: null,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => $id,
        ], $pdo));
    }

    public static function delete(PDO $pdo, int $id): void
    {
        self::ensureSchema();
        $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id' . tenant_where_clause($pdo, 'customers', 'customers', 'AND'));
        $stmt->execute(tenant_bind([':id' => $id], $pdo));
    }

    public static function phoneExists(PDO $pdo, string $phone, ?int $excludeId = null): bool
    {
        self::ensureSchema();
        $phone = trim($phone);
        if ($phone === '') {
            return false;
        }
        $sql = 'SELECT COUNT(*) FROM customers WHERE phone = :phone';
        $params = [':phone' => $phone];
        $sql .= tenant_where_clause($pdo, 'customers', 'customers', 'AND');
        $params = tenant_bind($params, $pdo);
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function recordTransaction(PDO $pdo, int $customerId, float $amount, int $transactionId): int
    {
        self::ensureSchema();
        $points = max(0, (int) floor($amount / 10000));
        $update = $pdo->prepare(
            'UPDATE customers
             SET points = points + :points,
                 total_spent = total_spent + :amount,
                 total_visits = total_visits + 1,
                 last_transaction_at = :time
             WHERE id = :id AND is_active = 1' . tenant_where_clause($pdo, 'customers', 'customers', 'AND')
        );
        $update->execute(tenant_bind([
            ':points' => $points,
            ':amount' => $amount,
            ':time' => date('Y-m-d H:i:s'),
            ':id' => $customerId,
        ], $pdo));

        if ($update->rowCount() > 0) {
            $log = $pdo->prepare(
                'INSERT INTO customer_point_logs (customer_id, transaction_id, points_delta, amount, note' . (tenant_table_has_column($pdo, 'customer_point_logs', 'store_id') ? ', store_id' : '') . ')
                 VALUES (:customer_id, :transaction_id, :points_delta, :amount, :note' . (tenant_table_has_column($pdo, 'customer_point_logs', 'store_id') ? ', :store_id' : '') . ')'
            );
            $logParams = [
                ':customer_id' => $customerId,
                ':transaction_id' => $transactionId,
                ':points_delta' => $points,
                ':amount' => $amount,
                ':note' => 'POS transaction',
            ];
            if (tenant_table_has_column($pdo, 'customer_point_logs', 'store_id')) {
                $logParams[':store_id'] = tenant_active_store_id($pdo);
            }
            $log->execute($logParams);
        }

        return $points;
    }

    public static function deleteTransactionLogs(PDO $pdo, int $transactionId): void
    {
        self::ensureSchema();

        $customerIdsStmt = $pdo->prepare(
            'SELECT DISTINCT customer_id
             FROM customer_point_logs
             WHERE transaction_id = :transaction_id' . tenant_where_clause($pdo, 'customer_point_logs', 'customer_point_logs', 'AND')
        );
        $customerIdsStmt->execute(tenant_bind([':transaction_id' => $transactionId], $pdo));
        $customerIds = array_map('intval', $customerIdsStmt->fetchAll(PDO::FETCH_COLUMN));

        $deleteStmt = $pdo->prepare('DELETE FROM customer_point_logs WHERE transaction_id = :transaction_id' . tenant_where_clause($pdo, 'customer_point_logs', 'customer_point_logs', 'AND'));
        $deleteStmt->execute(tenant_bind([':transaction_id' => $transactionId], $pdo));

        foreach ($customerIds as $customerId) {
            if ($customerId > 0) {
                self::recalculateAggregates($pdo, $customerId);
            }
        }
    }

    public static function recalculateAggregates(PDO $pdo, int $customerId): void
    {
        self::ensureSchema();

        $summaryStmt = $pdo->prepare(
            'SELECT
                COALESCE(SUM(points_delta), 0) AS points,
                COALESCE(SUM(amount), 0) AS total_spent,
                COUNT(*) AS total_visits,
                MAX(created_at) AS last_transaction_at
             FROM customer_point_logs
             WHERE customer_id = :customer_id' . tenant_where_clause($pdo, 'customer_point_logs', 'customer_point_logs', 'AND')
        );
        $summaryStmt->execute(tenant_bind([':customer_id' => $customerId], $pdo));
        $summary = $summaryStmt->fetch() ?: [];

        $updateStmt = $pdo->prepare(
            'UPDATE customers
             SET points = :points,
                 total_spent = :total_spent,
                 total_visits = :total_visits,
                 last_transaction_at = :last_transaction_at
             WHERE id = :customer_id' . tenant_where_clause($pdo, 'customers', 'customers', 'AND')
        );
        $updateStmt->execute(tenant_bind([
            ':points' => max(0, (int) ($summary['points'] ?? 0)),
            ':total_spent' => max(0, (float) ($summary['total_spent'] ?? 0)),
            ':total_visits' => max(0, (int) ($summary['total_visits'] ?? 0)),
            ':last_transaction_at' => ($summary['last_transaction_at'] ?? null) ?: null,
            ':customer_id' => $customerId,
        ], $pdo));
    }

    private static function ensureSchema(): void
    {
        ensure_update_schema();
    }
}
