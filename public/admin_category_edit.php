<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/tenant_helper.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/Product.php';

require_role(['admin']);

$pdo = db();
$errors = [];
$success = '';

$id = (int) ($_GET['id'] ?? 0);
$category = $id > 0 ? Category::find($pdo, $id) : null;
$categoryColumn = Product::categoryColumn($pdo);
$productTotal = 0;

if ($category && $categoryColumn) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE ' . $categoryColumn . ' = :id' . tenant_where_clause($pdo, 'products', 'products', 'AND'));
    $stmt->execute(tenant_bind([':id' => $category['id']], $pdo));
    $productTotal = (int) $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $category) {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $errors[] = 'Nama kategori wajib diisi.';
    }

    if (empty($errors)) {
        try {
            Category::update($pdo, (int) $category['id'], $name);
            $success = 'Kategori berhasil diperbarui.';
            audit_log('category_updated', ['category_id' => (int) $category['id']]);
            $category = Category::find($pdo, (int) $category['id']);
        } catch (Throwable $e) {
            $errors[] = 'Gagal memperbarui kategori. Nama mungkin sudah digunakan.';
        }
    }
}

$title = 'Edit Kategori';

require_once __DIR__ . '/../app/views/admin/category_edit.php';
