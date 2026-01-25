<?php

declare(strict_types=1);

class Payment
{
    public static function create(PDO $pdo, int $transactionId, string $method, float $amount, ?string $proof): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO payments (transaction_id, method, amount, proof)
             VALUES (:transaction_id, :method, :amount, :proof)'
        );
        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':method' => $method,
            ':amount' => $amount,
            ':proof' => $proof,
        ]);
    }
}
