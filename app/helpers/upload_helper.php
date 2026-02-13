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
