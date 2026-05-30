<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/auth_helper.php';

// Mobile navigation now lives in the sidebar drawer.
return;

$user = current_user();
$role = $user['role'] ?? '';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

$items = [];
if ($role === 'superadmin') {
    $items = [
        ['label' => __('nav.superadmin_overview'), 'icon' => 'dashboard', 'url' => base_url('superadmin_store_requests.php?focus=overview'), 'match' => 'focus=overview'],
        ['label' => __('nav.active_stores'), 'icon' => 'storefront', 'url' => base_url('superadmin_store_requests.php?focus=stores'), 'match' => 'focus=stores'],
        ['label' => 'Health', 'icon' => 'monitor_heart', 'url' => base_url('system_health.php'), 'match' => 'system_health.php'],
        ['label' => __('nav.support'), 'icon' => 'support_agent', 'url' => base_url('support_chat.php'), 'match' => 'support_chat.php'],
        ['label' => __('nav.audit'), 'icon' => 'fact_check', 'url' => base_url('admin_audit.php'), 'match' => 'admin_audit.php'],
        ['label' => __('nav.logout'), 'icon' => 'logout', 'url' => base_url('logout.php'), 'match' => 'logout.php'],
    ];
} elseif ($role === 'karyawan') {
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
        ['label' => __('nav.support'), 'icon' => 'support_agent', 'url' => base_url('support_chat.php'), 'match' => 'support_chat.php'],
        ['label' => __('nav.audit'), 'icon' => 'fact_check', 'url' => base_url('admin_audit.php'), 'match' => 'admin_audit.php'],
        ['label' => __('nav.logout'), 'icon' => 'logout', 'url' => base_url('logout.php'), 'match' => 'logout.php'],
    ];
} elseif ($role === 'admin') {
    $items = [
        ['label' => __('nav.dashboard'), 'icon' => 'dashboard', 'url' => base_url('admin.php'), 'match' => 'admin.php'],
        ['label' => __('nav.shift'), 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => __('nav.audit'), 'icon' => 'fact_check', 'url' => base_url('admin_audit.php'), 'match' => 'admin_audit.php'],
        ['label' => __('nav.product'), 'icon' => 'inventory_2', 'url' => base_url('admin_products.php'), 'match' => 'admin_product'],
        ['label' => __('nav.support'), 'icon' => 'support_agent', 'url' => base_url('support_chat.php'), 'match' => 'support_chat.php'],
        ['label' => __('nav.logout'), 'icon' => 'logout', 'url' => base_url('logout.php'), 'match' => 'logout.php'],
    ];
}
 else {
    return;
}
?>

<nav class="kp-bottom-nav d-md-none">
    <div class="container kp-bottom-nav-shell">
        <div class="kp-bottom-nav-track text-center" style="--kp-nav-count: <?php echo max(1, count($items)); ?>;">
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
