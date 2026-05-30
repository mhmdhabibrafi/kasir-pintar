<?php
/**
 * Database Configuration
 * Project : KASPINDO
 * DB      : MySQL
 * Driver  : PDO
 */

declare(strict_types=1);

require_once __DIR__ . '/app.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbHost = trim((string) (getenv('DB_HOST') ?: 'localhost'));
    $dbPort = trim((string) (getenv('DB_PORT') ?: '3306'));
    $dbName = trim((string) (getenv('DB_NAME') ?: ''));
    $dbUser = (string) (getenv('DB_USER') ?: 'root');
    $dbPass = (string) (getenv('DB_PASS') ?: '');
    $dbCharset = trim((string) (getenv('DB_CHARSET') ?: 'utf8mb4'));
    $dbSocket = trim((string) (getenv('DB_SOCKET') ?: ''));
    $dbNameLocked = $dbName !== '';

    $databaseCandidates = array_values(array_unique(array_filter(
        $dbNameLocked ? [$dbName] : ['kasir_pintar', 'kaspindo'],
        static fn ($name): bool => is_string($name) && trim($name) !== ''
    )));

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $lastErrorMessage = '';
    $bestScore = -1;
    $bestConnection = null;

    foreach ($databaseCandidates as $candidate) {
        try {
            if ($dbSocket !== '') {
                $dsn = sprintf(
                    'mysql:unix_socket=%s;dbname=%s;charset=%s',
                    $dbSocket,
                    $candidate,
                    $dbCharset
                );
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $dbHost,
                    $dbPort,
                    $candidate,
                    $dbCharset
                );
            }

            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            try {
                $pdo->exec("SET time_zone = '+07:00'");
            } catch (Throwable $e) {
                // Ignore timezone issues to keep app usable.
            }

            if ($dbNameLocked) {
                return $pdo;
            }

            $score = db_connection_score($pdo);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestConnection = $pdo;
            }
        } catch (PDOException $e) {
            $lastErrorMessage = $e->getMessage();
        }
    }

    if ($bestConnection instanceof PDO) {
        $pdo = $bestConnection;
        return $pdo;
    }

    if (PHP_SAPI === 'cli' && $lastErrorMessage !== '') {
        die('Koneksi database gagal. ' . $lastErrorMessage);
    }

    // Jangan tampilkan detail error di production/browser.
    die('Koneksi database gagal.');
}

function db_connection_score(PDO $pdo): int
{
    $score = 0;
    $coreTables = ['roles', 'users', 'categories', 'products', 'transactions', 'payments'];

    foreach ($coreTables as $table) {
        try {
            $exists = (int) $pdo->query(
                "SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = " . $pdo->quote($table)
            )->fetchColumn();
            if ($exists > 0) {
                $score += 10;
            }
        } catch (Throwable $e) {
            continue;
        }
    }

    foreach (['users', 'products', 'transactions', 'payments'] as $table) {
        try {
            $rows = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
            $score += min($rows, 1000);
        } catch (Throwable $e) {
            continue;
        }
    }

    return $score;
}
