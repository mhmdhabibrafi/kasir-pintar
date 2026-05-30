<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/backup_helper.php';

require_role(['superadmin']);

$errors = [];
$success = '';
$config = backup_get_config();

/**
 * @return string|null Relative path service account jika upload berhasil.
 */
function backup_handle_service_account_upload(array &$errors): ?string
{
    if (!isset($_FILES['service_account_file']) || !is_array($_FILES['service_account_file'])) {
        return null;
    }

    $file = $_FILES['service_account_file'];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload file service account gagal.';
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'json') {
        $errors[] = 'File service account harus format .json';
        return null;
    }

    $content = file_get_contents($tmpName);
    if ($content === false || trim($content) === '') {
        $errors[] = 'File service account kosong atau tidak bisa dibaca.';
        return null;
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
        $errors[] = 'Isi file JSON service account tidak valid.';
        return null;
    }

    $targetRelative = BACKUP_DEFAULT_SERVICE_ACCOUNT_PATH;
    $targetAbsolute = backup_service_account_full_path($targetRelative);
    $targetDir = dirname($targetAbsolute);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        $errors[] = 'Folder penyimpanan service account tidak bisa dibuat.';
        return null;
    }

    $written = file_put_contents($targetAbsolute, $content, LOCK_EX);
    if ($written === false) {
        $errors[] = 'Gagal menyimpan file service account.';
        return null;
    }

    return $targetRelative;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        $uploadedServicePath = backup_handle_service_account_upload($errors);
        $uploadMessage = $uploadedServicePath !== null ? 'File service account berhasil diupload.' : '';
        try {
            if ($action === 'save') {
                $mode = backup_normalize_mode((string) ($_POST['mode'] ?? ($config['mode'] ?? BACKUP_MODE_LOCAL_SYNC)));
                $enabled = isset($_POST['enabled']);
                $scheduleTime = trim((string) ($_POST['schedule_time'] ?? '02:00'));
                $intervalMinutes = (int) ($_POST['interval_minutes'] ?? BACKUP_DEFAULT_INTERVAL_MINUTES);
                $backupDirectory = trim((string) ($_POST['backup_directory'] ?? BACKUP_DEFAULT_DIRECTORY));
                $driveFolderRaw = trim((string) ($_POST['drive_folder'] ?? ''));
                $serviceAccountPath = trim((string) ($_POST['service_account_path'] ?? BACKUP_DEFAULT_SERVICE_ACCOUNT_PATH));
                if ($uploadedServicePath !== null) {
                    $serviceAccountPath = $uploadedServicePath;
                }
                $localRetention = (int) ($_POST['local_retention_days'] ?? 7);
                $remoteRetention = (int) ($_POST['remote_retention_files'] ?? 30);
                $filenamePrefix = trim((string) ($_POST['filename_prefix'] ?? BACKUP_DEFAULT_PREFIX));

                if ($mode === BACKUP_MODE_GOOGLE_DRIVE && !preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $scheduleTime)) {
                    $errors[] = 'Format jam backup harus HH:MM (contoh: 02:00).';
                }
                if ($intervalMinutes < 5 || $intervalMinutes > 1440) {
                    $errors[] = 'Interval backup harus antara 5 sampai 1440 menit.';
                }

                $driveFolderId = backup_extract_drive_folder_id($driveFolderRaw);
                if ($driveFolderId === '') {
                    $driveFolderId = backup_extract_drive_folder_id((string) ($_POST['drive_folder_id'] ?? ''));
                }

                if ($backupDirectory === '') {
                    $errors[] = 'Folder backup lokal wajib diisi.';
                }

                if ($enabled && $mode === BACKUP_MODE_GOOGLE_DRIVE && $driveFolderId === '') {
                    $errors[] = 'Folder Google Drive wajib diisi jika auto-backup diaktifkan.';
                }
                if ($enabled && $mode === BACKUP_MODE_GOOGLE_DRIVE && $serviceAccountPath === '') {
                    $errors[] = 'Path file service account wajib diisi jika auto-backup diaktifkan.';
                }
                if ($localRetention < 1 || $localRetention > 365) {
                    $errors[] = 'Retensi backup lokal harus antara 1 sampai 365 hari.';
                }
                if ($remoteRetention < 1 || $remoteRetention > 200) {
                    $errors[] = 'Retensi backup Google Drive harus antara 1 sampai 200 file.';
                }

                if (empty($errors)) {
                    $config = backup_save_config([
                        'mode' => $mode,
                        'enabled' => $enabled,
                        'schedule_time' => $scheduleTime,
                        'interval_minutes' => $intervalMinutes,
                        'backup_directory' => $backupDirectory,
                        'drive_folder_id' => $driveFolderId,
                        'service_account_path' => $serviceAccountPath,
                        'local_retention_days' => $localRetention,
                        'remote_retention_files' => $remoteRetention,
                        'filename_prefix' => $filenamePrefix,
                    ]);
                    $success = trim($uploadMessage . ' Pengaturan auto-backup berhasil disimpan.');
                }
            } elseif ($action === 'test_drive') {
                $postedMode = backup_normalize_mode((string) ($_POST['mode'] ?? ($config['mode'] ?? BACKUP_MODE_LOCAL_SYNC)));
                if ($postedMode !== BACKUP_MODE_GOOGLE_DRIVE) {
                    $errors[] = 'Tes koneksi Drive hanya tersedia saat mode Google Drive API.';
                }

                $driveFolderRaw = trim((string) ($_POST['drive_folder'] ?? ''));
                $driveFolderId = backup_extract_drive_folder_id($driveFolderRaw);
                if ($driveFolderId === '') {
                    $driveFolderId = (string) ($config['drive_folder_id'] ?? '');
                }

                $serviceAccountPath = trim((string) ($_POST['service_account_path'] ?? ''));
                if ($uploadedServicePath !== null) {
                    $serviceAccountPath = $uploadedServicePath;
                }
                if ($serviceAccountPath === '') {
                    $serviceAccountPath = (string) ($config['service_account_path'] ?? BACKUP_DEFAULT_SERVICE_ACCOUNT_PATH);
                }

                $testConfig = array_merge($config, [
                    'mode' => $postedMode,
                    'drive_folder_id' => $driveFolderId,
                    'service_account_path' => $serviceAccountPath,
                ]);

                if (empty($errors)) {
                    $result = backup_test_drive_connection($testConfig);
                    if (!empty($result['ok'])) {
                        $success = trim($uploadMessage . ' ' . (string) ($result['message'] ?? 'Koneksi Google Drive berhasil.'));
                    } else {
                        $errors[] = (string) ($result['message'] ?? 'Koneksi Google Drive gagal.');
                    }
                }
            } elseif ($action === 'run_now') {
                $result = backup_run([
                    'force' => true,
                    'trigger' => 'superadmin_manual',
                ]);
                if (!empty($result['ok'])) {
                    $success = (string) ($result['message'] ?? 'Backup selesai.');
                } else {
                    $errors[] = (string) ($result['message'] ?? 'Backup gagal.');
                }
                $config = backup_get_config();
            } elseif ($action === 'regen_token') {
                $config = backup_save_config([
                    'cron_token' => backup_generate_cron_token(),
                ]);
                $success = 'Token cron berhasil diganti.';
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage() !== ''
                ? $e->getMessage()
                : 'Terjadi kesalahan saat memproses backup.';
        }
    }
}

$config = backup_get_config();
$cronUrl = base_url('cron_backup.php?token=' . rawurlencode((string) ($config['cron_token'] ?? '')));
$serviceAccountPathAbsolute = backup_service_account_full_path((string) ($config['service_account_path'] ?? ''));
$serviceAccountEmail = backup_service_account_email($config);
$backupDirectoryAbsolute = backup_directory_full_path($config);

$title = 'Auto Backup Database';
require_once __DIR__ . '/../app/views/admin/backup.php';
