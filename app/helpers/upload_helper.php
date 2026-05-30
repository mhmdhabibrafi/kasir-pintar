<?php

declare(strict_types=1);

function save_qris_upload(array $file): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Bukti QRIS wajib diunggah.'];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['error' => 'Ukuran file maksimal 2MB.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    if (!isset($allowed[$extension])) {
        return ['error' => 'Format file harus JPG atau PNG.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowed, true)) {
        return ['error' => 'Format file tidak valid.'];
    }

    $uploadDir = __DIR__ . '/../../public/uploads/qris';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'qris_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['error' => 'Gagal menyimpan file.'];
    }

    return ['path' => 'uploads/qris/' . $filename];
}

function save_product_image_upload(array $file, bool $required = false): array
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return ['error' => 'Gambar produk wajib diunggah.'];
        }
        return ['path' => null];
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload gambar produk gagal.'];
    }

    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        return ['error' => 'Ukuran gambar produk maksimal 4MB.'];
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedByExt = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    if (!isset($allowedByExt[$extension])) {
        return ['error' => 'Format gambar produk harus JPG, PNG, atau WEBP.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        return ['error' => 'File upload tidak valid.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);
    if (!in_array($mime, $allowedByExt, true)) {
        return ['error' => 'Isi file gambar produk tidak valid.'];
    }

    $uploadDir = __DIR__ . '/../../public/uploads/products';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['error' => 'Folder upload gambar produk tidak bisa dibuat.'];
    }

    $filename = 'prd_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmpName, $destination)) {
        return ['error' => 'Gagal menyimpan gambar produk.'];
    }

    return ['path' => 'uploads/products/' . $filename];
}

function delete_product_image_file(?string $relativePath): void
{
    $path = trim((string) $relativePath);
    if ($path === '') {
        return;
    }

    $path = str_replace('\\', '/', $path);
    if (!preg_match('#^uploads/products/[A-Za-z0-9._-]+$#', $path)) {
        return;
    }

    $fullPath = __DIR__ . '/../../public/' . $path;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
