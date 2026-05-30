<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/notification_helper.php';
require_once __DIR__ . '/../app/helpers/telegram_helper.php';

require_role(['superadmin', 'admin']);

$pdo = db();
$user = current_user() ?? [];
$isSuperadmin = (string) ($user['role'] ?? '') === 'superadmin';
$notificationScope = $isSuperadmin ? 'system' : 'store';
$notificationStoreId = $isSuperadmin ? null : ((int) ($user['store_id'] ?? 0) ?: null);
$errors = [];
$success = '';
$config = notification_get_config($pdo, $notificationScope, $notificationStoreId);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'save') {
            $config = notification_save_config([
                'telegram_enabled' => isset($_POST['telegram_enabled']),
                'telegram_token' => trim((string) ($_POST['telegram_token'] ?? '')),
                'telegram_chat_id' => trim((string) ($_POST['telegram_chat_id'] ?? '')),
                'telegram_notify_transaction' => !$isSuperadmin && isset($_POST['telegram_notify_transaction']),
                'telegram_notify_refund' => !$isSuperadmin && isset($_POST['telegram_notify_refund']),
                'telegram_notify_shift' => !$isSuperadmin && isset($_POST['telegram_notify_shift']),
                'telegram_notify_low_stock' => !$isSuperadmin && isset($_POST['telegram_notify_low_stock']),
                'telegram_notify_audit' => isset($_POST['telegram_notify_audit']),
                'telegram_notify_tenant' => $isSuperadmin && isset($_POST['telegram_notify_tenant']),
                'telegram_notify_maintenance' => $isSuperadmin && isset($_POST['telegram_notify_maintenance']),
                'telegram_notify_backup' => $isSuperadmin && isset($_POST['telegram_notify_backup']),
                'telegram_notify_health' => $isSuperadmin && isset($_POST['telegram_notify_health']),
                'telegram_daily_recap_enabled' => !$isSuperadmin && isset($_POST['telegram_daily_recap_enabled']),
                'telegram_daily_recap_time' => trim((string) ($_POST['telegram_daily_recap_time'] ?? '21:00')),
            ], $pdo, $notificationScope, $notificationStoreId);
            audit_log($isSuperadmin ? 'system_notification_settings_updated' : 'store_notification_settings_updated');
            $success = 'Pengaturan notifikasi Telegram berhasil disimpan.';
        } elseif ($action === 'test') {
            $sent = telegram_send_message(telegram_pre([
                'KASPINDO',
                $isSuperadmin ? 'Tes Notifikasi Sistem' : 'Tes Notifikasi Operasional Toko',
                str_repeat('-', 30),
                'Status    : berhasil terhubung',
                'Scope     : ' . strtoupper($notificationScope),
                'Waktu     : ' . date('d/m/Y H:i:s'),
            ]), 'HTML', $config);
            $success = $sent ? 'Pesan tes berhasil dikirim ke Telegram.' : '';
            if (!$sent) {
                $errors[] = 'Pesan tes gagal dikirim. Periksa bot token, chat ID, dan koneksi server.';
            }
        }
    }
}

$title = $isSuperadmin ? 'Telegram Sistem' : 'Notifikasi Telegram';
require_once __DIR__ . '/../app/views/layouts/header.php';
?>

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title"><?php echo $isSuperadmin ? 'Telegram Sistem' : 'Notifikasi Telegram'; ?></h2>
        <p class="kp-page-subtitle">
            <?php echo $isSuperadmin
                ? 'Bot Telegram khusus monitoring platform: tenant, approval, maintenance, backup, health, dan audit sistem.'
                : 'Bot Telegram untuk operasional toko: transaksi, refund, shift, stok menipis, dan rekap harian.'; ?>
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-4">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success mb-4"><?php echo e($success); ?></div>
<?php endif; ?>

<section class="card p-6">
    <form method="POST" class="grid gap-5">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save">

        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <label class="inline-flex items-center gap-3 font-semibold m-0">
                <input type="checkbox" name="telegram_enabled" value="1" <?php echo !empty($config['telegram_enabled']) ? 'checked' : ''; ?>>
                Aktifkan Telegram Bot
            </label>
            <span class="badge <?php echo $isSuperadmin ? 'badge-warning' : 'badge-success'; ?>">
                <?php echo $isSuperadmin ? 'Scope: Sistem / Superadmin' : 'Scope: Operasional Toko'; ?>
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label">Bot Token</label>
                <input type="password" name="telegram_token" class="form-input" value="<?php echo e((string) ($config['telegram_token'] ?? '')); ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label">Chat ID</label>
                <input type="text" name="telegram_chat_id" class="form-input" value="<?php echo e((string) ($config['telegram_chat_id'] ?? '')); ?>">
            </div>
        </div>

        <div>
            <div class="font-bold text-slate-900 mb-2">
                <?php echo $isSuperadmin ? 'Event Sistem' : 'Event Operasional Toko'; ?>
            </div>
            <p class="text-sm text-muted mb-3">
                <?php echo $isSuperadmin
                    ? 'Superadmin tidak menerima notifikasi transaksi kasir toko di bagian ini. Gunakan ini untuk kejadian platform dan keamanan.'
                    : 'Notifikasi ini hanya untuk aktivitas toko aktif sesuai store_id akun admin.'; ?>
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            <?php
            $toggles = $isSuperadmin
                ? [
                    'telegram_notify_tenant' => 'Tenant & approval toko',
                    'telegram_notify_maintenance' => 'Maintenance sistem',
                    'telegram_notify_backup' => 'Backup & restore',
                    'telegram_notify_health' => 'System health',
                    'telegram_notify_audit' => 'Audit keamanan',
                ]
                : [
                    'telegram_notify_transaction' => 'Transaksi baru',
                    'telegram_notify_refund' => 'Refund',
                    'telegram_notify_shift' => 'Shift dan kas',
                    'telegram_notify_low_stock' => 'Stok menipis',
                    'telegram_daily_recap_enabled' => 'Rekap harian',
                ];
            ?>
            <?php foreach ($toggles as $name => $label): ?>
                <label class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 inline-flex items-center gap-3">
                    <input type="checkbox" name="<?php echo e($name); ?>" value="1" <?php echo !empty($config[$name]) ? 'checked' : ''; ?>>
                    <span class="font-semibold text-sm"><?php echo e($label); ?></span>
                </label>
            <?php endforeach; ?>
            </div>
        </div>

        <?php if (!$isSuperadmin): ?>
            <div class="form-group max-w-xs">
                <label class="form-label">Jam rekap harian</label>
                <input type="time" name="telegram_daily_recap_time" class="form-input" value="<?php echo e((string) ($config['telegram_daily_recap_time'] ?? '21:00')); ?>">
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn kp-btn-primary">
                <span class="material-icons-outlined">save</span>
                Simpan
            </button>
            <button type="submit" name="action" value="test" class="btn kp-btn-ghost">
                <span class="material-icons-outlined">send</span>
                Tes Kirim
            </button>
        </div>
    </form>
</section>

<?php
require_once __DIR__ . '/../app/views/layouts/footer.php';
?>
