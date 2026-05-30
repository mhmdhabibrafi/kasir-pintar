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
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif (!$isAdmin) {
        $errors[] = 'Akses terbatas. Hanya admin yang dapat mengubah stok.';
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
            if (Product::find($pdo, $productId) === null) {
                $errors[] = 'Produk tidak ditemukan.';
            }
            if ($stock !== null && $stock < 0) {
                $errors[] = 'Stok tidak boleh negatif.';
            }
            if ($min < 0) {
                $errors[] = 'Minimum stok tidak boleh negatif.';
            }

            if (empty($errors)) {
                try {
                    inventory_set_item($productId, $stock, $min, (int) ($currentUser['id'] ?? 0), $note);
                    $success = 'Stok produk berhasil diperbarui.';
                } catch (Throwable $e) {
                    $errors[] = 'Gagal memperbarui stok produk.';
                }
            }
        }
    }
}

$inventoryFilters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'stock_status' => trim((string) ($_GET['stock_status'] ?? 'all')),
];

if (!in_array($inventoryFilters['stock_status'], ['all', 'critical', 'out', 'tracked', 'untracked'], true)) {
    $inventoryFilters['stock_status'] = 'all';
}

$inventoryItems = inventory_get_all($pdo);
$products = array_map(
    static function (array $product) use ($inventoryItems): array {
        $productId = (int) ($product['id'] ?? 0);
        $inventoryItem = $inventoryItems[$productId] ?? null;
        $trackedInventory = is_array($inventoryItem)
            && array_key_exists('stock', $inventoryItem)
            && $inventoryItem['stock'] !== null;
        $stock = $trackedInventory ? (int) ($inventoryItem['stock'] ?? 0) : null;
        $minStock = $trackedInventory ? (int) ($inventoryItem['min'] ?? 0) : 0;

        return $product + [
            'stock_on_hand' => $stock,
            'min_stock_value' => $minStock,
            'inventory_tracked' => $trackedInventory,
            'is_critical_stock' => $trackedInventory && $stock !== null && $stock <= $minStock,
            'is_out_of_stock' => $trackedInventory && $stock !== null && $stock <= 0,
        ];
    },
    Product::allForAdmin($pdo)
);

usort($products, static function (array $left, array $right): int {
    $rank = static function (array $row): int {
        if (!empty($row['is_out_of_stock'])) {
            return 0;
        }
        if (!empty($row['is_critical_stock'])) {
            return 1;
        }
        if (!empty($row['inventory_tracked'])) {
            return 2;
        }
        return 3;
    };

    $leftRank = $rank($left);
    $rightRank = $rank($right);
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
});

$allProducts = $products;
$products = array_values(array_filter(
    $products,
    static function (array $product) use ($inventoryFilters): bool {
        if ($inventoryFilters['stock_status'] === 'critical' && empty($product['is_critical_stock'])) {
            return false;
        }
        if ($inventoryFilters['stock_status'] === 'out' && empty($product['is_out_of_stock'])) {
            return false;
        }
        if ($inventoryFilters['stock_status'] === 'tracked' && empty($product['inventory_tracked'])) {
            return false;
        }
        if ($inventoryFilters['stock_status'] === 'untracked' && !empty($product['inventory_tracked'])) {
            return false;
        }

        $keyword = strtolower($inventoryFilters['q']);
        if ($keyword === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            (string) ($product['name'] ?? ''),
            (string) ($product['category_name'] ?? ''),
            (string) ($product['sku'] ?? ''),
        ])));

        return str_contains($haystack, $keyword);
    }
));

$inventorySummary = [
    'all_total' => count($allProducts),
    'visible_total' => count($products),
    'tracked_total' => count(array_filter($products, static fn (array $row): bool => !empty($row['inventory_tracked']))),
    'critical_total' => count(array_filter($products, static fn (array $row): bool => !empty($row['is_critical_stock']))),
    'out_total' => count(array_filter($products, static fn (array $row): bool => !empty($row['is_out_of_stock']))),
];

$title = 'Kelola Stok';

require_once __DIR__ . '/../app/views/admin/inventory.php';
