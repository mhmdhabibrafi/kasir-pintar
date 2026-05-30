<?php

declare(strict_types=1);

require_once __DIR__ . '/data_store.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_helper.php';

const STORE_INFO_FILENAME = 'store_info.json';

function store_info_default(): array
{
    return [
        'store_name' => 'KASPINDO',
        'store_code' => '',
        'status' => '',
        'store_tagline' => 'Kasir, stok, dan laporan harian dalam satu alur kerja.',
        'store_address' => '',
        'store_city' => '',
        'store_province' => '',
        'postal_code' => '',
        'store_phone' => '',
        'store_email' => '',
        'store_whatsapp' => '',
        'store_instagram' => '',
        'business_hours' => '',
        'google_maps_url' => '',
        'receipt_footer' => 'Terima kasih atas kunjungan Anda.',
        'updated_at' => '',
    ];
}

function store_info_get(): array
{
    $storeId = store_info_current_store_id();
    if ($storeId !== null) {
        $storeRecord = store_info_find_store_record($storeId);
        if ($storeRecord !== null) {
            return $storeRecord;
        }
    }

    $stored = data_store_read(STORE_INFO_FILENAME, []);
    $config = store_info_sanitize(array_merge(store_info_default(), $stored), false);

    if (($stored['updated_at'] ?? '') !== $config['updated_at']) {
        data_store_write(STORE_INFO_FILENAME, $config);
    }

    return $config;
}

function store_info_save(array $changes): array
{
    $storeId = store_info_current_store_id();
    if ($storeId !== null) {
        $current = store_info_get();
        $merged = array_merge($current, $changes);
        $sanitized = store_info_sanitize($merged, true);

        try {
            $pdo = db();
            $stmt = $pdo->prepare(
                'UPDATE stores
                 SET store_name = :store_name,
                     store_tagline = :store_tagline,
                     store_address = :store_address,
                     store_city = :store_city,
                     store_province = :store_province,
                     postal_code = :postal_code,
                     store_phone = :store_phone,
                     store_whatsapp = :store_whatsapp,
                     store_email = :store_email,
                     store_instagram = :store_instagram,
                     business_hours = :business_hours,
                     google_maps_url = :google_maps_url,
                     receipt_footer = :receipt_footer
                 WHERE id = :id'
            );
            $stmt->execute([
                ':store_name' => $sanitized['store_name'],
                ':store_tagline' => $sanitized['store_tagline'],
                ':store_address' => $sanitized['store_address'],
                ':store_city' => $sanitized['store_city'],
                ':store_province' => $sanitized['store_province'],
                ':postal_code' => $sanitized['postal_code'],
                ':store_phone' => $sanitized['store_phone'],
                ':store_whatsapp' => $sanitized['store_whatsapp'],
                ':store_email' => $sanitized['store_email'],
                ':store_instagram' => $sanitized['store_instagram'],
                ':business_hours' => $sanitized['business_hours'],
                ':google_maps_url' => $sanitized['google_maps_url'],
                ':receipt_footer' => $sanitized['receipt_footer'],
                ':id' => $storeId,
            ]);
        } catch (Throwable $e) {
            // Fall back to legacy JSON storage if the DB update fails.
        }

        $fresh = store_info_find_store_record($storeId);
        if ($fresh !== null) {
            return $fresh;
        }
    }

    $current = store_info_get();
    $merged = array_merge($current, $changes);
    $sanitized = store_info_sanitize($merged, true);
    data_store_write(STORE_INFO_FILENAME, $sanitized);
    return $sanitized;
}

function store_info_sanitize(array $input, bool $touchUpdatedAt): array
{
    $defaults = store_info_default();

    $storeName = trim((string) ($input['store_name'] ?? $defaults['store_name']));
    $storeCode = trim((string) ($input['store_code'] ?? $defaults['store_code']));
    $status = trim((string) ($input['status'] ?? $defaults['status']));
    $tagline = trim((string) ($input['store_tagline'] ?? $defaults['store_tagline']));
    $address = trim((string) ($input['store_address'] ?? $defaults['store_address']));
    $city = trim((string) ($input['store_city'] ?? $defaults['store_city']));
    $province = trim((string) ($input['store_province'] ?? $defaults['store_province']));
    $postalCode = trim((string) ($input['postal_code'] ?? $defaults['postal_code']));
    $phone = trim((string) ($input['store_phone'] ?? ''));
    $email = trim((string) ($input['store_email'] ?? ''));
    $whatsapp = trim((string) ($input['store_whatsapp'] ?? ''));
    $instagram = store_info_normalize_social_handle((string) ($input['store_instagram'] ?? $defaults['store_instagram']));
    $businessHours = trim((string) ($input['business_hours'] ?? ''));
    $googleMapsUrl = trim((string) ($input['google_maps_url'] ?? ''));
    $receiptFooter = trim((string) ($input['receipt_footer'] ?? $defaults['receipt_footer']));
    $updatedAt = trim((string) ($input['updated_at'] ?? ''));

    if ($storeName === '') {
        $storeName = $defaults['store_name'];
    }
    if ($tagline === '') {
        $tagline = $defaults['store_tagline'];
    }
    if ($receiptFooter === '') {
        $receiptFooter = $defaults['receipt_footer'];
    }

    if ($touchUpdatedAt || $updatedAt === '') {
        $updatedAt = date('Y-m-d H:i:s');
    } else {
        $normalized = store_info_normalize_datetime($updatedAt);
        $updatedAt = $normalized !== '' ? $normalized : date('Y-m-d H:i:s');
    }

    return [
        'store_code' => store_info_limit($storeCode, 30),
        'status' => store_info_limit($status, 20),
        'store_name' => store_info_limit($storeName, 100),
        'store_tagline' => store_info_limit($tagline, 180),
        'store_address' => store_info_limit($address, 255),
        'store_city' => store_info_limit($city, 100),
        'store_province' => store_info_limit($province, 100),
        'postal_code' => store_info_limit($postalCode, 20),
        'store_phone' => store_info_limit($phone, 50),
        'store_whatsapp' => store_info_limit($whatsapp, 50),
        'store_email' => store_info_limit($email, 120),
        'store_instagram' => store_info_limit($instagram, 80),
        'business_hours' => store_info_limit($businessHours, 120),
        'google_maps_url' => store_info_limit($googleMapsUrl, 255),
        'receipt_footer' => store_info_limit($receiptFooter, 160),
        'updated_at' => $updatedAt,
    ];
}

function store_info_current_store_id(): ?int
{
    $user = current_user();
    $storeId = (int) ($user['store_id'] ?? 0);
    return $storeId > 0 ? $storeId : null;
}

function store_info_find_store_record(int $storeId): ?array
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT store_code, status, store_name, store_tagline, store_address, store_city, store_province, postal_code,
                    store_phone, store_whatsapp, store_email, store_instagram, business_hours, google_maps_url,
                    receipt_footer, updated_at
             FROM stores
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $storeId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return store_info_sanitize(array_merge(store_info_default(), [
            'store_code' => (string) ($row['store_code'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'store_name' => (string) ($row['store_name'] ?? ''),
            'store_tagline' => (string) ($row['store_tagline'] ?? ''),
            'store_address' => (string) ($row['store_address'] ?? ''),
            'store_city' => (string) ($row['store_city'] ?? ''),
            'store_province' => (string) ($row['store_province'] ?? ''),
            'postal_code' => (string) ($row['postal_code'] ?? ''),
            'store_phone' => (string) ($row['store_phone'] ?? ''),
            'store_whatsapp' => (string) ($row['store_whatsapp'] ?? ''),
            'store_email' => (string) ($row['store_email'] ?? ''),
            'store_instagram' => (string) ($row['store_instagram'] ?? ''),
            'business_hours' => (string) ($row['business_hours'] ?? ''),
            'google_maps_url' => (string) ($row['google_maps_url'] ?? ''),
            'receipt_footer' => (string) ($row['receipt_footer'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ]), false);
    } catch (Throwable $e) {
        return null;
    }
}

function store_info_normalize_datetime(string $value): string
{
    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function store_info_limit(string $value, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function store_info_normalize_social_handle(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $value) === 1) {
        return $value;
    }

    if ($value[0] !== '@') {
        return '@' . ltrim($value, '@');
    }

    return $value;
}

function store_info_compose_address(array $storeInfo): string
{
    $parts = [];
    $address = trim((string) ($storeInfo['store_address'] ?? ''));
    $city = trim((string) ($storeInfo['store_city'] ?? ''));
    $province = trim((string) ($storeInfo['store_province'] ?? ''));
    $postalCode = trim((string) ($storeInfo['postal_code'] ?? ''));

    if ($address !== '') {
        $parts[] = $address;
    }

    $region = trim(implode(', ', array_values(array_filter([$city, $province], static fn ($part): bool => $part !== ''))));
    if ($region !== '') {
        $parts[] = $region;
    }

    if ($postalCode !== '') {
        $parts[] = $postalCode;
    }

    return implode(', ', $parts);
}

function store_info_is_demo_profile(array $storeInfo): bool
{
    $haystacks = [
        strtolower(trim((string) ($storeInfo['store_code'] ?? ''))),
        strtolower(trim((string) ($storeInfo['store_name'] ?? ''))),
        strtolower(trim((string) ($storeInfo['store_tagline'] ?? ''))),
        strtolower(trim((string) ($storeInfo['store_address'] ?? ''))),
        strtolower(trim((string) ($storeInfo['store_email'] ?? ''))),
    ];

    foreach ($haystacks as $value) {
        if ($value === '') {
            continue;
        }

        if (
            str_contains($value, 'demo')
            || str_contains($value, 'dummy')
            || str_contains($value, '.local')
            || str_contains($value, 'internal-demo')
        ) {
            return true;
        }
    }

    return false;
}

function store_info_completion(array $storeInfo): array
{
    $checks = [
        'identitas' => trim((string) ($storeInfo['store_name'] ?? '')) !== '' && trim((string) ($storeInfo['store_tagline'] ?? '')) !== '',
        'alamat' => store_info_compose_address($storeInfo) !== '',
        'kontak' => trim((string) ($storeInfo['store_phone'] ?? '')) !== '' || trim((string) ($storeInfo['store_whatsapp'] ?? '')) !== '',
        'email' => trim((string) ($storeInfo['store_email'] ?? '')) !== '',
        'operasional' => trim((string) ($storeInfo['business_hours'] ?? '')) !== '',
        'kanal_digital' => trim((string) ($storeInfo['store_instagram'] ?? '')) !== '' || trim((string) ($storeInfo['google_maps_url'] ?? '')) !== '',
        'footer' => trim((string) ($storeInfo['receipt_footer'] ?? '')) !== '',
    ];

    $total = count($checks);
    $completed = count(array_filter($checks));
    $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

    return [
        'checks' => $checks,
        'completed' => $completed,
        'total' => $total,
        'percent' => $percent,
    ];
}
