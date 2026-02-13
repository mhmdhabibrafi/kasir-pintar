<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/auth_helper.php';

$user = current_user();
$role = $user['role'] ?? '';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

$items = [];
if ($role === 'karyawan') {
    $items = [
        ['label' => __('nav.cashier'), 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => __('nav.shift'), 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => __('nav.history'), 'icon' => 'receipt_long', 'url' => base_url('kasir_history.php'), 'match' => 'kasir_history.php'],
        ['label' => __('nav.logout'), 'icon' => 'logout', 'url' => base_url('logout.php'), 'match' => 'logout.php'],
    ];
} elseif ($role === 'bos') {
    $items = [
        ['label' => __('nav.dashboard'), 'icon' => 'dashboard', 'url' => base_url('bos.php'), 'match' => 'bos.php'],
        ['label' => __('nav.shift'), 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => __('nav.cashier'), 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => __('nav.cash_daily'), 'icon' => 'account_balance_wallet', 'url' => base_url('admin_cash_report.php'), 'match' => 'admin_cash_report.php'],
        ['label' => __('nav.audit'), 'icon' => 'fact_check', 'url' => base_url('admin_audit.php'), 'match' => 'admin_audit.php'],
        ['label' => __('nav.customer'), 'icon' => 'badge', 'url' => base_url('admin_customers.php'), 'match' => 'admin_customers.php'],
        ['label' => __('nav.stock'), 'icon' => 'warehouse', 'url' => base_url('admin_inventory.php'), 'match' => 'admin_inventory.php'],
        ['label' => __('nav.refund'), 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => __('nav.logout'), 'icon' => 'logout', 'url' => base_url('logout.php'), 'match' => 'logout.php'],
    ];
} elseif ($role === 'admin') {
    $items = [
        ['label' => __('nav.dashboard'), 'icon' => 'dashboard', 'url' => base_url('admin.php'), 'match' => 'admin.php'],
        ['label' => __('nav.shift'), 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => __('nav.cash_daily'), 'icon' => 'account_balance_wallet', 'url' => base_url('admin_cash_report.php'), 'match' => 'admin_cash_report.php'],
        ['label' => __('nav.audit'), 'icon' => 'fact_check', 'url' => base_url('admin_audit.php'), 'match' => 'admin_audit.php'],
        ['label' => __('nav.customer'), 'icon' => 'badge', 'url' => base_url('admin_customers.php'), 'match' => 'admin_customers.php'],
        ['label' => __('nav.product'), 'icon' => 'inventory_2', 'url' => base_url('admin_products.php'), 'match' => 'admin_product'],
        ['label' => __('nav.stock'), 'icon' => 'warehouse', 'url' => base_url('admin_inventory.php'), 'match' => 'admin_inventory.php'],
        ['label' => __('nav.telegram'), 'icon' => 'notifications', 'url' => base_url('admin_notifications.php'), 'match' => 'admin_notifications.php'],
        ['label' => __('nav.refund'), 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => __('nav.logout'), 'icon' => 'logout', 'url' => base_url('logout.php'), 'match' => 'logout.php'],
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
