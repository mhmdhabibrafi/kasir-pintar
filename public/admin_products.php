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

$categoryColumn = Product::categoryColumn($pdo);
$skuColumn = Product::skuColumn($pdo);
$activeColumn = Product::activeColumn($pdo);
$costColumn = Product::costColumn($pdo);
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

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
            $active = isset($_POST['active']) ? (int) $_POST['active'] : 1;

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

                if ($activeColumn) {
                    $data[$activeColumn] = $active === 1 ? 1 : 0;
                }

                try {
                    Product::create($pdo, $data);
                    $success = 'Produk berhasil ditambahkan.';
                    audit_log('product_created', ['name' => $name, 'price' => $price]);
                } catch (Throwable $e) {
                    $errors[] = 'Gagal menambah produk. SKU mungkin sudah digunakan.';
                }
            }
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    Product::delete($pdo, $id);
                    $success = 'Produk berhasil dihapus.';
                    audit_log('product_deleted', ['product_id' => $id]);
                } catch (Throwable $e) {
                    $errors[] = 'Gagal menghapus produk.';
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

$products = Product::allForAdmin($pdo);
$categories = $categoryColumn ? Category::all($pdo) : [];
$title = 'Kelola Produk';

require_once __DIR__ . '/../app/views/admin/products.php';
