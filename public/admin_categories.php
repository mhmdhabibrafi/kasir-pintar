<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/Product.php';

require_role(['admin']);

$pdo = db();
$errors = [];
$success = '';
$categoryColumn = Product::categoryColumn($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Nama kategori wajib diisi.';
        }

        if (empty($errors)) {
            try {
                Category::create($pdo, $name);
                $success = 'Kategori berhasil ditambahkan.';
                audit_log('category_created', ['name' => $name]);
            } catch (Throwable $e) {
                $errors[] = 'Gagal menambah kategori. Nama mungkin sudah digunakan.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($categoryColumn) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE ' . $categoryColumn . ' = :id');
                $stmt->execute([':id' => $id]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $errors[] = 'Kategori masih digunakan oleh produk.';
                }
            }

            if (empty($errors)) {
                try {
                    Category::delete($pdo, $id);
                    $success = 'Kategori berhasil dihapus.';
                    audit_log('category_deleted', ['category_id' => $id]);
                } catch (Throwable $e) {
                    $errors[] = 'Gagal menghapus kategori.';
                }
            }
        }
    }
}

$categories = Category::allWithTotals($pdo, $categoryColumn);
$title = 'Kelola Kategori';

require_once __DIR__ . '/../app/views/admin/categories.php';
