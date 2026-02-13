<?php
require_once __DIR__ . '/../layouts/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?php echo e($success); ?>
    </div>
<?php endif; ?>

<div class="kp-page-header align-items-center">
    <div class="text-center text-md-start w-100">
        <h2 class="kp-page-title">Pengaturan Telegram</h2>
        <p class="kp-page-subtitle">Simpan token bot dan ID grup untuk kirim notifikasi otomatis.</p>
    </div>
    <div class="kp-page-actions justify-content-center justify-content-md-end w-100">
        <?php if (telegram_is_configured()): ?>
            <span class="kp-badge">Telegram aktif</span>
        <?php else: ?>
            <span class="kp-alert-badge active">Belum terhubung</span>
        <?php endif; ?>
    </div>
</div>

<div class="kp-card p-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
    <form method="POST" class="kp-form-grid">
        <?php echo csrf_field(); ?>
        <div class="kp-form-full">
            <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="telegram_enabled" name="telegram_enabled" <?php echo !empty($config['telegram_enabled']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="telegram_enabled">Aktifkan Telegram</label>
            </div>
        </div>
        <div>
            <label class="form-label">Token Bot Telegram</label>
            <input type="text" class="form-control" name="telegram_token" placeholder="123456:ABCDEF..." value="<?php echo e((string) ($config['telegram_token'] ?? '')); ?>">
            <div class="kp-muted small mt-1">Token dari BotFather.</div>
        </div>
        <div>
            <label class="form-label">ID Grup / Channel</label>
            <input type="text" class="form-control" name="telegram_chat_id" placeholder="-1001234567890" value="<?php echo e((string) ($config['telegram_chat_id'] ?? '')); ?>">
            <div class="kp-muted small mt-1">Biasanya diawali -100 untuk grup.</div>
        </div>
        <div>
            <label class="form-label">Format Struk</label>
            <select class="form-select" name="telegram_format">
                <option value="detail" <?php echo (($config['telegram_format'] ?? 'detail') === 'detail') ? 'selected' : ''; ?>>Detail</option>
                <option value="summary" <?php echo (($config['telegram_format'] ?? 'detail') === 'summary') ? 'selected' : ''; ?>>Ringkas</option>
            </select>
        </div>
        <div>
            <label class="form-label">Opsi Struk</label>
            <div class="d-flex flex-column gap-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="telegram_include_logo" name="telegram_include_logo" <?php echo !empty($config['telegram_include_logo']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="telegram_include_logo">Sertakan logo</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="telegram_include_items" name="telegram_include_items" <?php echo !empty($config['telegram_include_items']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="telegram_include_items">Sertakan detail item</label>
                </div>
            </div>
        </div>
        <div>
            <label class="form-label">Jenis Notifikasi</label>
            <div class="d-flex flex-column gap-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="telegram_notify_transaction" name="telegram_notify_transaction" <?php echo !empty($config['telegram_notify_transaction']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="telegram_notify_transaction">Transaksi berhasil</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="telegram_notify_refund" name="telegram_notify_refund" <?php echo !empty($config['telegram_notify_refund']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="telegram_notify_refund">Refund</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="telegram_notify_shift" name="telegram_notify_shift" <?php echo !empty($config['telegram_notify_shift']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="telegram_notify_shift">Shift (buka/tutup/kas masuk-keluar)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="telegram_notify_low_stock" name="telegram_notify_low_stock" <?php echo !empty($config['telegram_notify_low_stock']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="telegram_notify_low_stock">Stok menipis setelah transaksi</label>
                </div>
            </div>
        </div>
        <div>
            <label class="form-label">Rekap Harian Otomatis</label>
            <div class="d-flex flex-column gap-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="telegram_daily_recap_enabled" name="telegram_daily_recap_enabled" <?php echo !empty($config['telegram_daily_recap_enabled']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="telegram_daily_recap_enabled">Aktifkan rekap harian</label>
                </div>
                <div>
                    <label class="form-label mb-1" for="telegram_daily_recap_time">Jam kirim</label>
                    <input type="time" class="form-control" id="telegram_daily_recap_time" name="telegram_daily_recap_time" value="<?php echo e((string) ($config['telegram_daily_recap_time'] ?? '21:00')); ?>">
                </div>
                <div class="kp-muted small">Rekap dikirim sekali per hari setelah jam tersebut.</div>
            </div>
        </div>
        <div class="kp-form-full kp-form-actions">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn kp-btn-primary" type="submit" name="action" value="save">
                <span class="material-icons-outlined">save</span>
                Simpan Pengaturan
                </button>
                <button class="btn kp-btn-ghost" type="submit" name="action" value="test_telegram">
                    <span class="material-icons-outlined">network_check</span>
                    Tes Koneksi
                </button>
            </div>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
