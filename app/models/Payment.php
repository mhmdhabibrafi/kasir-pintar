<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/tenant_helper.php';

class Payment
{
    public static function create(PDO $pdo, int $transactionId, string $method, float $amount, ?string $proof): void
    {
        $data = tenant_apply_insert_store($pdo, 'payments', [
            'transaction_id' => $transactionId,
            'method' => $method,
            'amount' => $amount,
            'proof' => $proof,
        ]);
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $stmt = $pdo->prepare('INSERT INTO payments (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }
        $stmt->execute($params);
    }
}
