<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth_helper.php';

$user = current_user();
if (!$user) {
    return;
}

$role = $user['role'] ?? '';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

$items = [];

if ($role === 'admin') {
    $items = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => base_url('admin.php'), 'match' => 'admin.php'],
        ['label' => 'Shift', 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => 'User', 'icon' => 'group', 'url' => base_url('admin_users.php'), 'match' => 'admin_user'],
        ['label' => 'Kategori', 'icon' => 'category', 'url' => base_url('admin_categories.php'), 'match' => 'admin_categor'],
        ['label' => 'Produk', 'icon' => 'inventory_2', 'url' => base_url('admin_products.php'), 'match' => 'admin_product'],
        ['label' => 'Stok', 'icon' => 'warehouse', 'url' => base_url('admin_inventory.php'), 'match' => 'admin_inventory.php'],
        ['label' => 'Promo', 'icon' => 'local_offer', 'url' => base_url('admin_promos.php'), 'match' => 'admin_promos.php'],
        ['label' => 'Refund', 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => 'Transaksi', 'icon' => 'receipt_long', 'url' => base_url('admin_transactions.php'), 'match' => 'admin_transactions.php'],
    ];
} elseif ($role === 'bos') {
    $items = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => base_url('bos.php'), 'match' => 'bos.php'],
        ['label' => 'Shift', 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => 'User', 'icon' => 'group', 'url' => base_url('admin_users.php'), 'match' => 'admin_user'],
        ['label' => 'Produk', 'icon' => 'inventory_2', 'url' => base_url('admin_products.php'), 'match' => 'admin_product'],
        ['label' => 'Stok', 'icon' => 'warehouse', 'url' => base_url('admin_inventory.php'), 'match' => 'admin_inventory.php'],
        ['label' => 'Promo', 'icon' => 'local_offer', 'url' => base_url('admin_promos.php'), 'match' => 'admin_promos.php'],
        ['label' => 'Refund', 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => 'Kasir', 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => 'Laporan Item', 'icon' => 'list_alt', 'url' => base_url('bos_items.php'), 'match' => 'bos_items.php'],
    ];
} elseif ($role === 'karyawan') {
    $items = [
        ['label' => 'Kasir', 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => 'Shift', 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => 'Riwayat', 'icon' => 'receipt_long', 'url' => base_url('kasir_history.php'), 'match' => 'kasir_history.php'],
        ['label' => 'Produk', 'icon' => 'inventory_2', 'url' => base_url('admin_products.php'), 'match' => 'admin_product'],
    ];
}
?>

<aside class="kp-sidebar d-none d-lg-flex flex-column">
    <div class="kp-logo">
        <div class="kp-logo-wrap">
            <img src="<?php echo e(base_url('assets/images/logo.jpg')); ?>" alt="KASIR PINTAR">
        </div>
        <div>
            <div class="kp-brand-title">KASIR PINTAR</div>
        </div>
    </div>

    <nav class="kp-nav">
        <?php foreach ($items as $item): ?>
            <?php $active = strpos($currentPath, $item['match']) !== false; ?>
            <a class="<?php echo $active ? 'active' : ''; ?>" href="<?php echo e($item['url']); ?>">
                <span class="material-icons-outlined"><?php echo e($item['icon']); ?></span>
                <?php echo e($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="kp-sidebar-footer mt-auto">
        Kasir Pintar versi 1.0 by Muhammad Habib Rafi
    </div>
</aside>

