<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helper.php';

function telegram_is_configured(): bool
{
    return defined('TELEGRAM_BOT_TOKEN') && defined('TELEGRAM_CHAT_ID')
        && TELEGRAM_BOT_TOKEN !== '' && TELEGRAM_CHAT_ID !== '';
}

function telegram_escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function telegram_send_message(string $text, string $parseMode = 'HTML'): bool
{
    if (!telegram_is_configured()) {
        return false;
    }

    $payload = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $text,
        'disable_web_page_preview' => true,
    ];
    if ($parseMode !== '') {
        $payload['parse_mode'] = $parseMode;
    }

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';

    if (function_exists('curl_init')) {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $result = curl_exec($ch);
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

function telegram_send_photo(string $filePath, string $caption = '', string $parseMode = 'HTML'): bool
{
    if (!telegram_is_configured()) {
        return false;
    }

    if (!is_file($filePath)) {
        return false;
    }

    if (!function_exists('curl_init')) {
        return telegram_send_message($caption, $parseMode);
    }

    $payload = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'photo' => new CURLFile($filePath),
    ];

    if ($caption !== '') {
        $payload['caption'] = $caption;
    }

    if ($parseMode !== '') {
        $payload['parse_mode'] = $parseMode;
    }

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendPhoto';
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $result = curl_exec($ch);
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
    if (!telegram_is_configured()) {
        return false;
    }

    if (!is_file($filePath)) {
        return false;
    }

    if (!function_exists('curl_init')) {
        return telegram_send_message($caption, $parseMode);
    }

    $payload = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'document' => new CURLFile($filePath),
    ];

    if ($caption !== '') {
        $payload['caption'] = $caption;
    }

    if ($parseMode !== '') {
        $payload['parse_mode'] = $parseMode;
    }

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendDocument';
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
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
