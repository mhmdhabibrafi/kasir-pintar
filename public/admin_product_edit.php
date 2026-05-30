<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/upload_helper.php';
require_once __DIR__ . '/../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Category.php';

require_role(['admin']);

$pdo = db();
$errors = [];
$success = '';

$id = (int) ($_GET['id'] ?? 0);
$product = $id > 0 ? Product::find($pdo, $id) : null;
$categoryColumn = Product::categoryColumn($pdo);
$skuColumn = Product::skuColumn($pdo);
$imageColumn = Product::imageColumn($pdo);
$activeColumn = Product::activeColumn($pdo);
$costColumn = Product::costColumn($pdo);
$categories = $categoryColumn ? Category::all($pdo) : [];
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
$currentUserId = (int) ($currentUser['id'] ?? 0);
$inventoryItem = $product ? inventory_get_item($id) : null;

$resolveCategoryId = static function (?int $selectedId, string $newCategoryName, ?int $fallbackId = null) use ($pdo, $categoryColumn, &$errors): ?int {
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

    if ($fallbackId !== null && $fallbackId > 0) {
        return $fallbackId;
    }

    try {
        return Category::ensureDefault($pdo);
    } catch (Throwable $e) {
        $errors[] = 'Kategori default gagal dibuat.';
        return null;
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    if (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mengubah data produk.';
    } elseif (empty($errors)) {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $cost = (float) ($_POST['cost_price'] ?? 0);
        $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $newCategoryName = trim((string) ($_POST['category_name_new'] ?? ''));
        $active = isset($_POST['active']) ? (int) $_POST['active'] : null;
        $removeImage = isset($_POST['remove_image']);
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
            $resolvedCategoryId = $resolveCategoryId(
                $categoryId,
                $newCategoryName,
                isset($product[$categoryColumn]) ? (int) $product[$categoryColumn] : null
            );
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

            if ($activeColumn && $active !== null) {
                $data[$activeColumn] = $active === 1 ? 1 : 0;
            }

            $oldImagePath = $imageColumn ? (string) ($product[$imageColumn] ?? '') : '';
            if ($imageColumn) {
                if (!empty($uploadedImagePath)) {
                    $data[$imageColumn] = $uploadedImagePath;
                } elseif ($removeImage) {
                    $data[$imageColumn] = null;
                }
            }

            try {
                $pdo->beginTransaction();
                Product::update($pdo, $id, $data);
                inventory_set_item($id, $stockValue, $minStockValue, $currentUserId, 'sinkron edit produk');
                $pdo->commit();
                if ($imageColumn) {
                    if (!empty($uploadedImagePath) && $oldImagePath !== '' && $oldImagePath !== $uploadedImagePath) {
                        delete_product_image_file($oldImagePath);
                    } elseif ($removeImage && $oldImagePath !== '') {
                        delete_product_image_file($oldImagePath);
                    }
                }
                $success = 'Produk berhasil diperbarui.';
                audit_log('product_updated', ['product_id' => $id]);
                $product = Product::find($pdo, $id);
                $inventoryItem = inventory_get_item($id);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (!empty($uploadedImagePath)) {
                    delete_product_image_file($uploadedImagePath);
                }
                $errors[] = 'Gagal memperbarui produk.';
            }
        }
    }
}

$inventoryTracked = is_array($inventoryItem) && array_key_exists('stock', $inventoryItem) && $inventoryItem['stock'] !== null;
$formState = [
    'name' => (string) (($_POST['name'] ?? '') !== '' ? $_POST['name'] : ($product['name'] ?? '')),
    'sku' => (string) (($_POST['sku'] ?? '') !== '' ? $_POST['sku'] : ($skuColumn ? ($product[$skuColumn] ?? '') : '')),
    'price' => (string) (($_POST['price'] ?? '') !== '' ? $_POST['price'] : ($product['price'] ?? '0')),
    'cost_price' => (string) (($_POST['cost_price'] ?? '') !== '' ? $_POST['cost_price'] : ($costColumn ? ($product[$costColumn] ?? '0') : '0')),
    'category_id' => (string) (($_POST['category_id'] ?? '') !== '' ? $_POST['category_id'] : ($categoryColumn ? ($product[$categoryColumn] ?? '') : '')),
    'category_name_new' => (string) ($_POST['category_name_new'] ?? ''),
    'active' => (string) (($_POST['active'] ?? '') !== '' ? $_POST['active'] : ($activeColumn ? ($product[$activeColumn] ?? '1') : '1')),
    'track_inventory' => ($_SERVER['REQUEST_METHOD'] === 'POST') ? isset($_POST['track_inventory']) : $inventoryTracked,
    'stock' => (string) (($_POST['stock'] ?? '') !== '' ? $_POST['stock'] : ($inventoryTracked ? (string) ($inventoryItem['stock'] ?? 0) : '')),
    'min_stock' => (string) (($_POST['min_stock'] ?? '') !== '' ? $_POST['min_stock'] : (is_array($inventoryItem) ? (string) ($inventoryItem['min'] ?? 0) : '0')),
];

$title = 'Edit Produk';

require_once __DIR__ . '/../app/views/admin/product_edit.php';
