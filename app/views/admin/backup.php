<?php
require_once __DIR__ . '/../layouts/header.php';

$mode = backup_normalize_mode((string) ($config['mode'] ?? BACKUP_MODE_LOCAL_SYNC));
$isGoogleMode = $mode === BACKUP_MODE_GOOGLE_DRIVE;
$lastResult = (string) ($config['last_result'] ?? '');
$statusBadgeClass = 'badge-neutral bg-slate-100 text-slate-700';
$statusLabel = 'Belum pernah dijalankan';
if ($lastResult === 'success') {
    $statusBadgeClass = 'badge-success';
    $statusLabel = 'Backup sukses';
} elseif ($lastResult === 'error') {
    $statusBadgeClass = 'badge-error animate-pulse';
    $statusLabel = 'Backup gagal';
} elseif ($lastResult === 'running') {
    $statusBadgeClass = 'badge-warning';
    $statusLabel = 'Backup berjalan';
}

$lastFileSize = (int) ($config['last_file_size'] ?? 0);
$lastFileSizeText = $lastFileSize > 0 ? number_format($lastFileSize / 1048576, 2, ',', '.') . ' MB' : '-';
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

<div class="flex flex-col md:flex-row justify-between md:items-end gap-4 mb-6">
    <div>
        <h2 class="font-bold text-slate-900 text-2xl mb-1">Auto Backup Database</h2>
        <p class="text-sm text-muted m-0">Backup SQL otomatis ke folder lokal dan sinkronisasi ke cloud.</p>
    </div>
    <div>
        <span class="badge <?php echo e($statusBadgeClass); ?> px-3 py-1.5 text-xs">
            <i data-lucide="<?php echo $lastResult === 'success' ? 'check-circle' : ($lastResult === 'error' ? 'alert-triangle' : ($lastResult === 'running' ? 'loader' : 'help-circle')); ?>" class="w-3.5 h-3.5 mr-1.5 inline"></i>
            <?php echo e($statusLabel); ?>
        </span>
    </div>
</div>

<div class="card p-6 mb-6">
    <form method="POST" class="space-y-6" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>

        <div class="p-4 rounded-xl border <?php echo !empty($config['enabled']) ? 'bg-blue-50/50 border-blue-200' : 'bg-slate-50 border-slate-200'; ?>">
            <label class="flex items-start gap-3 cursor-pointer m-0">
                <input class="form-check-input mt-1" type="checkbox" id="enabled" name="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
                <div>
                    <span class="font-bold text-slate-900 block">Aktifkan Auto-Backup</span>
                    <span class="text-xs text-slate-600 mt-1 block">Backup berjalan otomatis saat cron dipanggil atau saat panel superadmin diakses.</span>
                </div>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="form-group mb-0">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="mode">Mode Backup</label>
                <select class="form-input w-full" id="mode" name="mode">
                    <option value="<?php echo e(BACKUP_MODE_LOCAL_SYNC); ?>" <?php echo $mode === BACKUP_MODE_LOCAL_SYNC ? 'selected' : ''; ?>>Local Folder Sync (Rekomendasi)</option>
                    <option value="<?php echo e(BACKUP_MODE_GOOGLE_DRIVE); ?>" <?php echo $mode === BACKUP_MODE_GOOGLE_DRIVE ? 'selected' : ''; ?>>Google Drive API (Service Account)</option>
                </select>
                <div class="text-[11px] text-muted mt-1.5">Mode lokal tidak membutuhkan konfigurasi API atau file JSON.</div>
            </div>

            <div class="form-group mb-0">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="interval_minutes">Interval Backup (Menit)</label>
                <input class="form-input w-full" type="number" min="5" max="1440" step="1" id="interval_minutes" name="interval_minutes" value="<?php echo e((string) ($config['interval_minutes'] ?? BACKUP_DEFAULT_INTERVAL_MINUTES)); ?>">
                <div class="text-[11px] text-muted mt-1.5">Contoh: `60` untuk satu jam sekali.</div>
            </div>

            <div class="form-group mb-0">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="backup_directory">Folder Backup Lokal</label>
                <input class="form-input w-full" type="text" id="backup_directory" name="backup_directory" value="<?php echo e((string) ($config['backup_directory'] ?? BACKUP_DEFAULT_DIRECTORY)); ?>" placeholder="backup">
                <div class="text-[11px] text-muted mt-1.5">Relatif dari root project, contoh: `backup`.</div>
            </div>

            <div class="form-group mb-0">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="filename_prefix">Prefix Nama File</label>
                <input class="form-input w-full" type="text" id="filename_prefix" name="filename_prefix" value="<?php echo e((string) ($config['filename_prefix'] ?? 'kaspindo-db-backup')); ?>">
                <div class="text-[11px] text-muted mt-1.5">Awalan file, contoh: `kaspindo-db-backup`.</div>
            </div>

            <div class="form-group mb-0">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="local_retention_days">Retensi Lokal (Hari)</label>
                <input class="form-input w-full" type="number" min="1" max="365" id="local_retention_days" name="local_retention_days" value="<?php echo e((string) ($config['local_retention_days'] ?? 7)); ?>">
                <div class="text-[11px] text-muted mt-1.5">Jumlah hari file backup akan disimpan.</div>
            </div>

            <!-- Google Drive Specific Fields -->
            <div class="form-group mb-0 js-drive-field" style="<?php echo $isGoogleMode ? '' : 'display:none;'; ?>">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="schedule_time">Jam Backup Harian (Mode Google)</label>
                <input class="form-input w-full" type="time" id="schedule_time" name="schedule_time" value="<?php echo e((string) ($config['schedule_time'] ?? '02:00')); ?>">
                <div class="text-[11px] text-muted mt-1.5">Timezone aplikasi: Asia/Jakarta.</div>
            </div>

            <div class="form-group mb-0 js-drive-field md:col-span-2" style="<?php echo $isGoogleMode ? '' : 'display:none;'; ?>">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="drive_folder">Folder Google Drive</label>
                <input class="form-input w-full" type="text" id="drive_folder" name="drive_folder" value="<?php echo e((string) ($config['drive_folder_id'] ?? '')); ?>" placeholder="Paste link folder atau folder ID">
                <div class="text-[11px] text-muted mt-1.5">Isi dengan ID folder Drive atau link folder.</div>
            </div>

            <div class="form-group mb-0 js-drive-field" style="<?php echo $isGoogleMode ? '' : 'display:none;'; ?>">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="service_account_path">Path Service Account JSON</label>
                <input class="form-input w-full" type="text" id="service_account_path" name="service_account_path" value="<?php echo e((string) ($config['service_account_path'] ?? '')); ?>" placeholder="storage/keys/google-service-account.json">
                <div class="text-[11px] text-muted mt-1.5">Path absolut atau relatif dari root project.</div>
            </div>

            <div class="form-group mb-0 js-drive-field" style="<?php echo $isGoogleMode ? '' : 'display:none;'; ?>">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="service_account_file">Upload Service Account JSON</label>
                <input class="form-input w-full py-1.5 text-sm" type="file" id="service_account_file" name="service_account_file" accept=".json,application/json">
                <div class="text-[11px] text-muted mt-1.5">Akan ditimpa jika sudah ada.</div>
            </div>

            <div class="form-group mb-0 js-drive-field" style="<?php echo $isGoogleMode ? '' : 'display:none;'; ?>">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-muted" for="remote_retention_files">Retensi File Drive (Total File)</label>
                <input class="form-input w-full" type="number" min="1" max="200" id="remote_retention_files" name="remote_retention_files" value="<?php echo e((string) ($config['remote_retention_files'] ?? 30)); ?>">
                <div class="text-[11px] text-muted mt-1.5">Berapa banyak file yang disimpan di Drive.</div>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-200 flex flex-wrap gap-3">
            <button class="btn btn-primary" type="submit" name="action" value="save">
                <i data-lucide="save" class="w-4 h-4"></i>
                Simpan Pengaturan
            </button>
            <button class="btn btn-secondary js-drive-test-btn" type="submit" name="action" value="test_drive" <?php echo $isGoogleMode ? '' : 'disabled'; ?>>
                <i data-lucide="cloud-lightning" class="w-4 h-4"></i>
                Tes Drive
            </button>
            <button class="btn btn-secondary" type="submit" name="action" value="run_now">
                <i data-lucide="play" class="w-4 h-4"></i>
                Jalankan Backup
            </button>
            <button class="btn btn-secondary text-red-600 hover:bg-red-50 hover:border-red-200" type="submit" name="action" value="regen_token">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                Reset Token Cron
            </button>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="card p-6 border-t-4 border-t-blue-500">
        <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide mb-4">Status Terakhir</h3>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between pb-2 border-b border-slate-100">
                <span class="text-muted">Waktu attempt:</span>
                <span class="font-medium"><?php echo e((string) ($config['last_attempt_at'] ?: '-')); ?></span>
            </div>
            <div class="flex justify-between pb-2 border-b border-slate-100">
                <span class="text-muted">Waktu sukses:</span>
                <span class="font-medium"><?php echo e((string) ($config['last_success_at'] ?: '-')); ?></span>
            </div>
            <div class="flex justify-between pb-2 border-b border-slate-100">
                <span class="text-muted">Pesan:</span>
                <span class="font-medium text-right max-w-[200px] truncate"><?php echo e((string) ($config['last_message'] ?: '-')); ?></span>
            </div>
            <div class="flex justify-between pb-2 border-b border-slate-100">
                <span class="text-muted">File:</span>
                <span class="font-medium text-right max-w-[200px] truncate"><?php echo e((string) ($config['last_file_name'] ?: '-')); ?></span>
            </div>
            <div class="flex justify-between pb-2 border-b border-slate-100">
                <span class="text-muted">Ukuran:</span>
                <span class="font-medium"><?php echo e($lastFileSizeText); ?></span>
            </div>
            <div class="flex justify-between pb-2 border-b border-slate-100">
                <span class="text-muted">Mode:</span>
                <span class="font-medium"><?php echo e($mode === BACKUP_MODE_GOOGLE_DRIVE ? 'Google Drive API' : 'Local Folder Sync'); ?></span>
            </div>
            <div class="flex justify-between pb-2 border-b border-slate-100">
                <span class="text-muted">Drive File ID:</span>
                <span class="font-medium text-right max-w-[200px] truncate"><?php echo e((string) ($config['last_drive_file_id'] ?: '-')); ?></span>
            </div>
            <div class="pt-1">
                <span class="text-xs text-muted block mb-1">Folder Lokal Absolut:</span>
                <code class="text-[10px] bg-slate-100 px-2 py-1 rounded block text-slate-600 break-all"><?php echo e($backupDirectoryAbsolute); ?></code>
            </div>
        </div>
    </div>

    <div class="card p-6 border-t-4 border-t-emerald-500">
        <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wide mb-4">Cron Endpoint</h3>
        <div class="space-y-4 text-sm">
            <div>
                <span class="text-muted block mb-1">URL Cron / Webhook:</span>
                <code class="text-xs bg-slate-100 px-2 py-1 rounded block text-slate-800 break-all border border-slate-200"><?php echo e($cronUrl); ?></code>
            </div>
            <div class="flex justify-between pb-2 border-b border-slate-100">
                <span class="text-muted">Service Account Email:</span>
                <span class="font-medium"><?php echo e($serviceAccountEmail !== '' ? $serviceAccountEmail : '-'); ?></span>
            </div>
            <div>
                <span class="text-muted block mb-1">Path JSON Absolut:</span>
                <code class="text-[10px] bg-slate-100 px-2 py-1 rounded block text-slate-600 break-all"><?php echo e($serviceAccountPathAbsolute); ?></code>
            </div>

            <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 mt-4">
                <p class="text-xs text-blue-800 font-bold mb-1">Panduan Penggunaan:</p>
                <p class="text-[11px] text-blue-700 leading-relaxed mb-2">
                    Untuk menjalankan backup otomatis di latar belakang, gunakan Windows Task Scheduler dan buat task yang dijalankan setiap 5 menit dengan script PowerShell berikut:
                </p>
                <code class="text-[10px] bg-white px-2 py-1 rounded block text-slate-800 border border-blue-200 break-all font-mono">
                    powershell -Command "Invoke-WebRequest -Uri '<?php echo e($cronUrl); ?>' -UseBasicParsing | Out-Null"
                </code>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const modeInput = document.getElementById('mode');
        if (!modeInput) {
            return;
        }

        const driveFields = document.querySelectorAll('.js-drive-field');
        const driveTestBtn = document.querySelector('.js-drive-test-btn');
        const googleMode = '<?php echo e(BACKUP_MODE_GOOGLE_DRIVE); ?>';

        function refreshModeFields() {
            const isGoogle = modeInput.value === googleMode;
            driveFields.forEach((field) => {
                field.style.display = isGoogle ? '' : 'none';
            });
            if (driveTestBtn) {
                driveTestBtn.disabled = !isGoogle;
            }
        }

        modeInput.addEventListener('change', refreshModeFields);
        refreshModeFields();
    })();
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
