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
$categoryColumn = Product::categoryColumn($pdo);
$isCreatePost = ($_SERVER['REQUEST_METHOD'] === 'POST') && (string) ($_POST['action'] ?? '') === 'create';
$oldCreate = $isCreatePost ? $_POST : [];

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
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE ' . $categoryColumn . ' = :id' . tenant_where_clause($pdo, 'products', 'products', 'AND'));
                $stmt->execute(tenant_bind([':id' => $id], $pdo));
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

$categoryFilters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'usage' => (string) ($_GET['usage'] ?? 'all'),
];

if (!in_array($categoryFilters['usage'], ['all', 'used', 'empty'], true)) {
    $categoryFilters['usage'] = 'all';
}

$categories = Category::allWithTotals($pdo, $categoryColumn);
$allCategories = $categories;
$categories = array_values(array_filter(
    $categories,
    static function (array $category) use ($categoryFilters): bool {
        $totalProducts = (int) ($category['total_products'] ?? 0);

        if ($categoryFilters['usage'] === 'used' && $totalProducts <= 0) {
            return false;
        }

        if ($categoryFilters['usage'] === 'empty' && $totalProducts > 0) {
            return false;
        }

        $keyword = strtolower($categoryFilters['q']);
        if ($keyword === '') {
            return true;
        }

        return str_contains(strtolower((string) ($category['name'] ?? '')), $keyword);
    }
));

$categorySummary = [
    'all_total' => count($allCategories),
    'visible_total' => count($categories),
    'used_total' => count(array_filter($categories, static fn (array $row): bool => (int) ($row['total_products'] ?? 0) > 0)),
    'empty_total' => count(array_filter($categories, static fn (array $row): bool => (int) ($row['total_products'] ?? 0) <= 0)),
    'product_total' => array_reduce(
        $categories,
        static fn (int $carry, array $row): int => $carry + (int) ($row['total_products'] ?? 0),
        0
    ),
];

$title = 'Kelola Kategori';

require_once __DIR__ . '/../app/views/admin/categories.php';
