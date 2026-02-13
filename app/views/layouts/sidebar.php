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
        ['label' => __('nav.dashboard'), 'icon' => 'dashboard', 'url' => base_url('admin.php'), 'match' => 'admin.php'],
        ['label' => __('nav.shift'), 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => __('nav.cash_daily'), 'icon' => 'account_balance_wallet', 'url' => base_url('admin_cash_report.php'), 'match' => 'admin_cash_report.php'],
        ['label' => __('nav.audit'), 'icon' => 'fact_check', 'url' => base_url('admin_audit.php'), 'match' => 'admin_audit.php'],
        ['label' => __('nav.user'), 'icon' => 'group', 'url' => base_url('admin_users.php'), 'match' => 'admin_user'],
        ['label' => __('nav.customer'), 'icon' => 'badge', 'url' => base_url('admin_customers.php'), 'match' => 'admin_customers.php'],
        ['label' => __('nav.category'), 'icon' => 'category', 'url' => base_url('admin_categories.php'), 'match' => 'admin_categor'],
        ['label' => __('nav.product'), 'icon' => 'inventory_2', 'url' => base_url('admin_products.php'), 'match' => 'admin_product'],
        ['label' => __('nav.stock'), 'icon' => 'warehouse', 'url' => base_url('admin_inventory.php'), 'match' => 'admin_inventory.php'],
        ['label' => __('nav.promo'), 'icon' => 'local_offer', 'url' => base_url('admin_promos.php'), 'match' => 'admin_promos.php'],
        ['label' => __('nav.telegram'), 'icon' => 'notifications', 'url' => base_url('admin_notifications.php'), 'match' => 'admin_notifications.php'],
        ['label' => __('nav.refund'), 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => __('nav.transaction'), 'icon' => 'receipt_long', 'url' => base_url('admin_transactions.php'), 'match' => 'admin_transactions.php'],
        ['label' => __('nav.support'), 'icon' => 'support_agent', 'url' => 'https://wa.me/6288271370244', 'match' => 'wa.me', 'external' => true],
    ];
} elseif ($role === 'bos') {
    $items = [
        ['label' => __('nav.dashboard'), 'icon' => 'dashboard', 'url' => base_url('bos.php'), 'match' => 'bos.php'],
        ['label' => __('nav.shift'), 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => __('nav.cash_daily'), 'icon' => 'account_balance_wallet', 'url' => base_url('admin_cash_report.php'), 'match' => 'admin_cash_report.php'],
        ['label' => __('nav.audit'), 'icon' => 'fact_check', 'url' => base_url('admin_audit.php'), 'match' => 'admin_audit.php'],
        ['label' => __('nav.user'), 'icon' => 'group', 'url' => base_url('admin_users.php'), 'match' => 'admin_user'],
        ['label' => __('nav.customer'), 'icon' => 'badge', 'url' => base_url('admin_customers.php'), 'match' => 'admin_customers.php'],
        ['label' => __('nav.product'), 'icon' => 'inventory_2', 'url' => base_url('admin_products.php'), 'match' => 'admin_product'],
        ['label' => __('nav.stock'), 'icon' => 'warehouse', 'url' => base_url('admin_inventory.php'), 'match' => 'admin_inventory.php'],
        ['label' => __('nav.promo'), 'icon' => 'local_offer', 'url' => base_url('admin_promos.php'), 'match' => 'admin_promos.php'],
        ['label' => __('nav.refund'), 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => __('nav.cashier'), 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => __('nav.item_report'), 'icon' => 'list_alt', 'url' => base_url('bos_items.php'), 'match' => 'bos_items.php'],
        ['label' => __('nav.support'), 'icon' => 'support_agent', 'url' => 'https://wa.me/6288271370244', 'match' => 'wa.me', 'external' => true],
    ];
} elseif ($role === 'karyawan') {
    $items = [
        ['label' => __('nav.cashier'), 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => __('nav.shift'), 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => __('nav.history'), 'icon' => 'receipt_long', 'url' => base_url('kasir_history.php'), 'match' => 'kasir_history.php'],
        ['label' => __('nav.support'), 'icon' => 'support_agent', 'url' => 'https://wa.me/6288271370244', 'match' => 'wa.me', 'external' => true],
    ];
}
?>

<aside class="kp-sidebar d-none d-lg-flex flex-column">
    <div class="kp-logo">
        <div class="kp-logo-wrap">
            <img src="<?php echo e(base_url('assets/images/logo.jpg')); ?>" alt="KASPINDO">
        </div>
        <div>
            <div class="kp-brand-title">KASPINDO</div>
        </div>
    </div>

    <nav class="kp-nav">
        <?php foreach ($items as $item): ?>
            <?php $active = strpos($currentPath, $item['match']) !== false; ?>
            <a
                class="<?php echo $active ? 'active' : ''; ?>"
                href="<?php echo e($item['url']); ?>"
                <?php echo !empty($item['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
            >
                <span class="material-icons-outlined"><?php echo e($item['icon']); ?></span>
                <?php echo e($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="kp-sidebar-footer mt-auto">
        <?php echo e(__('app.footer_version')); ?>
    </div>
</aside>
