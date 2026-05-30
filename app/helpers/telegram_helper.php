<?php

declare(strict_types=1);

require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/format_helper.php';

function telegram_escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function telegram_can_send(string $event = 'transaction'): bool
{
    try {
        $systemEvents = ['audit', 'tenant', 'maintenance', 'backup', 'health'];
        $config = in_array($event, $systemEvents, true)
            ? notification_get_config(null, 'system')
            : notification_get_config(null, 'store');
        return notification_event_enabled($event, $config);
    } catch (Throwable $e) {
        return false;
    }
}

function telegram_send_message(string $message, string $parseMode = 'HTML', ?array $config = null): bool
{
    $config = $config ?? notification_get_config();
    $token = trim((string) ($config['telegram_token'] ?? ''));
    $chatId = trim((string) ($config['telegram_chat_id'] ?? ''));
    if ($token === '' || $chatId === '' || !function_exists('curl_init')) {
        return false;
    }

    $ch = curl_init('https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage');
    if ($ch === false) {
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => '1',
        ],
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return false;
    }

    $payload = json_decode((string) $response, true);
    return is_array($payload) && !empty($payload['ok']);
}

function telegram_pre(array $lines): string
{
    return '<pre>' . telegram_escape(implode("\n", $lines)) . '</pre>';
}

function telegram_notify_transaction(array $data): void
{
    if (!telegram_can_send('transaction')) {
        return;
    }

    $lines = [
        'KASPINDO',
        'Transaksi Baru',
        str_repeat('-', 30),
        'ID        : #' . (string) ($data['transaction_id'] ?? '-'),
        'Kasir     : ' . (string) ($data['cashier'] ?? '-'),
        'Metode    : ' . strtoupper((string) ($data['method'] ?? '-')),
        'Total     : ' . format_rupiah((float) ($data['total'] ?? 0)),
        'Shift     : ' . (string) ($data['shift_id'] ?? '-'),
        'Waktu     : ' . date('d/m/Y H:i:s'),
    ];
    telegram_send_message(telegram_pre($lines), 'HTML');
}

function telegram_notify_shift(string $action, array $data): void
{
    if (!telegram_can_send('shift')) {
        return;
    }

    $title = [
        'opened' => 'Shift Dibuka',
        'closed' => 'Shift Ditutup',
        'movement' => 'Kas Shift Bergerak',
    ][$action] ?? 'Update Shift';

    $lines = [
        'KASPINDO',
        $title,
        str_repeat('-', 30),
        'Shift     : ' . (string) ($data['shift_id'] ?? '-'),
        'User      : ' . (string) ($data['user_name'] ?? $data['user_id'] ?? '-'),
    ];

    if (isset($data['opening_cash'])) {
        $lines[] = 'Kas Awal  : ' . format_rupiah((float) $data['opening_cash']);
    }
    if (isset($data['closing_cash'])) {
        $lines[] = 'Kas Akhir : ' . format_rupiah((float) $data['closing_cash']);
    }
    if (isset($data['amount'])) {
        $lines[] = 'Nominal   : ' . format_rupiah((float) $data['amount']);
    }
    if (!empty($data['type'])) {
        $lines[] = 'Tipe      : ' . strtoupper((string) $data['type']);
    }
    if (!empty($data['note'])) {
        $lines[] = 'Catatan   : ' . (string) $data['note'];
    }
    $lines[] = 'Waktu     : ' . date('d/m/Y H:i:s');

    telegram_send_message(telegram_pre($lines), 'HTML');
}

function telegram_notify_low_stock(array $items): void
{
    if (!telegram_can_send('low_stock') || empty($items)) {
        return;
    }

    $lines = ['KASPINDO', 'Stok Menipis', str_repeat('-', 30)];
    foreach (array_slice($items, 0, 10) as $item) {
        $lines[] = '- ' . (string) ($item['name'] ?? ('#' . ($item['id'] ?? '-')))
            . ' | stok ' . (string) ($item['stock'] ?? 0)
            . ' / min ' . (string) ($item['min'] ?? 0);
    }
    $lines[] = 'Waktu     : ' . date('d/m/Y H:i:s');

    telegram_send_message(telegram_pre($lines), 'HTML');
}

function telegram_notify_audit(string $message, array $context = []): void
{
    if (!telegram_can_send('audit')) {
        return;
    }

    $lines = [
        'KASPINDO',
        'Audit Event',
        str_repeat('-', 30),
        'Event     : ' . $message,
        'User      : ' . (string) ($context['username'] ?? $context['user_id'] ?? '-'),
        'Role      : ' . (string) ($context['role'] ?? '-'),
        'Waktu     : ' . date('d/m/Y H:i:s'),
    ];
    telegram_send_message(telegram_pre($lines), 'HTML');
}

function telegram_notify_system_event(string $event, array $data = []): void
{
    if (!telegram_can_send($event)) {
        return;
    }

    $titles = [
        'tenant' => 'Tenant / Mitra',
        'maintenance' => 'Maintenance Sistem',
        'backup' => 'Backup Sistem',
        'health' => 'System Health',
    ];

    $lines = [
        'KASPINDO SYSTEM',
        $titles[$event] ?? 'Event Sistem',
        str_repeat('-', 30),
        'Event     : ' . (string) ($data['event'] ?? $event),
    ];

    foreach ([
        'store_name' => 'Toko',
        'domain' => 'Domain',
        'referral' => 'Referral',
        'status' => 'Status',
        'mode' => 'Mode',
        'trigger' => 'Trigger',
        'file_name' => 'File',
        'actor' => 'Admin',
        'note' => 'Catatan',
    ] as $key => $label) {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value !== '') {
            $lines[] = str_pad($label, 10) . ': ' . $value;
        }
    }

    $lines[] = 'Waktu     : ' . date('d/m/Y H:i:s');
    telegram_send_message(telegram_pre($lines), 'HTML', notification_get_config(null, 'system'));
}
