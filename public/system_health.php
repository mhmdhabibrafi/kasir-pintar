<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/backup_helper.php';
require_once __DIR__ . '/../app/helpers/db_migration_helper.php';
require_once __DIR__ . '/../app/helpers/domain_helper.php';
require_once __DIR__ . '/../app/helpers/telegram_helper.php';

ensure_update_schema();
require_role(['superadmin']);

$pdo = db();
$title = 'System Health';
$checks = [];
$errors = [];
$success = '';

$addCheck = static function (string $group, string $label, string $status, string $detail = '') use (&$checks): void {
    $checks[] = [
        'group' => $group,
        'label' => $label,
        'status' => $status,
        'detail' => $detail,
    ];
};

$requiredExtensions = ['pdo_mysql', 'curl', 'openssl', 'zlib', 'fileinfo'];
foreach ($requiredExtensions as $extension) {
    $addCheck(
        'PHP',
        'Extension ' . $extension,
        extension_loaded($extension) ? 'ok' : 'error',
        extension_loaded($extension) ? 'Aktif' : 'Belum aktif di PHP.'
    );
}

try {
    $databaseName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '-');
    $addCheck('Database', 'Koneksi MySQL', 'ok', 'Terhubung ke database ' . $databaseName . '.');
} catch (Throwable $e) {
    $addCheck('Database', 'Koneksi MySQL', 'error', 'Gagal membaca database aktif.');
}

$coreTables = ['roles', 'users', 'stores', 'store_domains', 'categories', 'products', 'transactions', 'payments'];
foreach ($coreTables as $table) {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table"
        );
        $stmt->execute([':table' => $table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        $addCheck('Database', 'Tabel ' . $table, $exists ? 'ok' : 'error', $exists ? 'Ada' : 'Tidak ditemukan.');
    } catch (Throwable $e) {
        $addCheck('Database', 'Tabel ' . $table, 'warning', 'Tidak bisa dicek.');
    }
}

$paths = [
    'storage/logs' => __DIR__ . '/../storage/logs',
    'storage/cache' => __DIR__ . '/../storage/cache',
    'storage/data' => __DIR__ . '/../storage/data',
    'backup' => __DIR__ . '/../backup',
    'public/uploads' => __DIR__ . '/uploads',
];

foreach ($paths as $label => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $addCheck(
        'Storage',
        $label,
        is_dir($path) && is_writable($path) ? 'ok' : 'error',
        is_dir($path) && is_writable($path) ? 'Writable' : 'Tidak writable atau belum ada.'
    );
}

try {
    $currentHost = store_domain_current_host();
    $domainContext = store_domain_current($pdo);
    $dnsTarget = store_domain_dns_target();
    if ($currentHost === '') {
        $addCheck('Domain', 'Host aktif', 'warning', 'Host tidak terdeteksi.');
    } elseif ($domainContext) {
        $addCheck(
            'Domain',
            'Host aktif',
            'ok',
            $currentHost . ' terhubung ke ' . (string) ($domainContext['store_name'] ?? ('store #' . (int) ($domainContext['store_id'] ?? 0)))
        );
    } elseif (store_domain_is_platform_host($currentHost)) {
        $addCheck('Domain', 'Host aktif', 'ok', $currentHost . ' dikenali sebagai domain pusat.');
    } else {
        $addCheck('Domain', 'Host aktif', 'warning', $currentHost . ' belum terdaftar di custom domain toko.');
    }
    $addCheck(
        'Domain',
        'Domain pusat',
        trim((string) (getenv('APP_PRIMARY_HOST') ?: getenv('KASPINDO_PRIMARY_HOST') ?: getenv('APP_URL') ?: '')) !== '' ? 'ok' : 'warning',
        trim((string) (getenv('APP_PRIMARY_HOST') ?: getenv('KASPINDO_PRIMARY_HOST') ?: getenv('APP_URL') ?: '')) !== ''
            ? 'Terkonfigurasi: ' . implode(', ', store_domain_platform_hosts())
            : 'Belum diisi. Set APP_PRIMARY_HOST pada .env.'
    );
    $addCheck(
        'Domain',
        'Target DNS mitra',
        $dnsTarget !== '' ? 'ok' : 'warning',
        $dnsTarget !== '' ? 'Arahkan CNAME domain mitra ke ' . $dnsTarget . '.' : 'Belum diisi. Set APP_DOMAIN_TARGET atau APP_PRIMARY_HOST.'
    );
    $addCheck(
        'Proxy',
        'HTTPS publik',
        app_request_is_https() ? 'ok' : 'warning',
        app_request_is_https()
            ? 'Request terdeteksi HTTPS atau proxy HTTPS.'
            : 'Belum terdeteksi HTTPS. Untuk Cloudflare Tunnel, pastikan header X-Forwarded-Proto/CF-Visitor diteruskan atau set APP_FORCE_HTTPS=1.'
    );
    $addCheck(
        'Proxy',
        'Base path',
        'ok',
        app_base_path_from_request() !== '' ? app_base_path_from_request() : '/'
    );
} catch (Throwable $e) {
    $addCheck('Domain', 'Host aktif', 'warning', 'Tidak bisa membaca mapping domain.');
}

$supportEnabled = function_exists('app_env_bool') ? app_env_bool('APP_SUPPORT_ENABLED', false) : false;
$addCheck(
    'Fitur',
    'Support chat',
    $supportEnabled ? 'ok' : 'warning',
    $supportEnabled
        ? 'Aktif dan tampil untuk role yang diizinkan.'
        : 'Nonaktif. Set APP_SUPPORT_ENABLED=1 jika fitur support mitra ingin dipakai lagi.'
);

try {
    $backupConfig = backup_get_config();
    $mode = (string) ($backupConfig['mode'] ?? 'local_sync');
    $cronToken = trim((string) ($backupConfig['cron_token'] ?? ''));
    $addCheck('Backup', 'Mode backup', 'ok', $mode);
    $addCheck('Backup', 'Cron token', $cronToken !== '' ? 'ok' : 'warning', $cronToken !== '' ? 'Sudah dibuat' : 'Belum dibuat.');
} catch (Throwable $e) {
    $addCheck('Backup', 'Konfigurasi backup', 'warning', 'Belum bisa dibaca.');
}

$statusRank = ['ok' => 0, 'warning' => 1, 'error' => 2];
$worst = 'ok';
foreach ($checks as $check) {
    if (($statusRank[$check['status']] ?? 0) > ($statusRank[$worst] ?? 0)) {
        $worst = $check['status'];
    }
}

$summary = [
    'ok' => 0,
    'warning' => 0,
    'error' => 0,
];
foreach ($checks as $check) {
    $summary[$check['status']] = ($summary[$check['status']] ?? 0) + 1;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif ((string) ($_POST['action'] ?? '') === 'send_health_report') {
        if (telegram_can_send('health')) {
            telegram_notify_system_event('health', [
                'event' => 'Ringkasan system health',
                'status' => strtoupper($worst),
                'note' => 'OK: ' . (int) $summary['ok'] . ', Warning: ' . (int) $summary['warning'] . ', Error: ' . (int) $summary['error'],
            ]);
            $success = 'Ringkasan system health berhasil dikirim ke Telegram Sistem.';
        } else {
            $errors[] = 'Telegram Sistem untuk event System Health belum aktif atau token/chat ID belum diisi.';
        }
    }
}

require_once __DIR__ . '/../app/views/layouts/header.php';
?>

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title">System Health</h2>
        <p class="kp-page-subtitle">Ringkasan kondisi teknis KASPINDO untuk superadmin.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <span class="badge <?php echo $worst === 'error' ? 'badge-danger' : ($worst === 'warning' ? 'badge-warning' : 'badge-success'); ?>">
            <?php echo e(strtoupper($worst)); ?>
        </span>
        <form method="POST" class="m-0">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="send_health_report">
            <button type="submit" class="btn kp-btn-ghost">
                <span class="material-icons-outlined">send</span>
                Kirim ke Telegram
            </button>
        </form>
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

<div class="kp-grid kp-grid-3 mb-4">
    <div class="kp-card-flat p-4">
        <div class="kp-muted small">OK</div>
        <div class="kp-kpi-value text-primary"><?php echo (int) $summary['ok']; ?></div>
    </div>
    <div class="kp-card-flat p-4">
        <div class="kp-muted small">Warning</div>
        <div class="kp-kpi-value" style="color:#b7791f"><?php echo (int) $summary['warning']; ?></div>
    </div>
    <div class="kp-card-flat p-4">
        <div class="kp-muted small">Error</div>
        <div class="kp-kpi-value" style="color:#c0392b"><?php echo (int) $summary['error']; ?></div>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <h3 class="card-title">Diagnostic Check</h3>
        <div class="kp-muted small"><?php echo e(date('d M Y H:i:s')); ?></div>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Grup</th>
                    <th>Check</th>
                    <th>Status</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><?php echo e($check['group']); ?></td>
                        <td><?php echo e($check['label']); ?></td>
                        <td>
                            <span class="badge <?php echo $check['status'] === 'error' ? 'badge-danger' : ($check['status'] === 'warning' ? 'badge-warning' : 'badge-success'); ?>">
                                <?php echo e(strtoupper($check['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo e($check['detail']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
require_once __DIR__ . '/../app/views/layouts/footer.php';
?>
