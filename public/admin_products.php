<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/upload_helper.php';
require_once __DIR__ . '/../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Category.php';

require_role(['admin', 'bos']);

$pdo = db();
ensure_update_schema();
$errors = [];
$success = '';

$categoryColumn = Product::categoryColumn($pdo);
$skuColumn = Product::skuColumn($pdo);
$imageColumn = Product::imageColumn($pdo);
$activeColumn = Product::activeColumn($pdo);
$costColumn = Product::costColumn($pdo);
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
$currentUserId = (int) ($currentUser['id'] ?? 0);

$resolveCategoryId = static function (?int $selectedId, string $newCategoryName) use ($pdo, $categoryColumn, &$errors): ?int {
    if (!$categoryColumn) {
        return null;
    }

    $normalizedNewCategory = trim($newCategoryName);
    if ($normalizedNewCategory !== '') {
        try {
            return Category::findOrCreate($pdo, $normalizedNewCategory);
        } catch (Throwable $e) {
            $errors[] = 'Gagal menyiapkan kategori baru.';
            return null;
        }
    }

    if ($selectedId !== null && $selectedId > 0) {
        return $selectedId;
    }

    try {
        return Category::ensureDefault($pdo);
    } catch (Throwable $e) {
        $errors[] = 'Kategori default gagal dibuat.';
        return null;
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    if (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mengubah data produk.';
    } elseif (empty($errors)) {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $cost = (float) ($_POST['cost_price'] ?? 0);
            $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : null;
            $newCategoryName = trim((string) ($_POST['category_name_new'] ?? ''));
            $active = isset($_POST['active']) ? (int) $_POST['active'] : 1;
            $trackInventory = isset($_POST['track_inventory']);
            $stockInput = trim((string) ($_POST['stock'] ?? ''));
            $minStockInput = trim((string) ($_POST['min_stock'] ?? ''));
            $stockValue = $trackInventory ? max(0, (int) ($stockInput !== '' ? $stockInput : '0')) : null;
            $minStockValue = $trackInventory ? max(0, (int) ($minStockInput !== '' ? $minStockInput : '0')) : 0;

            if ($name === '') {
                $errors[] = 'Nama produk wajib diisi.';
            }

            if ($skuColumn && $sku === '') {
                $errors[] = 'SKU/kode wajib diisi.';
            }

            if ($price < 0 || ($costColumn && $cost < 0)) {
                $errors[] = 'Harga jual atau harga modal tidak valid.';
            }

            if ($categoryColumn) {
                $resolvedCategoryId = $resolveCategoryId($categoryId, $newCategoryName);
                if ($resolvedCategoryId !== null) {
                    $categoryId = $resolvedCategoryId;
                }
            }

            if (empty($errors)) {
                $uploadedImagePath = null;
                if ($imageColumn) {
                    $upload = save_product_image_upload($_FILES['product_image'] ?? [], false);
                    if (!empty($upload['error'])) {
                        $errors[] = (string) $upload['error'];
                    } else {
                        $uploadedImagePath = $upload['path'] ?? null;
                    }
                }
            }

            if (empty($errors)) {
                $data = [
                    'name' => $name,
                    'price' => $price,
                ];

                if ($skuColumn) {
                    $data[$skuColumn] = $sku;
                }

                if ($costColumn) {
                    $data[$costColumn] = $cost;
                }

                if ($categoryColumn) {
                    $data[$categoryColumn] = $categoryId;
                }

                if ($imageColumn) {
                    $data[$imageColumn] = $uploadedImagePath;
                }

                if ($activeColumn) {
                    $data[$activeColumn] = $active === 1 ? 1 : 0;
                }

                try {
                    $pdo->beginTransaction();
                    $productId = Product::create($pdo, $data);
                    inventory_set_item($productId, $stockValue, $minStockValue, $currentUserId, 'sinkron produk baru');
                    $pdo->commit();
                    $success = 'Produk berhasil ditambahkan.';
                    audit_log('product_created', [
                        'product_id' => $productId,
                        'name' => $name,
                        'price' => $price,
                        'stock' => $stockValue,
                        'min_stock' => $minStockValue,
                    ]);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (!empty($uploadedImagePath)) {
                        delete_product_image_file((string) $uploadedImagePath);
                    }
                    $errors[] = 'Gagal menambah produk. SKU atau kategori mungkin bermasalah.';
                }
            }
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $deletedImagePath = '';
                if ($imageColumn) {
                    $row = Product::find($pdo, $id);
                    if (is_array($row)) {
                        $deletedImagePath = (string) ($row[$imageColumn] ?? '');
                    }
                }
                try {
                    $pdo->beginTransaction();
                    $deleteInventoryLogs = $pdo->prepare('DELETE FROM inventory_logs WHERE product_id = :id' . tenant_where_clause($pdo, 'inventory_logs', 'inventory_logs', 'AND'));
                    $deleteInventoryItems = $pdo->prepare('DELETE FROM inventory_items WHERE product_id = :id' . tenant_where_clause($pdo, 'inventory_items', 'inventory_items', 'AND'));
                    $deleteInventoryLogs->execute(tenant_bind([':id' => $id], $pdo));
                    $deleteInventoryItems->execute(tenant_bind([':id' => $id], $pdo));
                    Product::delete($pdo, $id);
                    $pdo->commit();
                    if ($deletedImagePath !== '') {
                        delete_product_image_file($deletedImagePath);
                    }
                    $success = 'Produk berhasil dihapus.';
                    audit_log('product_deleted', ['product_id' => $id]);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Gagal menghapus produk. Produk mungkin sudah dipakai transaksi.';
                }
            }
        }

        if ($action === 'toggle' && $activeColumn) {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (int) ($_POST['active'] ?? 0);
            if ($id > 0) {
                try {
                    Product::update($pdo, $id, [$activeColumn => $active === 1 ? 1 : 0]);
                    $success = 'Status produk berhasil diubah.';
                    audit_log('product_status_updated', ['product_id' => $id, 'active' => $active === 1 ? 1 : 0]);
                } catch (Throwable $e) {
                    $errors[] = 'Gagal mengubah status produk.';
                }
            }
        }
    }
}

$categories = $categoryColumn ? Category::all($pdo) : [];
$productFilters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'category' => isset($_GET['category']) ? (int) $_GET['category'] : 0,
    'status' => (string) ($_GET['status'] ?? 'all'),
    'stock' => (string) ($_GET['stock'] ?? 'all'),
];

if (!in_array($productFilters['status'], ['all', 'active', 'inactive'], true)) {
    $productFilters['status'] = 'all';
}

if (!in_array($productFilters['stock'], ['all', 'critical', 'out', 'tracked', 'untracked'], true)) {
    $productFilters['stock'] = 'all';
}

$inventoryItems = inventory_get_all($pdo);
$products = array_map(
    static function (array $product) use ($inventoryItems, $activeColumn, $costColumn): array {
        $productId = (int) ($product['id'] ?? 0);
        $inventoryItem = $inventoryItems[$productId] ?? null;
        $trackedInventory = is_array($inventoryItem)
            && array_key_exists('stock', $inventoryItem)
            && $inventoryItem['stock'] !== null;
        $stock = $trackedInventory ? (int) ($inventoryItem['stock'] ?? 0) : null;
        $minStock = $trackedInventory ? (int) ($inventoryItem['min'] ?? 0) : 0;
        $isCritical = $trackedInventory && $stock !== null && $stock <= $minStock;
        $isOut = $trackedInventory && $stock !== null && $stock <= 0;

        return $product + [
            'stock_on_hand' => $stock,
            'min_stock_value' => $minStock,
            'inventory_tracked' => $trackedInventory,
            'is_critical_stock' => $isCritical,
            'is_out_of_stock' => $isOut,
            'is_active_row' => !$activeColumn || (int) ($product['is_active'] ?? 1) === 1,
            'margin_value' => $costColumn ? ((float) ($product['price'] ?? 0) - (float) ($product['cost_price'] ?? 0)) : null,
        ];
    },
    Product::allForAdmin($pdo)
);

$allProducts = $products;
$products = array_values(array_filter(
    $products,
    static function (array $product) use ($productFilters): bool {
        if ($productFilters['category'] > 0 && (int) ($product['category_id'] ?? 0) !== $productFilters['category']) {
            return false;
        }

        if ($productFilters['status'] === 'active' && empty($product['is_active_row'])) {
            return false;
        }

        if ($productFilters['status'] === 'inactive' && !empty($product['is_active_row'])) {
            return false;
        }

        if ($productFilters['stock'] === 'critical' && empty($product['is_critical_stock'])) {
            return false;
        }

        if ($productFilters['stock'] === 'out' && empty($product['is_out_of_stock'])) {
            return false;
        }

        if ($productFilters['stock'] === 'tracked' && empty($product['inventory_tracked'])) {
            return false;
        }

        if ($productFilters['stock'] === 'untracked' && !empty($product['inventory_tracked'])) {
            return false;
        }

        $keyword = strtolower($productFilters['q']);
        if ($keyword === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            (string) ($product['name'] ?? ''),
            (string) ($product['sku'] ?? ''),
            (string) ($product['category_name'] ?? ''),
        ])));

        return str_contains($haystack, $keyword);
    }
));

$productSummary = [
    'all_total' => count($allProducts),
    'visible_total' => count($products),
    'active_total' => count(array_filter($products, static fn (array $row): bool => !empty($row['is_active_row']))),
    'inactive_total' => count(array_filter($products, static fn (array $row): bool => empty($row['is_active_row']))),
    'critical_total' => count(array_filter($products, static fn (array $row): bool => !empty($row['is_critical_stock']))),
    'tracked_total' => count(array_filter($products, static fn (array $row): bool => !empty($row['inventory_tracked']))),
];

$title = 'Kelola Produk';

require_once __DIR__ . '/../app/views/admin/products.php';
