<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/db_migration_helper.php';

class Customer
{
    public static function all(PDO $pdo): array
    {
        self::ensureSchema();
        $stmt = $pdo->query(
            'SELECT id, name, phone, email, address, is_active, points, total_spent, total_visits, last_transaction_at, created_at
             FROM customers
             ORDER BY name ASC'
        );
        return $stmt->fetchAll();
    }

    public static function allActive(PDO $pdo): array
    {
        self::ensureSchema();
        $stmt = $pdo->query(
            'SELECT id, name, phone, points
             FROM customers
             WHERE is_active = 1
             ORDER BY name ASC'
        );
        return $stmt->fetchAll();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        self::ensureSchema();
        $stmt = $pdo->prepare(
            'SELECT id, name, phone, email, address, is_active, points, total_spent, total_visits, last_transaction_at, created_at
             FROM customers
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(PDO $pdo, array $data): int
    {
        self::ensureSchema();
        $stmt = $pdo->prepare(
            'INSERT INTO customers (name, phone, email, address, is_active)
             VALUES (:name, :phone, :email, :address, :is_active)'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'] ?: null,
            ':email' => $data['email'] ?: null,
            ':address' => $data['address'] ?: null,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
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
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'] ?: null,
            ':email' => $data['email'] ?: null,
            ':address' => $data['address'] ?: null,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => $id,
        ]);
    }

    public static function delete(PDO $pdo, int $id): void
    {
        self::ensureSchema();
        $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
        $stmt->execute([':id' => $id]);
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
             WHERE id = :id AND is_active = 1'
        );
        $update->execute([
            ':points' => $points,
            ':amount' => $amount,
            ':time' => date('Y-m-d H:i:s'),
            ':id' => $customerId,
        ]);

        if ($update->rowCount() > 0) {
            $log = $pdo->prepare(
                'INSERT INTO customer_point_logs (customer_id, transaction_id, points_delta, amount, note)
                 VALUES (:customer_id, :transaction_id, :points_delta, :amount, :note)'
            );
            $log->execute([
                ':customer_id' => $customerId,
                ':transaction_id' => $transactionId,
                ':points_delta' => $points,
                ':amount' => $amount,
                ':note' => 'POS transaction',
            ]);
        }

        return $points;
    }

    private static function ensureSchema(): void
    {
        ensure_update_schema();
    }
}

