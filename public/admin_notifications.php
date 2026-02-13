<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/notification_helper.php';
require_once __DIR__ . '/../app/helpers/telegram_helper.php';

require_role(['admin']);

$errors = [];
$success = '';

$config = notification_get_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        $enabled = isset($_POST['telegram_enabled']);
        $token = trim((string) ($_POST['telegram_token'] ?? ''));
        $chatId = trim((string) ($_POST['telegram_chat_id'] ?? ''));
        $includeLogo = isset($_POST['telegram_include_logo']);
        $includeItems = isset($_POST['telegram_include_items']);
        $notifyTransaction = isset($_POST['telegram_notify_transaction']);
        $notifyRefund = isset($_POST['telegram_notify_refund']);
        $notifyShift = isset($_POST['telegram_notify_shift']);
        $notifyLowStock = isset($_POST['telegram_notify_low_stock']);
        $dailyRecapEnabled = isset($_POST['telegram_daily_recap_enabled']);
        $dailyRecapTime = trim((string) ($_POST['telegram_daily_recap_time'] ?? '21:00'));
        $format = in_array(($_POST['telegram_format'] ?? 'detail'), ['detail', 'summary'], true)
            ? (string) $_POST['telegram_format']
            : 'detail';

        if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $dailyRecapTime)) {
            $dailyRecapTime = '21:00';
            $errors[] = 'Format jam rekap harus HH:MM (contoh: 21:00).';
        }
        if ($chatId !== '' && !preg_match('/^-?\d+$/', $chatId)) {
            $errors[] = 'ID grup Telegram harus berupa angka (contoh: -1001234567890).';
        }

        $config['telegram_enabled'] = $enabled;
        $config['telegram_token'] = $token;
        $config['telegram_chat_id'] = $chatId;
        $config['telegram_include_logo'] = $includeLogo;
        $config['telegram_include_items'] = $includeItems;
        $config['telegram_format'] = $format;
        $config['telegram_notify_transaction'] = $notifyTransaction;
        $config['telegram_notify_refund'] = $notifyRefund;
        $config['telegram_notify_shift'] = $notifyShift;
        $config['telegram_notify_low_stock'] = $notifyLowStock;
        $config['telegram_daily_recap_enabled'] = $dailyRecapEnabled;
        $config['telegram_daily_recap_time'] = $dailyRecapTime;

        if ($action === 'test_telegram') {
            if ($token === '' || $chatId === '') {
                $errors[] = 'Isi token bot dan ID grup untuk tes koneksi.';
            }
            if (empty($errors)) {
                $message = "<b>MY KASPIN</b>\n"
                    . "Tes koneksi Telegram berhasil.\n"
                    . 'Waktu: ' . date('d/m/Y H:i:s');
                $sent = telegram_send_message_raw($token, $chatId, $message, 'HTML');
                if ($sent) {
                    $success = 'Tes koneksi berhasil. Pesan uji coba sudah dikirim.';
                } else {
                    $errors[] = 'Tes koneksi gagal. Cek token bot, chat ID, dan pastikan bot sudah ada di grup.';
                }
            }
        } else {
            if ($enabled && ($token === '' || $chatId === '')) {
                $errors[] = 'Token bot dan ID grup wajib diisi jika Telegram diaktifkan.';
            }

            if (empty($errors)) {
                notification_save_config($config);
                $success = 'Pengaturan Telegram berhasil disimpan.';
                $config = notification_get_config();
            }
        }
    }
}

$title = 'Pengaturan Telegram';
require_once __DIR__ . '/../app/views/admin/notifications.php';
