<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../app/models/Product.php';

require_role(['admin', 'bos']);

$pdo = db();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $stockRaw = trim((string) ($_POST['stock'] ?? ''));
        $minRaw = trim((string) ($_POST['min'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($productId <= 0) {
            $errors[] = 'Produk tidak valid.';
        } else {
            $stock = $stockRaw === '' ? null : (int) $stockRaw;
            $min = $minRaw === '' ? 0 : (int) $minRaw;
            inventory_set_item($productId, $stock, $min, (int) (current_user()['id'] ?? 0), $note);
            $success = 'Stok produk berhasil diperbarui.';
        }
    }
}

$products = Product::allForAdmin($pdo);
$inventoryItems = inventory_get_all();
$title = 'Kelola Stok';

require_once __DIR__ . '/../app/views/admin/inventory.php';
