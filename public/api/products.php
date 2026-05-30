<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/api_helper.php';
require_once __DIR__ . '/../../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../../app/helpers/user_permission_helper.php';
require_once __DIR__ . '/../../app/models/Product.php';

api_require_method('GET');
$auth = api_require_auth(['admin', 'bos', 'karyawan']);
$user = $auth['user'];
if (!user_can($user, 'access_cashier')) {
    api_error('Akun ini tidak diizinkan membuka POS.', 403, 'cashier_access_denied');
}

$pdo = db();
$query = strtolower(trim((string) ($_GET['q'] ?? '')));
$rows = Product::all($pdo);
$inventoryItems = inventory_get_all();
$includeCostPrice = in_array((string) ($user['role'] ?? ''), ['admin', 'bos'], true);
$products = [];

foreach ($rows as $row) {
    $name = (string) ($row['name'] ?? '');
    $sku = (string) ($row['sku'] ?? '');
    $productImage = trim((string) ($row['product_image'] ?? ''));
    $productImageUrl = $productImage !== '' ? base_url($productImage) : null;
    $inventoryItem = $inventoryItems[(int) ($row['id'] ?? 0)] ?? null;
    $trackedStock = is_array($inventoryItem) && array_key_exists('stock', $inventoryItem) && $inventoryItem['stock'] !== null;
    $stockValue = $trackedStock ? (int) ($inventoryItem['stock'] ?? 0) : (isset($row['stock']) ? (int) $row['stock'] : null);

    if ($query !== '') {
        $haystack = strtolower($name . ' ' . $sku);
        if (strpos($haystack, $query) === false) {
            continue;
        }
    }

    $product = [
        'id' => (int) $row['id'],
        'name' => $name,
        'sku' => $sku,
        'price' => (float) ($row['price'] ?? 0),
        'stock' => $stockValue,
        'stock_tracked' => $trackedStock,
        'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
        'category_name' => isset($row['category_name']) ? (string) $row['category_name'] : null,
        'product_image' => $productImage !== '' ? $productImage : null,
        'product_image_url' => $productImageUrl,
    ];

    if ($includeCostPrice) {
        $product['cost_price'] = isset($row['cost_price']) ? (float) $row['cost_price'] : null;
    }

    $products[] = $product;
}

api_ok([
    'count' => count($products),
    'products' => $products,
]);
