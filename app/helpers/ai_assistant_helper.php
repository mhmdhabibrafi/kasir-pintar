<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db_migration_helper.php';
require_once __DIR__ . '/tenant_helper.php';

function ai_assistant_config(): array
{
    $baseUrl = rtrim((string) (getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');

    return [
        'enabled' => app_env_bool('AI_ASSISTANT_ENABLED', false),
        'provider' => (string) (getenv('AI_PROVIDER') ?: 'openai'),
        'api_key' => (string) (getenv('OPENAI_API_KEY') ?: getenv('AI_OPENAI_API_KEY') ?: ''),
        'model' => (string) (getenv('OPENAI_MODEL') ?: getenv('AI_MODEL') ?: 'gpt-5.5'),
        'endpoint' => $baseUrl . '/responses',
        'organization' => (string) (getenv('OPENAI_ORG_ID') ?: ''),
        'project' => (string) (getenv('OPENAI_PROJECT') ?: ''),
        'timeout' => max(5, (int) (getenv('AI_TIMEOUT_SECONDS') ?: 30)),
        'max_output_tokens' => max(200, min(4000, (int) (getenv('AI_MAX_OUTPUT_TOKENS') ?: 900))),
        'reasoning_effort' => trim((string) (getenv('AI_REASONING_EFFORT') ?: 'medium')),
    ];
}

function ai_assistant_status(): array
{
    $config = ai_assistant_config();
    $reasons = [];

    if (!$config['enabled']) {
        $reasons[] = 'AI_ASSISTANT_ENABLED belum aktif';
    }
    if ($config['api_key'] === '') {
        $reasons[] = 'OPENAI_API_KEY belum diisi';
    }
    if (!function_exists('curl_init')) {
        $reasons[] = 'Ekstensi PHP curl belum aktif';
    }

    return [
        'ready' => empty($reasons),
        'reasons' => $reasons,
        'provider' => $config['provider'],
        'model' => $config['model'],
        'endpoint' => $config['endpoint'],
    ];
}

function ai_assistant_normalize_date(?string $value, string $fallback): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
    if (!$date || $date->format('Y-m-d') !== $raw) {
        return $fallback;
    }

    return $raw;
}

function ai_assistant_business_snapshot(PDO $pdo, array $user, string $startDate, string $endDate): array
{
    ensure_update_schema();

    if ($startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }

    $params = [':start_date' => $startDate, ':end_date' => $endDate];
    $storeId = tenant_user_store_id($user, $pdo);
    $scope = $storeId === null ? 'platform' : 'store';
    $storeName = 'Semua toko';

    if ($storeId !== null && tenant_table_has_column($pdo, 'stores', 'store_name')) {
        try {
            $stmt = $pdo->prepare('SELECT store_name FROM stores WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $storeId]);
            $storeName = (string) ($stmt->fetchColumn() ?: ('Store #' . $storeId));
        } catch (Throwable $e) {
            $storeName = 'Store #' . $storeId;
        }
    }

    $snapshot = [
        'generated_at' => date('c'),
        'period' => ['start_date' => $startDate, 'end_date' => $endDate],
        'scope' => ['type' => $scope, 'store_id' => $storeId, 'store_name' => $storeName],
        'metrics' => [
            'transactions' => 0,
            'gross_sales' => 0.0,
            'refund_total' => 0.0,
            'net_sales' => 0.0,
            'average_ticket' => 0.0,
            'cash_sales' => 0.0,
            'qris_sales' => 0.0,
            'low_stock_count' => 0,
        ],
        'top_products' => [],
        'daily_sales' => [],
        'low_stock_items' => [],
    ];

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM transactions
             WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date'
            . tenant_where_clause($pdo, 'transactions', 'transactions', 'AND', $user)
        );
        $stmt->execute(tenant_bind($params, $pdo, $user));
        $snapshot['metrics']['transactions'] = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $snapshot['warnings'][] = 'Jumlah transaksi belum bisa dibaca.';
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT payments.method, COALESCE(SUM(payments.amount), 0) AS total
             FROM payments
             WHERE DATE(payments.created_at) BETWEEN :start_date AND :end_date'
            . tenant_where_clause($pdo, 'payments', 'payments', 'AND', $user) . '
             GROUP BY payments.method'
        );
        $stmt->execute(tenant_bind($params, $pdo, $user));
        foreach ($stmt->fetchAll() as $row) {
            $method = strtolower((string) ($row['method'] ?? ''));
            $total = (float) ($row['total'] ?? 0);
            if ($method === 'cash') {
                $snapshot['metrics']['cash_sales'] = $total;
            } elseif ($method === 'qris') {
                $snapshot['metrics']['qris_sales'] = $total;
            }
        }
    } catch (Throwable $e) {
        $snapshot['warnings'][] = 'Ringkasan pembayaran belum bisa dibaca.';
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(refunds.amount), 0)
             FROM refunds
             WHERE DATE(refunds.created_at) BETWEEN :start_date AND :end_date'
            . tenant_where_clause($pdo, 'refunds', 'refunds', 'AND', $user)
        );
        $stmt->execute(tenant_bind($params, $pdo, $user));
        $snapshot['metrics']['refund_total'] = (float) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $snapshot['metrics']['refund_total'] = 0.0;
    }

    $snapshot['metrics']['gross_sales'] = (float) $snapshot['metrics']['cash_sales'] + (float) $snapshot['metrics']['qris_sales'];
    $snapshot['metrics']['net_sales'] = max(0.0, (float) $snapshot['metrics']['gross_sales'] - (float) $snapshot['metrics']['refund_total']);
    if ((int) $snapshot['metrics']['transactions'] > 0) {
        $snapshot['metrics']['average_ticket'] = (float) $snapshot['metrics']['net_sales'] / (int) $snapshot['metrics']['transactions'];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT DATE(payments.created_at) AS day, COALESCE(SUM(payments.amount), 0) AS total
             FROM payments
             WHERE DATE(payments.created_at) BETWEEN :start_date AND :end_date'
            . tenant_where_clause($pdo, 'payments', 'payments', 'AND', $user) . '
             GROUP BY DATE(payments.created_at)
             ORDER BY day ASC'
        );
        $stmt->execute(tenant_bind($params, $pdo, $user));
        foreach ($stmt->fetchAll() as $row) {
            $snapshot['daily_sales'][] = [
                'date' => (string) ($row['day'] ?? ''),
                'total' => (float) ($row['total'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        $snapshot['warnings'][] = 'Tren harian belum bisa dibaca.';
    }

    try {
        $qtyColumn = Transaction::itemQuantityColumn($pdo) ?? 'qty';
        $tenant = tenant_multi_where_clause($pdo, [
            'transactions' => 'transactions',
            'transaction_items' => 'transaction_items',
            'products' => 'products',
        ], 'AND', $user);
        $stmt = $pdo->prepare(
            'SELECT products.name, COALESCE(SUM(transaction_items.' . $qtyColumn . '), 0) AS qty
             FROM transaction_items
             INNER JOIN transactions ON transactions.id = transaction_items.transaction_id
             INNER JOIN products ON products.id = transaction_items.product_id
             WHERE DATE(transactions.created_at) BETWEEN :start_date AND :end_date'
            . $tenant['sql'] . '
             GROUP BY products.id, products.name
             ORDER BY qty DESC
             LIMIT 5'
        );
        $stmt->execute($params + $tenant['params']);
        foreach ($stmt->fetchAll() as $row) {
            $snapshot['top_products'][] = [
                'name' => (string) ($row['name'] ?? ''),
                'qty' => (int) ($row['qty'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        $snapshot['warnings'][] = 'Produk terlaris belum bisa dibaca.';
    }

    try {
        $tenant = tenant_multi_where_clause($pdo, [
            'inventory_items' => 'inventory_items',
            'products' => 'products',
        ], 'AND', $user);
        $stmt = $pdo->prepare(
            'SELECT products.name, inventory_items.stock, inventory_items.min_stock
             FROM inventory_items
             INNER JOIN products ON products.id = inventory_items.product_id
             WHERE inventory_items.stock IS NOT NULL
               AND inventory_items.stock <= inventory_items.min_stock'
            . $tenant['sql'] . '
             ORDER BY inventory_items.stock ASC, products.name ASC
             LIMIT 10'
        );
        $stmt->execute($tenant['params']);
        foreach ($stmt->fetchAll() as $row) {
            $snapshot['low_stock_items'][] = [
                'name' => (string) ($row['name'] ?? ''),
                'stock' => (int) ($row['stock'] ?? 0),
                'min_stock' => (int) ($row['min_stock'] ?? 0),
            ];
        }
        $snapshot['metrics']['low_stock_count'] = count($snapshot['low_stock_items']);
    } catch (Throwable $e) {
        $snapshot['metrics']['low_stock_count'] = 0;
    }

    return $snapshot;
}

function ai_assistant_answer(PDO $pdo, string $question, array $user, string $startDate, string $endDate): array
{
    $status = ai_assistant_status();
    if (!$status['ready']) {
        throw new RuntimeException('AI Assistant belum siap: ' . implode(', ', $status['reasons']));
    }

    $question = trim($question);
    if ($question === '') {
        throw new InvalidArgumentException('Pertanyaan AI tidak boleh kosong.');
    }
    if (strlen($question) > 2000) {
        throw new InvalidArgumentException('Pertanyaan AI maksimal 2000 karakter.');
    }

    $snapshot = ai_assistant_business_snapshot($pdo, $user, $startDate, $endDate);
    $response = ai_assistant_call_openai($question, $snapshot);

    audit_log('ai_assistant_used', [
        'model' => $response['model'],
        'period' => $snapshot['period'],
        'scope' => $snapshot['scope'],
        'request_id' => $response['request_id'] ?? null,
    ]);

    return [
        'answer' => $response['answer'],
        'model' => $response['model'],
        'usage' => $response['usage'],
        'request_id' => $response['request_id'],
        'snapshot' => $snapshot,
    ];
}

function ai_assistant_call_openai(string $question, array $snapshot): array
{
    $config = ai_assistant_config();
    $instructions = implode("\n", [
        'Anda adalah KASPINDO AI Assistant untuk sistem POS toko.',
        'Jawab dalam Bahasa Indonesia yang ringkas, praktis, dan sopan.',
        'Fokus pada insight operasional: penjualan, kas, stok, promo, member, refund, dan shift.',
        'Berikan 3-6 poin aksi yang bisa dilakukan admin toko.',
        'Jangan meminta, menampilkan, atau menyimpan API key, password, token, credential backup, atau data sensitif.',
        'Jika data belum cukup, sebutkan asumsi dan rekomendasikan pengecekan manual.',
    ]);

    $contextJson = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($contextJson === false) {
        $contextJson = '{}';
    }

    $payload = [
        'model' => $config['model'],
        'input' => $instructions . "\n\nKonteks operasional JSON:\n" . $contextJson . "\n\nPertanyaan operator:\n" . $question,
        'max_output_tokens' => $config['max_output_tokens'],
    ];

    if ($config['reasoning_effort'] !== '' && strtolower($config['reasoning_effort']) !== 'none') {
        $payload['reasoning'] = ['effort' => $config['reasoning_effort']];
    }

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key'],
    ];
    if ($config['organization'] !== '') {
        $headers[] = 'OpenAI-Organization: ' . $config['organization'];
    }
    if ($config['project'] !== '') {
        $headers[] = 'OpenAI-Project: ' . $config['project'];
    }

    $requestId = null;
    $ch = curl_init($config['endpoint']);
    if ($ch === false) {
        throw new RuntimeException('Gagal menyiapkan koneksi AI.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => min(10, (int) $config['timeout']),
        CURLOPT_TIMEOUT => (int) $config['timeout'],
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$requestId): int {
            $length = strlen($header);
            if (stripos($header, 'x-request-id:') === 0) {
                $requestId = trim(substr($header, strlen('x-request-id:')));
            }
            return $length;
        },
    ]);

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false || $raw === '') {
        throw new RuntimeException('Koneksi ke OpenAI API gagal' . ($error !== '' ? ': ' . $error : '.'));
    }

    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respons AI tidak valid.');
    }

    if ($httpCode >= 400) {
        $message = (string) ($decoded['error']['message'] ?? 'OpenAI API mengembalikan error.');
        throw new RuntimeException('OpenAI API error (' . $httpCode . '): ' . $message);
    }

    $answer = ai_assistant_extract_output_text($decoded);
    if ($answer === '') {
        throw new RuntimeException('AI tidak mengembalikan teks jawaban.');
    }

    return [
        'answer' => $answer,
        'model' => (string) ($decoded['model'] ?? $config['model']),
        'usage' => is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [],
        'request_id' => $requestId,
    ];
}

function ai_assistant_extract_output_text(array $response): string
{
    if (isset($response['output_text']) && is_string($response['output_text'])) {
        return trim($response['output_text']);
    }

    $chunks = [];
    foreach (($response['output'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach (($item['content'] ?? []) as $content) {
            if (!is_array($content)) {
                continue;
            }
            $text = $content['text'] ?? $content['output_text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                $chunks[] = trim($text);
            }
        }
    }

    return trim(implode("\n\n", $chunks));
}
