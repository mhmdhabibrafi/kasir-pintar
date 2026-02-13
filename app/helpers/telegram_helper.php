<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/format_helper.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notification_helper.php';

function telegram_config(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $envToken = defined('TELEGRAM_BOT_TOKEN') ? (string) TELEGRAM_BOT_TOKEN : '';
    $envChatId = defined('TELEGRAM_CHAT_ID') ? (string) TELEGRAM_CHAT_ID : '';

    $config = [
        'enabled' => ($envToken !== '' && $envChatId !== ''),
        'token' => $envToken,
        'chat_id' => $envChatId,
        'include_logo' => true,
        'include_items' => true,
        'format' => 'detail',
        'notify_transaction' => true,
        'notify_refund' => true,
        'notify_shift' => true,
        'notify_low_stock' => true,
        'daily_recap_enabled' => false,
        'daily_recap_time' => '21:00',
        'daily_recap_last_sent' => null,
    ];

    try {
        $settings = notification_get_config();
        $dbToken = (string) ($settings['telegram_token'] ?? '');
        $dbChatId = (string) ($settings['telegram_chat_id'] ?? '');
        if ($dbToken !== '' && $dbChatId !== '') {
            $config['token'] = $dbToken;
            $config['chat_id'] = $dbChatId;
            $config['enabled'] = !empty($settings['telegram_enabled']);
        }
        if (array_key_exists('telegram_include_logo', $settings)) {
            $config['include_logo'] = !empty($settings['telegram_include_logo']);
        }
        if (array_key_exists('telegram_include_items', $settings)) {
            $config['include_items'] = !empty($settings['telegram_include_items']);
        }
        if (!empty($settings['telegram_format']) && in_array($settings['telegram_format'], ['detail', 'summary'], true)) {
            $config['format'] = (string) $settings['telegram_format'];
        }
        $config['notify_transaction'] = !empty($settings['telegram_notify_transaction']);
        $config['notify_refund'] = !empty($settings['telegram_notify_refund']);
        $config['notify_shift'] = !empty($settings['telegram_notify_shift']);
        $config['notify_low_stock'] = !empty($settings['telegram_notify_low_stock']);
        $config['daily_recap_enabled'] = !empty($settings['telegram_daily_recap_enabled']);
        $config['daily_recap_time'] = telegram_normalize_daily_time((string) ($settings['telegram_daily_recap_time'] ?? '21:00'));
        $config['daily_recap_last_sent'] = $settings['telegram_daily_recap_last_sent'] ?? null;
    } catch (Throwable $e) {
        // fallback to env values
    }

    $cached = $config;
    return $config;
}

function telegram_is_configured(): bool
{
    $config = telegram_config();
    return $config['enabled'] && $config['token'] !== '' && $config['chat_id'] !== '';
}

function telegram_can_send(?string $event = null): bool
{
    $config = telegram_config();
    if (!$config['enabled'] || $config['token'] === '' || $config['chat_id'] === '') {
        return false;
    }

    if ($event === null || $event === '') {
        return true;
    }

    $eventMap = [
        'transaction' => 'notify_transaction',
        'refund' => 'notify_refund',
        'shift' => 'notify_shift',
        'low_stock' => 'notify_low_stock',
    ];
    $key = $eventMap[$event] ?? null;
    if ($key === null) {
        return true;
    }

    return !empty($config[$key]);
}

function telegram_escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function telegram_send_message_raw(string $token, string $chatId, string $text, string $parseMode = 'HTML'): bool
{
    $token = trim($token);
    $chatId = trim($chatId);
    if ($token === '' || $chatId === '') {
        return false;
    }

    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ];
    if ($parseMode !== '') {
        $payload['parse_mode'] = $parseMode;
    }

    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';

    if (function_exists('curl_init')) {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_exec($ch);
            $error = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($error === 0 && $status >= 200 && $status < 300) {
                return true;
            }
            security_log('telegram_send_failed', ['method' => 'sendMessage', 'status' => $status, 'error' => $error]);
            usleep(200000);
        }
        return false;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'timeout' => 6,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        security_log('telegram_send_failed', ['method' => 'sendMessage', 'fallback' => true]);
        return false;
    }
    return true;
}

function telegram_send_message(string $text, string $parseMode = 'HTML'): bool
{
    $config = telegram_config();
    if (!$config['enabled'] || $config['token'] === '' || $config['chat_id'] === '') {
        return false;
    }
    return telegram_send_message_raw((string) $config['token'], (string) $config['chat_id'], $text, $parseMode);
}

function telegram_send_photo(string $filePath, string $caption = '', string $parseMode = 'HTML'): bool
{
    $config = telegram_config();
    if (!$config['enabled'] || $config['token'] === '' || $config['chat_id'] === '') {
        return false;
    }

    if (!is_file($filePath)) {
        return false;
    }

    if (!function_exists('curl_init')) {
        return telegram_send_message($caption, $parseMode);
    }

    $payload = [
        'chat_id' => $config['chat_id'],
        'photo' => new CURLFile($filePath),
    ];

    if ($caption !== '') {
        $payload['caption'] = $caption;
    }

    if ($parseMode !== '') {
        $payload['parse_mode'] = $parseMode;
    }

    $url = 'https://api.telegram.org/bot' . $config['token'] . '/sendPhoto';
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_exec($ch);
        $error = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error === 0 && $status >= 200 && $status < 300) {
            return true;
        }
        security_log('telegram_send_failed', ['method' => 'sendPhoto', 'status' => $status, 'error' => $error]);
        usleep(200000);
    }

    return false;
}

function telegram_send_document(string $filePath, string $caption = '', string $parseMode = 'HTML'): bool
{
    $config = telegram_config();
    if (!$config['enabled'] || $config['token'] === '' || $config['chat_id'] === '') {
        return false;
    }

    if (!is_file($filePath)) {
        return false;
    }

    if (!function_exists('curl_init')) {
        return telegram_send_message($caption, $parseMode);
    }

    $payload = [
        'chat_id' => $config['chat_id'],
        'document' => new CURLFile($filePath),
    ];

    if ($caption !== '') {
        $payload['caption'] = $caption;
    }

    if ($parseMode !== '') {
        $payload['parse_mode'] = $parseMode;
    }

    $url = 'https://api.telegram.org/bot' . $config['token'] . '/sendDocument';
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $error = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error === 0 && $status >= 200 && $status < 300) {
            return true;
        }
        security_log('telegram_send_failed', ['method' => 'sendDocument', 'status' => $status, 'error' => $error]);
        usleep(200000);
    }

    return false;
}

function telegram_normalize_daily_time(string $raw): string
{
    if (preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $raw) === 1) {
        return $raw;
    }
    return '21:00';
}

function telegram_build_daily_recap_message(PDO $pdo, string $date): string
{
    $trxStmt = $pdo->prepare(
        'SELECT COUNT(*) AS total_trx,
                COALESCE(SUM(grand_total), 0) AS total_sales,
                COALESCE(SUM(CASE WHEN payment_method = "cash" THEN grand_total ELSE 0 END), 0) AS cash_sales,
                COALESCE(SUM(CASE WHEN payment_method = "qris" THEN grand_total ELSE 0 END), 0) AS qris_sales
         FROM transaction_meta
         WHERE DATE(created_at) = :day'
    );
    $trxStmt->execute([':day' => $date]);
    $trx = $trxStmt->fetch() ?: [];

    $refundStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS refund_total,
                COALESCE(SUM(CASE WHEN method = "cash" THEN amount ELSE 0 END), 0) AS refund_cash,
                COALESCE(SUM(CASE WHEN method = "qris" THEN amount ELSE 0 END), 0) AS refund_qris
         FROM refunds
         WHERE DATE(created_at) = :day'
    );
    $refundStmt->execute([':day' => $date]);
    $refund = $refundStmt->fetch() ?: [];

    $salesTotal = (float) ($trx['total_sales'] ?? 0);
    $salesCash = (float) ($trx['cash_sales'] ?? 0);
    $salesQris = (float) ($trx['qris_sales'] ?? 0);
    $refundTotal = (float) ($refund['refund_total'] ?? 0);
    $refundCash = (float) ($refund['refund_cash'] ?? 0);
    $refundQris = (float) ($refund['refund_qris'] ?? 0);
    $netSales = max(0.0, $salesTotal - $refundTotal);

    $lines = [];
    $lines[] = 'MY KASPIN';
    $lines[] = 'Rekap Harian';
    $lines[] = str_repeat('-', 30);
    $lines[] = 'Tanggal       : ' . date('d/m/Y', strtotime($date));
    $lines[] = 'Total Trx     : ' . (int) ($trx['total_trx'] ?? 0);
    $lines[] = 'Sales Total   : ' . format_rupiah($salesTotal);
    $lines[] = 'Sales Cash    : ' . format_rupiah($salesCash);
    $lines[] = 'Sales QRIS    : ' . format_rupiah($salesQris);
    $lines[] = 'Refund Total  : -' . format_rupiah($refundTotal);
    $lines[] = 'Refund Cash   : -' . format_rupiah($refundCash);
    $lines[] = 'Refund QRIS   : -' . format_rupiah($refundQris);
    $lines[] = str_repeat('-', 30);
    $lines[] = 'Net Sales     : ' . format_rupiah($netSales);

    return '<pre>' . telegram_escape(implode("\n", $lines)) . '</pre>';
}

function telegram_maybe_send_daily_recap(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $config = notification_get_config();
    } catch (Throwable $e) {
        return;
    }

    if (empty($config['telegram_enabled']) || empty($config['telegram_daily_recap_enabled'])) {
        return;
    }
    if (empty($config['telegram_token']) || empty($config['telegram_chat_id'])) {
        return;
    }

    $now = new DateTimeImmutable('now');
    $today = $now->format('Y-m-d');
    if ((string) ($config['telegram_daily_recap_last_sent'] ?? '') === $today) {
        return;
    }

    $time = telegram_normalize_daily_time((string) ($config['telegram_daily_recap_time'] ?? '21:00'));
    $scheduled = DateTimeImmutable::createFromFormat('Y-m-d H:i', $today . ' ' . $time);
    if (!$scheduled || $now < $scheduled) {
        return;
    }

    try {
        $pdo = db();
        $message = telegram_build_daily_recap_message($pdo, $today);
        $sent = telegram_send_message_raw((string) $config['telegram_token'], (string) $config['telegram_chat_id'], $message, 'HTML');
        if (!$sent) {
            return;
        }

        $config['telegram_daily_recap_last_sent'] = $today;
        notification_save_config($config);
        audit_log('telegram_daily_recap_sent', [
            'date' => $today,
            'time' => $now->format('H:i:s'),
        ]);
    } catch (Throwable $e) {
        security_log('telegram_daily_recap_failed', ['error' => $e->getMessage()]);
    }
}
