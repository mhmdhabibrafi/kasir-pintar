<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_migration_helper.php';
require_once __DIR__ . '/transaction_meta_helper.php';
require_once __DIR__ . '/refund_helper.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/tenant_helper.php';

$telegramHelperPath = __DIR__ . '/telegram_helper.php';
if (is_file($telegramHelperPath)) {
    require_once $telegramHelperPath;
}

function shift_empty_summary(): array
{
    return [
        'transactions' => 0,
        'sales_cash' => 0.0,
        'sales_qris' => 0.0,
        'gross_total' => 0.0,
        'refund_total' => 0.0,
        'refund_cash' => 0.0,
        'refund_qris' => 0.0,
    ];
}

function shift_movements_by_shift_ids(array $shiftIds): array
{
    if (empty($shiftIds)) {
        return [];
    }
    ensure_update_schema();
    $pdo = db();
    $params = [];
    $placeholders = [];
    foreach (array_values($shiftIds) as $index => $shiftId) {
        $key = ':shift_id_' . $index;
        $placeholders[] = $key;
        $params[$key] = (int) $shiftId;
    }
    $placeholders = implode(',', $placeholders);
    $tenantCondition = tenant_filter_sql($pdo, 'shift_movements', 'shift_movements');
    $hasTenant = $tenantCondition !== '' && tenant_user_store_id(null, $pdo) !== null;
    $stmt = $pdo->prepare(
        'SELECT shift_id, created_at, movement_type, amount, note
         FROM shift_movements
         WHERE shift_id IN (' . $placeholders . ')' . ($hasTenant ? ' AND ' . $tenantCondition : '') . '
         ORDER BY created_at ASC'
    );
    if ($hasTenant) {
        $params[':tenant_store_id'] = tenant_active_store_id($pdo);
    }
    $stmt->execute($params);
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

function shift_active_list(bool $includeMovements = true): array
{
    ensure_update_schema();
    $pdo = db();
    $activeStmt = $pdo->prepare('SELECT * FROM shifts WHERE closed_at IS NULL' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND') . ' ORDER BY opened_at ASC');
    $activeStmt->execute(tenant_bind([], $pdo));
    $activeRows = $activeStmt->fetchAll();
    $activeIds = array_map(static fn ($row) => (int) $row['id'], $activeRows);
    $activeMovements = $includeMovements ? shift_movements_by_shift_ids($activeIds) : [];
    $active = [];
    foreach ($activeRows as $row) {
        $active[] = shift_map_row($row, $activeMovements[(int) $row['id']] ?? []);
    }

    return $active;
}

function shift_active_count(): int
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shifts WHERE closed_at IS NULL' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND'));
    $stmt->execute(tenant_bind([], $pdo));

    return (int) $stmt->fetchColumn();
}

function shift_recent_history(int $limit = 60, ?int $userId = null): array
{
    ensure_update_schema();
    $pdo = db();
    $limit = max(1, min(200, $limit));
    $params = [];
    $sql = 'SELECT * FROM shifts WHERE closed_at IS NOT NULL';
    if ($userId !== null && $userId > 0) {
        $sql .= ' AND user_id = :user_id';
        $params[':user_id'] = $userId;
    }
    $sql .= tenant_where_clause($pdo, 'shifts', 'shifts', 'AND');
    $sql .= ' ORDER BY closed_at DESC LIMIT ' . $limit;

    $historyStmt = $pdo->prepare($sql);
    $historyStmt->execute(tenant_bind($params, $pdo));
    $historyRows = $historyStmt->fetchAll();
    $historyIds = array_map(static fn ($row) => (int) $row['id'], $historyRows);
    $historyMovements = shift_movements_by_shift_ids($historyIds);
    $summaryMap = shift_summaries(array_map(static fn ($row) => (string) $row['shift_code'], $historyRows));
    $history = [];
    foreach ($historyRows as $row) {
        $shift = shift_map_row($row, $historyMovements[(int) $row['id']] ?? []);
        $shift['summary'] = $summaryMap[(string) $shift['shift_id']] ?? shift_empty_summary();
        $history[] = $shift;
    }

    return $history;
}

function shift_store(): array
{
    $active = [];
    foreach (shift_active_list(true) as $shift) {
        $active[(string) $shift['user_id']] = $shift;
    }

    return ['active' => $active, 'history' => shift_recent_history(200), 'updated_at' => date('Y-m-d H:i:s')];
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
        'SELECT * FROM shifts WHERE user_id = :user_id AND closed_at IS NULL' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND') . ' ORDER BY opened_at DESC LIMIT 1'
    );
    $stmt->execute(tenant_bind([':user_id' => $userId], $pdo));
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
        $stmt = $pdo->prepare('SELECT 1 FROM shifts WHERE shift_code = :code' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND') . ' LIMIT 1');
        $stmt->execute(tenant_bind([':code' => $code], $pdo));
        $exists = (bool) $stmt->fetchColumn();
    } while ($exists);
    return $code;
}

function shift_suggest_opening_cash(int $userId): float
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT closing_cash
         FROM shifts
         WHERE user_id = :user_id
           AND closed_at IS NOT NULL
           AND closing_cash IS NOT NULL' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND') . '
         ORDER BY closed_at DESC
         LIMIT 1'
    );
    $stmt->execute(tenant_bind([':user_id' => $userId], $pdo));
    $closingCash = $stmt->fetchColumn();

    return $closingCash === false ? 0.0 : max(0.0, (float) $closingCash);
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
    $hasStore = tenant_table_has_column($pdo, 'shifts', 'store_id');
    $stmt = $pdo->prepare(
        'INSERT INTO shifts (shift_code, user_id, user_name, opened_at, opening_cash, note' . ($hasStore ? ', store_id' : '') . ')
         VALUES (:shift_code, :user_id, :user_name, :opened_at, :opening_cash, :note' . ($hasStore ? ', :store_id' : '') . ')'
    );
    $params = [
        ':shift_code' => $shiftCode,
        ':user_id' => $userId,
        ':user_name' => $userName,
        ':opened_at' => $openedAt,
        ':opening_cash' => $openingCash,
        ':note' => $note,
    ];
    if ($hasStore) {
        $params[':store_id'] = tenant_active_store_id($pdo);
    }
    $stmt->execute($params);

    audit_log('shift_opened', [
        'shift_id' => $shiftCode,
        'user_id' => $userId,
        'user_name' => $userName,
        'opening_cash' => $openingCash,
        'note' => $note,
    ]);
    if (function_exists('telegram_notify_shift')) {
        telegram_notify_shift('opened', [
            'shift_id' => $shiftCode,
            'user_id' => $userId,
            'user_name' => $userName,
            'opening_cash' => $openingCash,
            'note' => $note,
        ]);
    }

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
    if (!in_array($type, ['in', 'out'], true) || $amount <= 0) {
        return;
    }
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id, shift_code FROM shifts WHERE user_id = :user_id AND closed_at IS NULL' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND') . ' ORDER BY opened_at DESC LIMIT 1'
    );
    $stmt->execute(tenant_bind([':user_id' => $userId], $pdo));
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $shiftId = (int) $row['id'];
    $shiftCode = (string) ($row['shift_code'] ?? '');
    $hasStore = tenant_table_has_column($pdo, 'shift_movements', 'store_id');
    $insert = $pdo->prepare(
        'INSERT INTO shift_movements (shift_id, movement_type, amount, note' . ($hasStore ? ', store_id' : '') . ')
         VALUES (:shift_id, :movement_type, :amount, :note' . ($hasStore ? ', :store_id' : '') . ')'
    );
    $params = [
        ':shift_id' => $shiftId,
        ':movement_type' => $type,
        ':amount' => $amount,
        ':note' => $note,
    ];
    if ($hasStore) {
        $params[':store_id'] = tenant_active_store_id($pdo);
    }
    $insert->execute($params);
    audit_log('shift_cash_movement', [
        'shift_id' => $shiftCode,
        'user_id' => $userId,
        'type' => $type,
        'amount' => $amount,
        'note' => $note,
    ]);
    if (function_exists('telegram_notify_shift')) {
        telegram_notify_shift('movement', [
            'shift_id' => $shiftCode,
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
        ]);
    }
}

function shift_add_movement_by_code(string $shiftCode, string $type, float $amount, string $note = ''): bool
{
    if ($shiftCode === '' || !in_array($type, ['in', 'out'], true) || $amount <= 0) {
        return false;
    }
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id FROM shifts WHERE shift_code = :code AND closed_at IS NULL' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND') . ' LIMIT 1'
    );
    $stmt->execute(tenant_bind([':code' => $shiftCode], $pdo));
    $shiftId = $stmt->fetchColumn();
    if (!$shiftId) {
        return false;
    }
    $hasStore = tenant_table_has_column($pdo, 'shift_movements', 'store_id');
    $insert = $pdo->prepare(
        'INSERT INTO shift_movements (shift_id, movement_type, amount, note' . ($hasStore ? ', store_id' : '') . ')
         VALUES (:shift_id, :movement_type, :amount, :note' . ($hasStore ? ', :store_id' : '') . ')'
    );
    $params = [
        ':shift_id' => (int) $shiftId,
        ':movement_type' => $type,
        ':amount' => $amount,
        ':note' => $note,
    ];
    if ($hasStore) {
        $params[':store_id'] = tenant_active_store_id($pdo);
    }
    $insert->execute($params);
    audit_log('shift_cash_movement', [
        'shift_id' => $shiftCode,
        'type' => $type,
        'amount' => $amount,
        'note' => $note,
    ]);
    if (function_exists('telegram_notify_shift')) {
        telegram_notify_shift('movement', [
            'shift_id' => $shiftCode,
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
        ]);
    }
    return true;
}

function shift_close(int $userId, float $closingCash, string $note = ''): ?array
{
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM shifts WHERE user_id = :user_id AND closed_at IS NULL' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND') . ' ORDER BY opened_at DESC LIMIT 1'
    );
    $stmt->execute(tenant_bind([':user_id' => $userId], $pdo));
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $closedAt = date('Y-m-d H:i:s');
    $update = $pdo->prepare(
        'UPDATE shifts
         SET closed_at = :closed_at, closing_cash = :closing_cash, close_note = :close_note
         WHERE id = :id' . tenant_where_clause($pdo, 'shifts', 'shifts', 'AND')
    );
    $update->execute(tenant_bind([
        ':closed_at' => $closedAt,
        ':closing_cash' => $closingCash,
        ':close_note' => $note,
        ':id' => (int) $row['id'],
    ], $pdo));

    $movements = shift_movements_by_shift_ids([(int) $row['id']]);
    $shift = shift_map_row(
        array_merge($row, ['closed_at' => $closedAt, 'closing_cash' => $closingCash, 'close_note' => $note]),
        $movements[(int) $row['id']] ?? []
    );
    $shift['summary'] = shift_summary((string) $shift['shift_id']);

    audit_log('shift_closed', [
        'shift_id' => $shift['shift_id'] ?? null,
        'user_id' => (int) ($shift['user_id'] ?? 0),
        'closing_cash' => $closingCash,
        'note' => $note,
    ]);
    if (function_exists('telegram_notify_shift')) {
        telegram_notify_shift('closed', [
            'shift_id' => $shift['shift_id'] ?? null,
            'user_id' => (int) ($shift['user_id'] ?? 0),
            'user_name' => (string) ($shift['user_name'] ?? ''),
            'closing_cash' => $closingCash,
            'note' => $note,
        ]);
    }

    return $shift;
}

function shift_summaries(array $shiftIds): array
{
    $codes = [];
    foreach ($shiftIds as $shiftId) {
        $code = trim((string) $shiftId);
        if ($code !== '') {
            $codes[$code] = $code;
        }
    }
    if (empty($codes)) {
        return [];
    }

    ensure_update_schema();
    $pdo = db();
    $params = [];
    $placeholders = [];
    foreach (array_values($codes) as $index => $code) {
        $key = ':shift_code_' . $index;
        $placeholders[] = $key;
        $params[$key] = $code;
    }
    $placeholderSql = implode(',', $placeholders);
    $result = [];
    foreach ($codes as $code) {
        $result[$code] = shift_empty_summary();
    }

    $trxStmt = $pdo->prepare(
        'SELECT shift_code,
                COUNT(*) AS transactions,
                COALESCE(SUM(grand_total), 0) AS gross_total,
                COALESCE(SUM(CASE WHEN payment_method = "cash" THEN grand_total ELSE 0 END), 0) AS sales_cash,
                COALESCE(SUM(CASE WHEN payment_method = "qris" THEN grand_total ELSE 0 END), 0) AS sales_qris
         FROM transaction_meta
         WHERE shift_code IN (' . $placeholderSql . ')' . tenant_where_clause($pdo, 'transaction_meta', 'transaction_meta', 'AND') . '
         GROUP BY shift_code'
    );
    $trxStmt->execute(tenant_bind($params, $pdo));
    foreach ($trxStmt->fetchAll() as $row) {
        $code = (string) ($row['shift_code'] ?? '');
        if ($code === '' || !isset($result[$code])) {
            continue;
        }
        $result[$code]['transactions'] = (int) ($row['transactions'] ?? 0);
        $result[$code]['gross_total'] = (float) ($row['gross_total'] ?? 0);
        $result[$code]['sales_cash'] = (float) ($row['sales_cash'] ?? 0);
        $result[$code]['sales_qris'] = (float) ($row['sales_qris'] ?? 0);
    }

    $refundStmt = $pdo->prepare(
        'SELECT shift_code, method, COALESCE(SUM(amount), 0) AS total
         FROM refunds
         WHERE shift_code IN (' . $placeholderSql . ')' . tenant_where_clause($pdo, 'refunds', 'refunds', 'AND') . '
         GROUP BY shift_code, method'
    );
    $refundStmt->execute(tenant_bind($params, $pdo));
    foreach ($refundStmt->fetchAll() as $row) {
        $code = (string) ($row['shift_code'] ?? '');
        if ($code === '' || !isset($result[$code])) {
            continue;
        }
        $amount = (float) ($row['total'] ?? 0);
        $method = strtolower((string) ($row['method'] ?? ''));
        $result[$code]['refund_total'] += $amount;
        if ($method === 'cash') {
            $result[$code]['refund_cash'] += $amount;
        } elseif ($method === 'qris') {
            $result[$code]['refund_qris'] += $amount;
        }
    }

    return $result;
}

function shift_summary(string $shiftId): array
{
    $summary = shift_summaries([$shiftId]);

    return $summary[$shiftId] ?? shift_empty_summary();
}

function shift_expected_cash(array $shift, ?array $summary = null): float
{
    $opening = (float) ($shift['opening_cash'] ?? 0);
    $movementTotals = shift_movement_totals($shift);
    $summary = $summary ?? ($shift['summary'] ?? null);
    if (!is_array($summary)) {
        $summary = shift_summary((string) ($shift['shift_id'] ?? ''));
    }
    $cashSales = (float) ($summary['sales_cash'] ?? 0);
    $cashRefund = (float) ($summary['refund_cash'] ?? 0);

    return $opening
        + (float) ($movementTotals['cash_in'] ?? 0)
        - (float) ($movementTotals['cash_out'] ?? 0)
        + $cashSales
        - $cashRefund;
}

function shift_cash_balance(array $shift): float
{
    return shift_expected_cash($shift);
}

function shift_movement_totals(array $shift): array
{
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
    return ['cash_in' => $cashIn, 'cash_out' => $cashOut];
}
