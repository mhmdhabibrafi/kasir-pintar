<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/data_store.php';

const BACKUP_CONFIG_FILENAME = 'backup_config.json';
const BACKUP_DEFAULT_SERVICE_ACCOUNT_PATH = 'storage/keys/google-service-account.json';
const BACKUP_DEFAULT_PREFIX = 'kaspindo-db-backup';
const BACKUP_MODE_LOCAL_SYNC = 'local_sync';
const BACKUP_MODE_GOOGLE_DRIVE = 'google_drive';
const BACKUP_DEFAULT_INTERVAL_MINUTES = 5;
const BACKUP_DEFAULT_DIRECTORY = 'backup';

function backup_project_root(): string
{
    return dirname(__DIR__, 2);
}

function backup_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now');
}

function backup_default_config(): array
{
    return [
        'mode' => BACKUP_MODE_LOCAL_SYNC,
        'enabled' => false,
        'schedule_time' => '02:00',
        'interval_minutes' => BACKUP_DEFAULT_INTERVAL_MINUTES,
        'backup_directory' => BACKUP_DEFAULT_DIRECTORY,
        'drive_folder_id' => '',
        'service_account_path' => BACKUP_DEFAULT_SERVICE_ACCOUNT_PATH,
        'local_retention_days' => 7,
        'remote_retention_files' => 30,
        'filename_prefix' => BACKUP_DEFAULT_PREFIX,
        'cron_token' => backup_generate_cron_token(),
        'last_attempt_at' => '',
        'last_success_at' => '',
        'last_result' => '',
        'last_message' => '',
        'last_file_name' => '',
        'last_file_size' => 0,
        'last_drive_file_id' => '',
        'updated_at' => '',
    ];
}

function backup_generate_cron_token(): string
{
    return bin2hex(random_bytes(24));
}

function backup_get_config(): array
{
    $stored = data_store_read(BACKUP_CONFIG_FILENAME, []);
    $config = backup_sanitize_config(array_merge(backup_default_config(), $stored), false);

    if (($stored['cron_token'] ?? '') !== $config['cron_token']) {
        data_store_write(BACKUP_CONFIG_FILENAME, $config);
    }

    return $config;
}

function backup_save_config(array $changes): array
{
    $current = backup_get_config();
    $merged = array_merge($current, $changes);
    $sanitized = backup_sanitize_config($merged, true);
    data_store_write(BACKUP_CONFIG_FILENAME, $sanitized);
    return $sanitized;
}

function backup_sanitize_config(array $config, bool $touchUpdatedAt): array
{
    $defaults = backup_default_config();

    $mode = backup_normalize_mode((string) ($config['mode'] ?? $defaults['mode']));
    $scheduleTime = backup_normalize_schedule_time((string) ($config['schedule_time'] ?? $defaults['schedule_time']));
    $intervalMinutes = backup_normalize_interval_minutes((int) ($config['interval_minutes'] ?? $defaults['interval_minutes']));
    $backupDirectory = backup_normalize_backup_directory((string) ($config['backup_directory'] ?? $defaults['backup_directory']));
    $driveFolderId = backup_normalize_drive_folder_id((string) ($config['drive_folder_id'] ?? ''));
    $servicePath = trim((string) ($config['service_account_path'] ?? $defaults['service_account_path']));
    if ($servicePath === '') {
        $servicePath = $defaults['service_account_path'];
    }

    $prefix = backup_normalize_prefix((string) ($config['filename_prefix'] ?? $defaults['filename_prefix']));

    $localRetention = (int) ($config['local_retention_days'] ?? $defaults['local_retention_days']);
    $localRetention = max(1, min(365, $localRetention));

    $remoteRetention = (int) ($config['remote_retention_files'] ?? $defaults['remote_retention_files']);
    $remoteRetention = max(1, min(200, $remoteRetention));

    $cronToken = trim((string) ($config['cron_token'] ?? ''));
    if ($cronToken === '') {
        $cronToken = backup_generate_cron_token();
    }

    $updatedAt = (string) ($config['updated_at'] ?? '');
    if ($touchUpdatedAt || $updatedAt === '') {
        $updatedAt = backup_now()->format('Y-m-d H:i:s');
    }

    return [
        'mode' => $mode,
        'enabled' => !empty($config['enabled']),
        'schedule_time' => $scheduleTime,
        'interval_minutes' => $intervalMinutes,
        'backup_directory' => $backupDirectory,
        'drive_folder_id' => $driveFolderId,
        'service_account_path' => str_replace('\\', '/', $servicePath),
        'local_retention_days' => $localRetention,
        'remote_retention_files' => $remoteRetention,
        'filename_prefix' => $prefix,
        'cron_token' => $cronToken,
        'last_attempt_at' => backup_normalize_datetime((string) ($config['last_attempt_at'] ?? '')),
        'last_success_at' => backup_normalize_datetime((string) ($config['last_success_at'] ?? '')),
        'last_result' => backup_normalize_result((string) ($config['last_result'] ?? '')),
        'last_message' => trim((string) ($config['last_message'] ?? '')),
        'last_file_name' => trim((string) ($config['last_file_name'] ?? '')),
        'last_file_size' => max(0, (int) ($config['last_file_size'] ?? 0)),
        'last_drive_file_id' => trim((string) ($config['last_drive_file_id'] ?? '')),
        'updated_at' => $updatedAt,
    ];
}

function backup_normalize_mode(string $value): string
{
    $value = trim(strtolower($value));
    if (in_array($value, [BACKUP_MODE_LOCAL_SYNC, BACKUP_MODE_GOOGLE_DRIVE], true)) {
        return $value;
    }
    return BACKUP_MODE_LOCAL_SYNC;
}

function backup_normalize_interval_minutes(int $value): int
{
    if ($value < 5) {
        return 5;
    }
    if ($value > 1440) {
        return 1440;
    }
    return $value;
}

function backup_normalize_backup_directory(string $value): string
{
    $value = trim(str_replace('\\', '/', $value));
    if ($value === '') {
        return BACKUP_DEFAULT_DIRECTORY;
    }

    $value = trim($value, '/');
    $value = (string) preg_replace('/[^A-Za-z0-9._\/-]+/', '-', $value);
    $value = str_replace('..', '', $value);
    $value = trim($value, '/');

    return $value === '' ? BACKUP_DEFAULT_DIRECTORY : $value;
}

function backup_directory_full_path(array $config): string
{
    $relative = backup_normalize_backup_directory((string) ($config['backup_directory'] ?? BACKUP_DEFAULT_DIRECTORY));
    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative);
    return backup_project_root() . DIRECTORY_SEPARATOR . $normalized;
}

function backup_normalize_result(string $value): string
{
    $value = trim(strtolower($value));
    if (in_array($value, ['success', 'error', 'running', 'skipped'], true)) {
        return $value;
    }
    return '';
}

function backup_normalize_datetime(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function backup_normalize_schedule_time(string $value): string
{
    $value = trim($value);
    if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $value)) {
        return '02:00';
    }
    return $value;
}

function backup_normalize_prefix(string $value): string
{
    $value = trim($value);
    $value = (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $value);
    $value = trim($value, '-_');

    return $value === '' ? BACKUP_DEFAULT_PREFIX : $value;
}

function backup_normalize_drive_folder_id(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $extracted = backup_extract_drive_folder_id($value);
    if ($extracted !== '') {
        return $extracted;
    }

    return (string) preg_replace('/[^A-Za-z0-9_-]/', '', $value);
}

function backup_extract_drive_folder_id(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('~drive\.google\.com/drive/folders/([A-Za-z0-9_-]+)~', $value, $matches)) {
        return $matches[1];
    }

    if (preg_match('~[?&]id=([A-Za-z0-9_-]+)~', $value, $matches)) {
        return $matches[1];
    }

    if (preg_match('/^[A-Za-z0-9_-]{10,}$/', $value) === 1) {
        return $value;
    }

    return '';
}

function backup_service_account_full_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    $isWindowsAbsolute = preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    $isUnixAbsolute = str_starts_with($path, '/');

    if ($isWindowsAbsolute || $isUnixAbsolute) {
        return $normalized;
    }

    return backup_project_root() . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
}

function backup_service_account_email(array $config): string
{
    try {
        $account = backup_load_service_account($config);
        return (string) ($account['client_email'] ?? '');
    } catch (Throwable $e) {
        return '';
    }
}

function backup_parse_datetime(?string $value): ?DateTimeImmutable
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Throwable $e) {
        return null;
    }
}

function backup_should_run_now(array $config, ?DateTimeImmutable $now = null): bool
{
    if (empty($config['enabled'])) {
        return false;
    }

    $now = $now ?? backup_now();
    $mode = backup_normalize_mode((string) ($config['mode'] ?? BACKUP_MODE_LOCAL_SYNC));

    if ($mode === BACKUP_MODE_LOCAL_SYNC) {
        $intervalMinutes = backup_normalize_interval_minutes((int) ($config['interval_minutes'] ?? BACKUP_DEFAULT_INTERVAL_MINUTES));
        $lastRunRef = backup_parse_datetime((string) ($config['last_attempt_at'] ?? ''));
        if ($lastRunRef === null) {
            $lastRunRef = backup_parse_datetime((string) ($config['last_success_at'] ?? ''));
        }

        if ($lastRunRef === null) {
            return true;
        }

        $elapsedSeconds = $now->getTimestamp() - $lastRunRef->getTimestamp();
        return $elapsedSeconds >= ($intervalMinutes * 60);
    }

    $scheduleTime = backup_normalize_schedule_time((string) ($config['schedule_time'] ?? '02:00'));
    $scheduledAt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $scheduleTime);
    if (!$scheduledAt || $now < $scheduledAt) {
        return false;
    }

    $lastSuccess = backup_parse_datetime((string) ($config['last_success_at'] ?? ''));
    if ($lastSuccess !== null && $lastSuccess >= $scheduledAt) {
        return false;
    }

    return true;
}

function backup_notify_system_event(array $result): void
{
    $helperPath = __DIR__ . '/telegram_helper.php';
    if (!function_exists('telegram_notify_system_event') && is_file($helperPath)) {
        require_once $helperPath;
    }
    if (!function_exists('telegram_notify_system_event')) {
        return;
    }

    telegram_notify_system_event('backup', [
        'event' => !empty($result['ok']) ? 'Backup berhasil' : 'Backup gagal',
        'status' => (string) ($result['status'] ?? (!empty($result['ok']) ? 'success' : 'error')),
        'mode' => (string) ($result['mode'] ?? '-'),
        'trigger' => (string) ($result['trigger'] ?? '-'),
        'file_name' => (string) ($result['file_name'] ?? ''),
        'note' => (string) ($result['message'] ?? ''),
    ]);
}

function backup_run(array $options = []): array
{
    $force = !empty($options['force']);
    $trigger = (string) ($options['trigger'] ?? 'manual');
    $config = backup_get_config();
    $mode = backup_normalize_mode((string) ($config['mode'] ?? BACKUP_MODE_LOCAL_SYNC));
    $now = backup_now();

    if (!$force && empty($config['enabled'])) {
        return [
            'ok' => false,
            'status' => 'skipped',
            'message' => 'Backup otomatis belum diaktifkan.',
            'trigger' => $trigger,
        ];
    }

    if (!$force && !backup_should_run_now($config, $now)) {
        return [
            'ok' => true,
            'status' => 'skipped',
            'message' => 'Belum masuk jadwal backup.',
            'trigger' => $trigger,
        ];
    }

    $lockError = '';
    $lockHandle = backup_acquire_lock($config, $lockError);
    if (!is_resource($lockHandle)) {
        $message = 'Backup sedang berjalan di proses lain.';
        if ($lockError !== '') {
            $message = $lockError;
        }
        backup_save_config([
            'last_attempt_at' => $now->format('Y-m-d H:i:s'),
            'last_result' => 'error',
            'last_message' => $message,
        ]);
        $result = [
            'ok' => false,
            'status' => 'error',
            'message' => $message,
            'trigger' => $trigger,
            'mode' => $mode,
        ];
        backup_notify_system_event($result);
        return $result;
    }

    backup_save_config([
        'last_attempt_at' => $now->format('Y-m-d H:i:s'),
        'last_result' => 'running',
        'last_message' => 'Backup sedang berjalan...',
    ]);

    try {
        backup_assert_prerequisites($config, $mode);

        $accessToken = '';
        if ($mode === BACKUP_MODE_GOOGLE_DRIVE) {
            $serviceAccount = backup_load_service_account($config);
            $accessToken = backup_request_access_token($serviceAccount);
        }

        $dump = backup_create_database_dump($config);
        $driveFileId = '';
        $warnings = [];
        if ($mode === BACKUP_MODE_GOOGLE_DRIVE) {
            $uploaded = backup_upload_file_to_drive(
                $dump['path'],
                $dump['name'],
                (string) $config['drive_folder_id'],
                $accessToken
            );
            $driveFileId = (string) ($uploaded['id'] ?? '');

            try {
                backup_cleanup_remote_files($config, (string) $config['drive_folder_id'], $accessToken);
            } catch (Throwable $e) {
                $warnings[] = 'retensi Google Drive gagal';
            }
        }

        try {
            backup_cleanup_local_files($config);
        } catch (Throwable $e) {
            $warnings[] = 'retensi lokal gagal';
        }

        $message = $mode === BACKUP_MODE_GOOGLE_DRIVE
            ? 'Backup berhasil diunggah ke Google Drive.'
            : 'Backup berhasil dibuat ke folder lokal.';
        if (!empty($warnings)) {
            $message .= ' Peringatan: ' . implode(', ', $warnings) . '.';
        }

        backup_save_config([
            'last_attempt_at' => $now->format('Y-m-d H:i:s'),
            'last_success_at' => $now->format('Y-m-d H:i:s'),
            'last_result' => 'success',
            'last_message' => $message,
            'last_file_name' => $dump['name'],
            'last_file_size' => $dump['size'],
            'last_drive_file_id' => $driveFileId,
        ]);

        $result = [
            'ok' => true,
            'status' => 'success',
            'message' => $message,
            'trigger' => $trigger,
            'file_name' => $dump['name'],
            'file_size' => $dump['size'],
            'drive_file_id' => $driveFileId,
            'mode' => $mode,
        ];
        backup_notify_system_event($result);
        return $result;
    } catch (Throwable $e) {
        $message = 'Backup gagal: ' . $e->getMessage();
        backup_save_config([
            'last_attempt_at' => $now->format('Y-m-d H:i:s'),
            'last_result' => 'error',
            'last_message' => $message,
        ]);

        $result = [
            'ok' => false,
            'status' => 'error',
            'message' => $message,
            'trigger' => $trigger,
            'mode' => $mode,
        ];
        backup_notify_system_event($result);
        return $result;
    } finally {
        backup_release_lock($lockHandle);
    }
}

function backup_test_drive_connection(array $config): array
{
    $mode = backup_normalize_mode((string) ($config['mode'] ?? BACKUP_MODE_LOCAL_SYNC));
    if ($mode !== BACKUP_MODE_GOOGLE_DRIVE) {
        return [
            'ok' => false,
            'message' => 'Tes koneksi Drive hanya tersedia untuk mode Google Drive API.',
        ];
    }

    try {
        backup_assert_prerequisites($config, $mode);
        $serviceAccount = backup_load_service_account($config);
        $accessToken = backup_request_access_token($serviceAccount);
        $folderId = (string) $config['drive_folder_id'];
        $url = 'https://www.googleapis.com/drive/v3/files/'
            . rawurlencode($folderId)
            . '?fields=id,name,mimeType&supportsAllDrives=true';

        [$status, $body] = backup_http_request('GET', $url, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        $payload = json_decode($body, true);
        if ($status < 200 || $status >= 300 || !is_array($payload)) {
            throw new RuntimeException('Folder Google Drive tidak bisa diakses.');
        }

        if (($payload['mimeType'] ?? '') !== 'application/vnd.google-apps.folder') {
            throw new RuntimeException('ID yang diberikan bukan folder Google Drive.');
        }

        return [
            'ok' => true,
            'message' => 'Koneksi Google Drive berhasil. Folder: ' . (string) ($payload['name'] ?? 'Unknown'),
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => 'Koneksi Google Drive gagal: ' . $e->getMessage(),
        ];
    }
}

function backup_assert_prerequisites(array $config, ?string $mode = null): void
{
    $mode = backup_normalize_mode((string) ($mode ?? ($config['mode'] ?? BACKUP_MODE_LOCAL_SYNC)));

    if ($mode === BACKUP_MODE_GOOGLE_DRIVE) {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Ekstensi PHP cURL belum aktif.');
        }

        if (!function_exists('openssl_sign')) {
            throw new RuntimeException('Ekstensi PHP OpenSSL belum aktif.');
        }

        if (trim((string) ($config['drive_folder_id'] ?? '')) === '') {
            throw new RuntimeException('Folder Google Drive belum diisi.');
        }

        $path = backup_service_account_full_path((string) ($config['service_account_path'] ?? ''));
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('File service account JSON tidak ditemukan: ' . $path);
        }
    }

    $dir = backup_directory_full_path($config);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder backup tidak bisa dibuat: ' . $dir);
    }
}

function backup_acquire_lock(array $config, string &$error = ''): mixed
{
    $dir = backup_directory_full_path($config);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        $error = 'Folder backup tidak bisa dibuat.';
        return null;
    }

    $lockFile = $dir . DIRECTORY_SEPARATOR . '.backup.lock';
    $handle = fopen($lockFile, 'c+');
    if ($handle === false) {
        $error = 'Lock backup tidak bisa dibuat.';
        return null;
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        $error = 'Backup sedang berjalan di proses lain.';
        return null;
    }

    ftruncate($handle, 0);
    fwrite($handle, (string) getmypid());
    fflush($handle);

    return $handle;
}

function backup_release_lock($handle): void
{
    if (!is_resource($handle)) {
        return;
    }
    flock($handle, LOCK_UN);
    fclose($handle);
}

function backup_create_database_dump(array $config): array
{
    $backupDir = backup_directory_full_path($config);
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Folder backup tidak bisa dibuat.');
    }

    $pdo = db();
    $dbName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: 'database');
    $timestamp = backup_now()->format('Ymd-His');
    $safeDbName = (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $dbName);
    $filename = backup_normalize_prefix((string) ($config['filename_prefix'] ?? BACKUP_DEFAULT_PREFIX))
        . '-' . $safeDbName . '-' . $timestamp . '.sql';
    $fullPath = $backupDir . DIRECTORY_SEPARATOR . $filename;

    backup_write_dump_sql($pdo, $dbName, $fullPath);
    $size = filesize($fullPath);
    if ($size === false) {
        $size = 0;
    }

    return [
        'name' => $filename,
        'path' => $fullPath,
        'size' => (int) $size,
    ];
}

function backup_write_dump_sql(PDO $pdo, string $dbName, string $targetPath): void
{
    $fp = fopen($targetPath, 'wb');
    if ($fp === false) {
        throw new RuntimeException('Gagal membuat file SQL backup.');
    }

    try {
        backup_file_write($fp, "-- KASPINDO Database Backup\n");
        backup_file_write($fp, '-- Generated at: ' . backup_now()->format('Y-m-d H:i:s') . "\n");
        backup_file_write($fp, '-- Database: ' . $dbName . "\n\n");
        backup_file_write($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
        backup_file_write($fp, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
        backup_file_write($fp, "SET NAMES utf8mb4;\n\n");

        $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = [];
        while (($row = $tablesStmt->fetch(PDO::FETCH_NUM)) !== false) {
            if (!empty($row[0])) {
                $tables[] = (string) $row[0];
            }
        }

        foreach ($tables as $table) {
            $tableEscaped = str_replace('`', '``', $table);
            $tableRef = '`' . $tableEscaped . '`';

            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $tableRef);
            $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
            if (!$createRow) {
                continue;
            }

            $createSql = (string) ($createRow['Create Table'] ?? array_values($createRow)[1] ?? '');
            if ($createSql === '') {
                continue;
            }

            backup_file_write($fp, '-- --------------------------------------------------------' . "\n");
            backup_file_write($fp, '-- Table structure for ' . $tableRef . "\n");
            backup_file_write($fp, 'DROP TABLE IF EXISTS ' . $tableRef . ";\n");
            backup_file_write($fp, $createSql . ";\n\n");

            $rowsStmt = $pdo->query('SELECT * FROM ' . $tableRef);
            $columns = null;
            while (($rowData = $rowsStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                if ($columns === null) {
                    $columns = array_map(
                        static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`',
                        array_keys($rowData)
                    );
                }

                $values = [];
                foreach ($rowData as $value) {
                    $values[] = backup_sql_value($pdo, $value);
                }

                backup_file_write(
                    $fp,
                    'INSERT INTO ' . $tableRef
                    . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n"
                );
            }

            backup_file_write($fp, "\n");
        }

        backup_file_write($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    } finally {
        fclose($fp);
    }
}

function backup_file_write($handle, string $text): void
{
    if (fwrite($handle, $text) === false) {
        throw new RuntimeException('Gagal menulis isi backup.');
    }
}

function backup_sql_value(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    $text = (string) $value;
    $quoted = $pdo->quote($text);
    if ($quoted !== false) {
        return $quoted;
    }

    return '0x' . bin2hex($text);
}

function backup_load_service_account(array $config): array
{
    $path = backup_service_account_full_path((string) ($config['service_account_path'] ?? ''));
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        throw new RuntimeException('File service account tidak ditemukan.');
    }

    $json = file_get_contents($path);
    if ($json === false || trim($json) === '') {
        throw new RuntimeException('File service account tidak bisa dibaca.');
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new RuntimeException('Format JSON service account tidak valid.');
    }

    foreach (['client_email', 'private_key'] as $required) {
        if (trim((string) ($data[$required] ?? '')) === '') {
            throw new RuntimeException('Field "' . $required . '" tidak ada pada service account.');
        }
    }

    $data['token_uri'] = (string) ($data['token_uri'] ?? 'https://oauth2.googleapis.com/token');
    return $data;
}

function backup_request_access_token(array $serviceAccount): string
{
    $jwt = backup_create_signed_jwt($serviceAccount);
    $body = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);

    [$status, $response] = backup_http_request(
        'POST',
        (string) $serviceAccount['token_uri'],
        ['Content-Type: application/x-www-form-urlencoded'],
        $body
    );

    $payload = json_decode($response, true);
    if ($status < 200 || $status >= 300 || !is_array($payload) || empty($payload['access_token'])) {
        $errorDescription = is_array($payload) ? (string) ($payload['error_description'] ?? $payload['error'] ?? '') : '';
        throw new RuntimeException('Gagal mendapatkan access token Google: ' . ($errorDescription !== '' ? $errorDescription : 'unknown error'));
    }

    return (string) $payload['access_token'];
}

function backup_create_signed_jwt(array $serviceAccount): string
{
    $header = backup_base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $now = time();
    $claims = backup_base64url_encode(json_encode([
        'iss' => (string) $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive',
        'aud' => (string) $serviceAccount['token_uri'],
        'iat' => $now,
        'exp' => $now + 3600,
    ]));

    $signingInput = $header . '.' . $claims;
    $privateKey = (string) $serviceAccount['private_key'];
    $signature = '';
    $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$signed) {
        throw new RuntimeException('Gagal menandatangani JWT service account.');
    }

    return $signingInput . '.' . backup_base64url_encode($signature);
}

function backup_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function backup_http_request(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Tidak bisa memulai request HTTP.');
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Request HTTP gagal: ' . $error);
    }

    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [$status, (string) $response];
}

function backup_upload_file_to_drive(string $filePath, string $fileName, string $folderId, string $accessToken): array
{
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new RuntimeException('File backup tidak bisa dibaca untuk upload.');
    }

    $metadata = json_encode([
        'name' => $fileName,
        'parents' => [$folderId],
    ]);
    if ($metadata === false) {
        throw new RuntimeException('Metadata Google Drive tidak valid.');
    }

    $mimeType = str_ends_with(strtolower($fileName), '.sql.gz') ? 'application/gzip' : 'application/sql';
    $boundary = 'kaspindo_' . bin2hex(random_bytes(10));
    $body = ''
        . '--' . $boundary . "\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $metadata . "\r\n"
        . '--' . $boundary . "\r\n"
        . 'Content-Type: ' . $mimeType . "\r\n\r\n"
        . $content . "\r\n"
        . '--' . $boundary . "--";

    [$status, $response] = backup_http_request(
        'POST',
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true',
        [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
        ],
        $body
    );

    $payload = json_decode($response, true);
    if ($status < 200 || $status >= 300 || !is_array($payload) || empty($payload['id'])) {
        $message = is_array($payload) ? (string) ($payload['error']['message'] ?? $payload['message'] ?? '') : '';
        throw new RuntimeException('Upload ke Google Drive gagal' . ($message !== '' ? ': ' . $message : '.'));
    }

    return $payload;
}

function backup_cleanup_local_files(array $config): void
{
    $days = max(1, (int) ($config['local_retention_days'] ?? 7));
    $prefix = backup_normalize_prefix((string) ($config['filename_prefix'] ?? BACKUP_DEFAULT_PREFIX));
    $dir = backup_directory_full_path($config);
    if (!is_dir($dir)) {
        return;
    }

    $cutoff = time() - ($days * 86400);
    $files = [];
    foreach (['*.sql', '*.sql.gz'] as $suffixPattern) {
        $pattern = $dir . DIRECTORY_SEPARATOR . $prefix . '-' . $suffixPattern;
        $matched = glob($pattern);
        if ($matched === false || $matched === []) {
            continue;
        }
        $files = array_merge($files, $matched);
    }

    foreach ($files as $file) {
        $mtime = filemtime($file);
        if ($mtime === false || $mtime >= $cutoff) {
            continue;
        }
        @unlink($file);
    }
}

function backup_cleanup_remote_files(array $config, string $folderId, string $accessToken): void
{
    $keep = max(1, (int) ($config['remote_retention_files'] ?? 30));
    $prefix = backup_normalize_prefix((string) ($config['filename_prefix'] ?? BACKUP_DEFAULT_PREFIX));

    $query = sprintf(
        "'%s' in parents and trashed = false and name contains '%s'",
        str_replace("'", "\\'", $folderId),
        str_replace("'", "\\'", $prefix)
    );

    $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
        'q' => $query,
        'fields' => 'files(id,name,createdTime)',
        'orderBy' => 'createdTime desc',
        'pageSize' => 200,
        'supportsAllDrives' => 'true',
        'includeItemsFromAllDrives' => 'true',
    ]);

    [$status, $response] = backup_http_request('GET', $url, [
        'Authorization: Bearer ' . $accessToken,
    ]);

    $payload = json_decode($response, true);
    if ($status < 200 || $status >= 300 || !is_array($payload)) {
        throw new RuntimeException('Gagal membaca daftar file backup di Google Drive.');
    }

    $files = is_array($payload['files'] ?? null) ? $payload['files'] : [];
    if (count($files) <= $keep) {
        return;
    }

    $toDelete = array_slice($files, $keep);
    foreach ($toDelete as $file) {
        $fileId = (string) ($file['id'] ?? '');
        if ($fileId === '') {
            continue;
        }
        [$deleteStatus] = backup_http_request(
            'DELETE',
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?supportsAllDrives=true',
            ['Authorization: Bearer ' . $accessToken]
        );
        if ($deleteStatus < 200 || $deleteStatus >= 300) {
            throw new RuntimeException('Gagal menghapus file lama backup di Google Drive.');
        }
    }
}
