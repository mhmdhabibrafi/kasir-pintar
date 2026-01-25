<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/auth_helper.php';

$user = current_user();
$role = $user['role'] ?? '';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

$items = [];
if ($role === 'karyawan') {
    $items = [
        ['label' => 'Kasir', 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => 'Shift', 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => 'Riwayat', 'icon' => 'receipt_long', 'url' => base_url('kasir_history.php'), 'match' => 'kasir_history.php'],
        ['label' => 'Produk', 'icon' => 'inventory_2', 'url' => base_url('admin_products.php'), 'match' => 'admin_products.php'],
        ['label' => 'Keluar', 'icon' => 'logout', 'url' => base_url('logout.php'), 'match' => 'logout.php'],
    ];
} elseif ($role === 'bos') {
    $items = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => base_url('bos.php'), 'match' => 'bos.php'],
        ['label' => 'Shift', 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => 'Kasir', 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => 'Stok', 'icon' => 'warehouse', 'url' => base_url('admin_inventory.php'), 'match' => 'admin_inventory.php'],
        ['label' => 'Refund', 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => 'Keluar', 'icon' => 'logout', 'url' => base_url('logout.php'), 'match' => 'logout.php'],
    ];
} elseif ($role === 'admin') {
    $items = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => base_url('admin.php'), 'match' => 'admin.php'],
        ['label' => 'Shift', 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => 'Produk', 'icon' => 'inventory_2', 'url' => base_url('admin_products.php'), 'match' => 'admin_product'],
        ['label' => 'Stok', 'icon' => 'warehouse', 'url' => base_url('admin_inventory.php'), 'match' => 'admin_inventory.php'],
        ['label' => 'Refund', 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => 'Keluar', 'icon' => 'logout', 'url' => base_url('logout.php'), 'match' => 'logout.php'],
    ];
} else {
    return;
}
?>

<nav class="kp-bottom-nav d-md-none">
    <div class="container">
        <div class="d-flex justify-content-around text-center">
            <?php foreach ($items as $item): ?>
                <?php $active = strpos($currentPath, $item['match']) !== false; ?>
                <a class="<?php echo $active ? 'active' : ''; ?>" href="<?php echo e($item['url']); ?>">
                    <span class="material-icons-outlined"><?php echo e($item['icon']); ?></span>
                    <div><?php echo e($item['label']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
