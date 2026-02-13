<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Category.php';

require_role(['admin', 'bos']);

$pdo = db();
$errors = [];
$success = '';

$id = (int) ($_GET['id'] ?? 0);
$product = $id > 0 ? Product::find($pdo, $id) : null;
$categoryColumn = Product::categoryColumn($pdo);
$skuColumn = Product::skuColumn($pdo);
$activeColumn = Product::activeColumn($pdo);
$costColumn = Product::costColumn($pdo);
$categories = $categoryColumn ? Category::all($pdo) : [];
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

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
        $active = isset($_POST['active']) ? (int) $_POST['active'] : null;

        if ($name === '') {
            $errors[] = 'Nama produk wajib diisi.';
        }

        if ($skuColumn && $sku === '') {
            $errors[] = 'SKU/kode wajib diisi.';
        }

        if ($price < 0 || ($costColumn && $cost < 0)) {
            $errors[] = 'Harga jual atau harga modal tidak valid.';
        }

        if ($categoryColumn && (!$categoryId || $categoryId < 1)) {
            $errors[] = 'Kategori wajib dipilih.';
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

            try {
                Product::update($pdo, $id, $data);
                $success = 'Produk berhasil diperbarui.';
                audit_log('product_updated', ['product_id' => $id]);
                $product = Product::find($pdo, $id);
            } catch (Throwable $e) {
                $errors[] = 'Gagal memperbarui produk.';
            }
        }
    }
}

$title = 'Edit Produk';

require_once __DIR__ . '/../app/views/admin/product_edit.php';
