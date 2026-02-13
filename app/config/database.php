<?php
/**
 * Database Configuration
 * Project : MY KASPIN
 * DB      : MySQL
 * Driver  : PDO
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $DB_HOST = 'localhost';
    $DB_NAME = 'kasir_pintar';
    $DB_USER = 'root';
    $DB_PASS = ''; // default XAMPP kosong
    $DB_CHARSET = 'utf8mb4';

    try {
        $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // error jelas
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // array associative
            PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statement asli
        ];

        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        try {
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (Throwable $e) {
        }
    } catch (PDOException $e) {
        // Jangan tampilkan detail error di production
        die("Koneksi database gagal.");
    }

    return $pdo;
}

