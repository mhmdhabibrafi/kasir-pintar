<?php

declare(strict_types=1);

class Transaction
{
    // Get last transaction within date range (with cashier).
    public static function getLastTransaction(PDO $pdo, string $startDate, string $endDate): array
    {
        $stmt = $pdo->prepare(
            'SELECT transactions.id, transactions.created_at, users.name AS cashier
             FROM transactions
             INNER JOIN users ON users.id = transactions.user_id
             WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date
             ORDER BY transactions.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $row = $stmt->fetch();

        return $row ?: [];
    }

    // Get transaction count for a specific date.
    public static function getTransactionCountToday(PDO $pdo, string $date): array
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM transactions WHERE DATE(created_at) = :date');
        $stmt->execute([':date' => $date]);
        $count = (int) $stmt->fetchColumn();

        return ['count' => $count];
    }

    // Get payment totals and percentages within date range.
    public static function getPaymentPercentage(PDO $pdo, string $startDate, string $endDate): array
    {
        $totals = ['cash' => 0.0, 'qris' => 0.0];
        $stmt = $pdo->prepare(
            'SELECT method, COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE DATE(created_at) BETWEEN :start_date AND :end_date
             GROUP BY method'
        );
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        foreach ($stmt->fetchAll() as $row) {
            $method = (string) $row['method'];
            if (isset($totals[$method])) {
                $totals[$method] = (float) $row['total'];
            }
        }

        $grandTotal = max(1.0, $totals['cash'] + $totals['qris']);
        $cashPct = ($totals['cash'] / $grandTotal) * 100;
        $qrisPct = 100 - $cashPct;

        return [
            'cash_total' => $totals['cash'],
            'qris_total' => $totals['qris'],
            'cash_pct' => $cashPct,
            'qris_pct' => $qrisPct,
        ];
    }

    // Get last transaction time within date range.
    public static function getLastTransactionTime(PDO $pdo, string $startDate, string $endDate): array
    {
        $stmt = $pdo->prepare(
            'SELECT MAX(created_at) AS last_time
             FROM transactions
             WHERE DATE(created_at) BETWEEN :start_date AND :end_date'
        );
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $lastTime = $stmt->fetchColumn();

        return ['last_time' => $lastTime ?: null];
    }
    // Get total cup (qty) within date range.
    public static function getTotalCupByDateRange(PDO $pdo, string $startDate, string $endDate): int
    {
        $qtyColumn = self::itemQuantityColumn($pdo) ?? 'quantity';
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(transaction_items.' . $qtyColumn . '), 0)
             FROM transaction_items
             INNER JOIN transactions ON transactions.id = transaction_items.transaction_id
             WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date'
        );
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        return (int) $stmt->fetchColumn();
    }

    // Compare revenue for a date versus the previous day.
    public static function getRevenueComparison(PDO $pdo, string $date): array
    {
        $today = $date;
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(created_at) = :date');
        $stmt->execute([':date' => $today]);
        $todayTotal = (float) $stmt->fetchColumn();

        $stmt->execute([':date' => $yesterday]);
        $yesterdayTotal = (float) $stmt->fetchColumn();

        $diff = $todayTotal - $yesterdayTotal;
        if ($yesterdayTotal > 0) {
            $pct = ($diff / $yesterdayTotal) * 100;
        } else {
            $pct = $todayTotal > 0 ? 100.0 : 0.0;
        }

        return [
            'today' => $todayTotal,
            'yesterday' => $yesterdayTotal,
            'diff' => $diff,
            'pct' => $pct,
            'trend' => $diff >= 0 ? 'up' : 'down',
        ];
    }

    // Get top products by total qty within date range.
    public static function getTopProducts(PDO $pdo, string $startDate, string $endDate, int $limit = 5): array
    {
        $limit = max(1, $limit);
        $qtyColumn = self::itemQuantityColumn($pdo) ?? 'quantity';
        $sql = 'SELECT products.id, products.name, COALESCE(SUM(transaction_items.' . $qtyColumn . '), 0) AS total_qty
                FROM transaction_items
                INNER JOIN transactions ON transactions.id = transaction_items.transaction_id
                INNER JOIN products ON products.id = transaction_items.product_id
                WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date
                GROUP BY products.id, products.name
                ORDER BY total_qty DESC
                LIMIT ' . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        return $stmt->fetchAll();
    }

    // Get payment summary (cash vs qris) within date range.
    public static function getPaymentSummary(PDO $pdo, string $startDate, string $endDate): array
    {
        $summary = ['cash' => 0.0, 'qris' => 0.0];
        $stmt = $pdo->prepare(
            'SELECT method, COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE DATE(created_at) BETWEEN :start_date AND :end_date
             GROUP BY method'
        );
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        foreach ($stmt->fetchAll() as $row) {
            $method = (string) $row['method'];
            if (isset($summary[$method])) {
                $summary[$method] = (float) $row['total'];
            }
        }
        return $summary;
    }

    // Get revenue series per day within date range.
    public static function getRevenueSeries(PDO $pdo, string $startDate, string $endDate): array
    {
        $range = [];
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->setTime(0, 0, 0);
        for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $range[$key] = 0.0;
        }

        $stmt = $pdo->prepare(
            'SELECT DATE(created_at) AS day, COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE DATE(created_at) BETWEEN :start_date AND :end_date
             GROUP BY DATE(created_at)'
        );
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        foreach ($stmt->fetchAll() as $row) {
            $day = (string) $row['day'];
            if (isset($range[$day])) {
                $range[$day] = (float) $row['total'];
            }
        }

        $series = [];
        foreach ($range as $day => $total) {
            $series[] = ['date' => $day, 'label' => date('d/m', strtotime($day)), 'value' => $total];
        }
        return $series;
    }
    public static function totalColumn(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'transactions'
               AND COLUMN_NAME IN ('total_amount', 'total')
             ORDER BY FIELD(COLUMN_NAME, 'total_amount', 'total')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }

    public static function noteColumn(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'transactions'
               AND COLUMN_NAME IN ('note', 'notes', 'catatan')
             ORDER BY FIELD(COLUMN_NAME, 'note', 'notes', 'catatan')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }

    public static function itemQuantityColumn(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'transaction_items'
               AND COLUMN_NAME IN ('quantity', 'qty')
             ORDER BY FIELD(COLUMN_NAME, 'quantity', 'qty')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }

    public static function itemSubtotalColumn(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'transaction_items'
               AND COLUMN_NAME IN ('subtotal', 'sub_total')
             ORDER BY FIELD(COLUMN_NAME, 'subtotal', 'sub_total')
             LIMIT 1"
        );
        $stmt->execute();
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }

    public static function count(PDO $pdo): int
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM transactions');
        return (int) $stmt->fetchColumn();
    }

    public static function create(PDO $pdo, int $userId, float $total, ?string $note = null): int
    {
        $totalColumn = self::totalColumn($pdo) ?? 'total_amount';
        $noteColumn = self::noteColumn($pdo);

        $columns = ['user_id', $totalColumn];
        $placeholders = [':user_id', ':total'];
        $params = [
            ':user_id' => $userId,
            ':total' => $total,
        ];

        if ($noteColumn) {
            $columns[] = $noteColumn;
            $placeholders[] = ':note';
            $params[':note'] = ($note !== null && $note !== '') ? $note : null;
        }

        $sql = 'INSERT INTO transactions (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    }

    public static function addItem(PDO $pdo, int $transactionId, int $productId, int $quantity, float $price, float $subtotal): void
    {
        $qtyColumn = self::itemQuantityColumn($pdo) ?? 'quantity';
        $subtotalColumn = self::itemSubtotalColumn($pdo);

        $columns = ['transaction_id', 'product_id', $qtyColumn, 'price'];
        $params = [
            ':transaction_id' => $transactionId,
            ':product_id' => $productId,
            ':quantity' => $quantity,
            ':price' => $price,
        ];

        if ($subtotalColumn) {
            $columns[] = $subtotalColumn;
            $params[':subtotal'] = $subtotal;
        }

        $placeholders = [':transaction_id', ':product_id', ':quantity', ':price'];
        if ($subtotalColumn) {
            $placeholders[] = ':subtotal';
        }

        $sql = 'INSERT INTO transaction_items (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}
