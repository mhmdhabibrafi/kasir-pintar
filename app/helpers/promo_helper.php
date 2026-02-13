<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db_migration_helper.php';

function promo_default_config(): array
{
    return [
        'defaults' => [
            'tax_percent' => 0,
            'service_percent' => 0,
            'rounding_mode' => 'none',
            'rounding_unit' => 100,
        ],
        'vouchers' => [],
        'updated_at' => null,
    ];
}

function promo_get_config(): array
{
    ensure_update_schema();
    $pdo = db();
    $defaults = promo_default_config()['defaults'];
    $stmt = $pdo->query('SELECT tax_percent, service_percent, rounding_mode, rounding_unit, updated_at FROM promo_settings ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch();
    if ($row) {
        $defaults = [
            'tax_percent' => (float) $row['tax_percent'],
            'service_percent' => (float) $row['service_percent'],
            'rounding_mode' => (string) $row['rounding_mode'],
            'rounding_unit' => (int) $row['rounding_unit'],
        ];
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO promo_settings (tax_percent, service_percent, rounding_mode, rounding_unit)
             VALUES (:tax_percent, :service_percent, :rounding_mode, :rounding_unit)'
        );
        $insert->execute([
            ':tax_percent' => $defaults['tax_percent'],
            ':service_percent' => $defaults['service_percent'],
            ':rounding_mode' => $defaults['rounding_mode'],
            ':rounding_unit' => $defaults['rounding_unit'],
        ]);
    }

    $vouchers = [];
    $voucherStmt = $pdo->query(
        'SELECT code, name, type, value, min_total, max_discount, expires, active
         FROM promo_vouchers
         ORDER BY code ASC'
    );
    foreach ($voucherStmt->fetchAll() as $voucher) {
        $vouchers[] = [
            'code' => (string) $voucher['code'],
            'name' => $voucher['name'] ?? '',
            'type' => (string) $voucher['type'],
            'value' => (float) $voucher['value'],
            'min_total' => (float) $voucher['min_total'],
            'max' => (float) $voucher['max_discount'],
            'expires' => $voucher['expires'] ?? '',
            'active' => !empty($voucher['active']),
        ];
    }

    $updatedAt = is_array($row) ? ($row['updated_at'] ?? null) : null;

    return [
        'defaults' => $defaults,
        'vouchers' => $vouchers,
        'updated_at' => $updatedAt,
    ];
}

function promo_save_config(array $config): void
{
    ensure_update_schema();
    $pdo = db();
    $defaults = $config['defaults'] ?? promo_default_config()['defaults'];
    $vouchers = $config['vouchers'] ?? [];

    $pdo->beginTransaction();
    try {
        $existing = $pdo->query('SELECT id FROM promo_settings ORDER BY id ASC LIMIT 1')->fetch();
        if ($existing) {
            $update = $pdo->prepare(
                'UPDATE promo_settings
                 SET tax_percent = :tax_percent,
                     service_percent = :service_percent,
                     rounding_mode = :rounding_mode,
                     rounding_unit = :rounding_unit
                 WHERE id = :id'
            );
            $update->execute([
                ':tax_percent' => $defaults['tax_percent'],
                ':service_percent' => $defaults['service_percent'],
                ':rounding_mode' => $defaults['rounding_mode'],
                ':rounding_unit' => $defaults['rounding_unit'],
                ':id' => (int) $existing['id'],
            ]);
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO promo_settings (tax_percent, service_percent, rounding_mode, rounding_unit)
                 VALUES (:tax_percent, :service_percent, :rounding_mode, :rounding_unit)'
            );
            $insert->execute([
                ':tax_percent' => $defaults['tax_percent'],
                ':service_percent' => $defaults['service_percent'],
                ':rounding_mode' => $defaults['rounding_mode'],
                ':rounding_unit' => $defaults['rounding_unit'],
            ]);
        }

        $codes = [];
        foreach ($vouchers as $voucher) {
            $code = strtoupper(trim((string) ($voucher['code'] ?? '')));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        if (!empty($codes)) {
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $deleteStmt = $pdo->prepare('DELETE FROM promo_vouchers WHERE code NOT IN (' . $placeholders . ')');
            $deleteStmt->execute($codes);
        } else {
            $pdo->exec('DELETE FROM promo_vouchers');
        }

        $upsert = $pdo->prepare(
            'INSERT INTO promo_vouchers
                (code, name, type, value, min_total, max_discount, expires, active)
             VALUES
                (:code, :name, :type, :value, :min_total, :max_discount, :expires, :active)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                type = VALUES(type),
                value = VALUES(value),
                min_total = VALUES(min_total),
                max_discount = VALUES(max_discount),
                expires = VALUES(expires),
                active = VALUES(active)'
        );
        foreach ($vouchers as $voucher) {
            $code = strtoupper(trim((string) ($voucher['code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $type = (string) ($voucher['type'] ?? 'amount');
            $upsert->execute([
                ':code' => $code,
                ':name' => trim((string) ($voucher['name'] ?? '')),
                ':type' => $type === 'percent' ? 'percent' : 'amount',
                ':value' => (float) ($voucher['value'] ?? 0),
                ':min_total' => (float) ($voucher['min_total'] ?? 0),
                ':max_discount' => (float) ($voucher['max'] ?? 0),
                ':expires' => ($voucher['expires'] ?? '') ?: null,
                ':active' => !empty($voucher['active']) ? 1 : 0,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function promo_find_voucher(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    ensure_update_schema();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT code, name, type, value, min_total, max_discount, expires, active
         FROM promo_vouchers
         WHERE UPPER(code) = :code
         LIMIT 1'
    );
    $stmt->execute([':code' => $code]);
    $voucher = $stmt->fetch();
    if (!$voucher || empty($voucher['active'])) {
        return null;
    }
    $expires = trim((string) ($voucher['expires'] ?? ''));
    if ($expires !== '' && $expires < date('Y-m-d')) {
        return null;
    }
    return [
        'code' => (string) $voucher['code'],
        'name' => $voucher['name'] ?? '',
        'type' => (string) $voucher['type'],
        'value' => (float) $voucher['value'],
        'min_total' => (float) $voucher['min_total'],
        'max' => (float) $voucher['max_discount'],
        'expires' => $expires,
        'active' => true,
    ];
}
