<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_migration_helper.php';
require_once __DIR__ . '/transaction_meta_helper.php';
require_once __DIR__ . '/refund_helper.php';

function shift_movements_by_shift_ids(array $shiftIds): array
{
    if (empty($shiftIds)) {
        return [];
    }
    ensure_update_schema();
    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($shiftIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT shift_id, created_at, movement_type, amount, note
         FROM shift_movements
         WHERE shift_id IN (' . $placeholders . ')
         ORDER BY created_at ASC'
    );
    $stmt->execute(array_values($shiftIds));
    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $shiftId = (int) $row['shift_id'];
        if (!isset($grouped[$shiftId])) {
            $grouped[$shiftId] = [];
        }
        $grouped[$shiftId][] = [
            'time' => $row['created_at'],
            'type' => $row['movement_type'],
            'amount' => (float) $row['amount'],
            'note' => $row['note'] ?? '',
        ];
    }
    return $grouped;
}

function shift_map_row(array $row, array $movements): array
{
    $shift = [
        'shift_id' => (string) $row['shift_code'],
        'user_id' => (int) $row['user_id'],
        'user_name' => (string) $row['user_name'],
        'opened_at' => $row['opened_at'],
        'opening_cash' => (float) $row['opening_cash'],
        'note' => $row['note'] ?? '',
        'movements' => $movements,
    ];
    if (!empty($row['closed_at'])) {
        $shift['closed_at'] = $row['closed_at'];
        $shift['closing_cash'] = isset($row['closing_cash']) ? (float) $row['closing_cash'] : null;
        $shift['close_note'] = $row['close_note'] ?? '';
    }
    return $shift;
}

function shift_store(): array
{
    ensure_update_schema();
    $pdo = db();
    $active = [];
    $activeRows = $pdo->query('SELECT * FROM shifts WHERE closed_at IS NULL ORDER BY opened_at ASC')->fetchAll();
    $activeIds = array_map(static fn ($row) => (int) $row['id'], $activeRows);
    $activeMovements = shift_movements_by_shift_ids($activeIds);
    foreach ($activeRows as $row) {
        $shift = shift_map_row($row, $activeMovements[(int) $row['id']] ?? []);
        $active[(string) $shift['user_id']] = $shift;
    }

    $historyRows = $pdo->query(
        'SELECT * FROM shifts WHERE closed_at IS NOT NULL ORDER BY closed_at DESC LIMIT 200'
    )->fetchAll();
    $historyIds = array_map(static fn ($row) => (int) $row['id'], $historyRows);
    $historyMovements = shift_movements_by_shift_ids($historyIds);
    $history = [];
    foreach ($historyRows as $row) {
        $shift = shift_map_row($row, $historyMovements[(int) $row['id']] ?? []);
        $shift['summary'] = shift_summary((string) $shift['shift_id']);
        $history[] = $shift;
    }
    $history = array_reverse($history);

    return ['active' => $active, 'history' => $history, 'updated_at' => date('Y-m-d H:i:s')];
}

function shift_save(array $store): void
{
    // Deprecated: shifts now stored in database.
}

function shift_get_active(int $userId): ?array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM shifts WHERE user_id = :user_id AND closed_at IS NULL ORDER BY opened_at DESC LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $movements = shift_movements_by_shift_ids([(int) $row['id']]);
    return shift_map_row($row, $movements[(int) $row['id']] ?? []);
}

function shift_generate_code(PDO $pdo): string
{
    do {
        $code = 'S' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT 1 FROM shifts WHERE shift_code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $exists = (bool) $stmt->fetchColumn();
    } while ($exists);
    return $code;
}

function shift_open(int $userId, string $userName, float $openingCash, string $note = ''): array
{
    $active = shift_get_active($userId);
    if ($active) {
        return $active;
    }

    ensure_update_schema();
    $pdo = db();
    $shiftCode = shift_generate_code($pdo);
    $openedAt = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO shifts (shift_code, user_id, user_name, opened_at, opening_cash, note)
         VALUES (:shift_code, :user_id, :user_name, :opened_at, :opening_cash, :note)'
    );
    $stmt->execute([
        ':shift_code' => $shiftCode,
        ':user_id' => $userId,
        ':user_name' => $userName,
        ':opened_at' => $openedAt,
        ':opening_cash' => $openingCash,
        ':note' => $note,
    ]);

    return [
        'shift_id' => $shiftCode,
        'user_id' => $userId,
        'user_name' => $userName,
        'opened_at' => $openedAt,
        'opening_cash' => $openingCash,
        'note' => $note,
        'movements' => [],
    ];
}

function shift_add_movement(int $userId, string $type, float $amount, string $note = ''): void
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id FROM shifts WHERE user_id = :user_id AND closed_at IS NULL ORDER BY opened_at DESC LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $shiftId = $stmt->fetchColumn();
    if (!$shiftId) {
        return;
    }
    $insert = $pdo->prepare(
        'INSERT INTO shift_movements (shift_id, movement_type, amount, note)
         VALUES (:shift_id, :movement_type, :amount, :note)'
    );
    $insert->execute([
        ':shift_id' => (int) $shiftId,
        ':movement_type' => $type,
        ':amount' => $amount,
        ':note' => $note,
    ]);
}

function shift_close(int $userId, float $closingCash, string $note = ''): ?array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM shifts WHERE user_id = :user_id AND closed_at IS NULL ORDER BY opened_at DESC LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $closedAt = date('Y-m-d H:i:s');
    $update = $pdo->prepare(
        'UPDATE shifts
         SET closed_at = :closed_at, closing_cash = :closing_cash, close_note = :close_note
         WHERE id = :id'
    );
    $update->execute([
        ':closed_at' => $closedAt,
        ':closing_cash' => $closingCash,
        ':close_note' => $note,
        ':id' => (int) $row['id'],
    ]);

    $movements = shift_movements_by_shift_ids([(int) $row['id']]);
    $shift = shift_map_row(
        array_merge($row, ['closed_at' => $closedAt, 'closing_cash' => $closingCash, 'close_note' => $note]),
        $movements[(int) $row['id']] ?? []
    );
    $shift['summary'] = shift_summary((string) $shift['shift_id']);

    return $shift;
}

function shift_summary(string $shiftId): array
{
    ensure_update_schema();
    $summary = [
        'transactions' => 0,
        'sales_cash' => 0.0,
        'sales_qris' => 0.0,
        'gross_total' => 0.0,
    ];
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS transactions,
                COALESCE(SUM(grand_total), 0) AS gross_total,
                COALESCE(SUM(CASE WHEN payment_method = "cash" THEN grand_total ELSE 0 END), 0) AS sales_cash,
                COALESCE(SUM(CASE WHEN payment_method = "qris" THEN grand_total ELSE 0 END), 0) AS sales_qris
         FROM transaction_meta
         WHERE shift_code = :shift_code'
    );
    $stmt->execute([':shift_code' => $shiftId]);
    $row = $stmt->fetch();
    if ($row) {
        $summary['transactions'] = (int) ($row['transactions'] ?? 0);
        $summary['gross_total'] = (float) ($row['gross_total'] ?? 0);
        $summary['sales_cash'] = (float) ($row['sales_cash'] ?? 0);
        $summary['sales_qris'] = (float) ($row['sales_qris'] ?? 0);
    }

    $refunds = refund_list(['shift_id' => $shiftId]);
    $refundTotal = 0.0;
    $refundCash = 0.0;
    foreach ($refunds as $refund) {
        $amount = (float) ($refund['amount'] ?? 0);
        $refundTotal += $amount;
        if (strtolower((string) ($refund['method'] ?? '')) === 'cash') {
            $refundCash += $amount;
        }
    }
    $summary['refund_total'] = $refundTotal;
    $summary['refund_cash'] = $refundCash;

    return $summary;
}

function shift_cash_balance(array $shift): float
{
    $opening = (float) ($shift['opening_cash'] ?? 0);
    $movements = $shift['movements'] ?? [];
    $cashIn = 0.0;
    $cashOut = 0.0;
    foreach ($movements as $move) {
        $amount = (float) ($move['amount'] ?? 0);
        if (($move['type'] ?? '') === 'in') {
            $cashIn += $amount;
        } elseif (($move['type'] ?? '') === 'out') {
            $cashOut += $amount;
        }
    }
    $summary = shift_summary((string) ($shift['shift_id'] ?? ''));
    $cashSales = (float) ($summary['sales_cash'] ?? 0);
    $cashRefund = (float) ($summary['refund_cash'] ?? 0);

    return $opening + $cashIn - $cashOut + $cashSales - $cashRefund;
}
