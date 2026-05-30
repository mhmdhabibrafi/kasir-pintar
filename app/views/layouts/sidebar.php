<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth_helper.php';

$user = current_user();

$role = $user['role'] ?? '';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

$items = [];
$supportEnabled = function_exists('app_env_bool') ? app_env_bool('APP_SUPPORT_ENABLED', false) : false;

if ($role === 'superadmin') {
    $items = [
        ['label' => __('nav.superadmin_overview'), 'icon' => 'dashboard', 'url' => base_url('superadmin_store_requests.php?focus=overview'), 'match' => 'focus=overview'],
        ['label' => __('nav.referrals'), 'icon' => 'key', 'url' => base_url('superadmin_store_requests.php?focus=referrals'), 'match' => 'focus=referrals'],
        ['label' => __('nav.store_requests'), 'icon' => 'store_mall_directory', 'url' => base_url('superadmin_store_requests.php?focus=requests'), 'match' => 'focus=requests'],
        ['label' => __('nav.active_stores'), 'icon' => 'storefront', 'url' => base_url('superadmin_store_requests.php?focus=stores'), 'match' => 'focus=stores'],
        ['label' => __('nav.tenant_users'), 'icon' => 'groups', 'url' => base_url('superadmin_users.php'), 'match' => 'superadmin_user'],
        ['label' => __('nav.maintenance'), 'icon' => 'construction', 'url' => base_url('superadmin_store_requests.php?focus=maintenance'), 'match' => 'focus=maintenance'],
        ['label' => 'Telegram Sistem', 'icon' => 'notifications', 'url' => base_url('admin_notifications.php'), 'match' => 'admin_notifications.php'],
        ['label' => 'System Health', 'icon' => 'monitor_heart', 'url' => base_url('system_health.php'), 'match' => 'system_health.php'],
        ['label' => __('nav.audit'), 'icon' => 'fact_check', 'url' => base_url('admin_audit.php'), 'match' => 'admin_audit.php'],
        ['label' => __('nav.backup'), 'icon' => 'backup', 'url' => base_url('superadmin_backup.php'), 'match' => 'superadmin_backup.php'],
    ];
} elseif ($role === 'admin') {
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
        ['label' => __('nav.store_info'), 'icon' => 'storefront', 'url' => base_url('admin_store.php'), 'match' => 'admin_store.php'],
        ['label' => __('nav.telegram'), 'icon' => 'notifications', 'url' => base_url('admin_notifications.php'), 'match' => 'admin_notifications.php'],
        ['label' => __('nav.refund'), 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => __('nav.transaction'), 'icon' => 'receipt_long', 'url' => base_url('admin_transactions.php'), 'match' => 'admin_transactions.php'],
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
        ['label' => __('nav.store_info'), 'icon' => 'storefront', 'url' => base_url('admin_store.php'), 'match' => 'admin_store.php'],
        ['label' => __('nav.promo'), 'icon' => 'local_offer', 'url' => base_url('admin_promos.php'), 'match' => 'admin_promos.php'],
        ['label' => __('nav.refund'), 'icon' => 'undo', 'url' => base_url('admin_refunds.php'), 'match' => 'admin_refunds.php'],
        ['label' => __('nav.cashier'), 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => __('nav.item_report'), 'icon' => 'list_alt', 'url' => base_url('bos_items.php'), 'match' => 'bos_items.php'],
    ];
} elseif ($role === 'karyawan') {
    $items = [
        ['label' => __('nav.cashier'), 'icon' => 'point_of_sale', 'url' => base_url('kasir.php'), 'match' => 'kasir.php'],
        ['label' => __('nav.shift'), 'icon' => 'schedule', 'url' => base_url('shift.php'), 'match' => 'shift.php'],
        ['label' => __('nav.history'), 'icon' => 'receipt_long', 'url' => base_url('kasir_history.php'), 'match' => 'kasir_history.php'],
    ];
}

if ($supportEnabled && in_array($role, ['superadmin', 'admin', 'bos'], true)) {
    $insertAfter = $role === 'superadmin' ? 'focus=maintenance' : 'admin_store.php';
    $supportItem = ['label' => __('nav.support'), 'icon' => 'support_agent', 'url' => base_url('support_chat.php'), 'match' => 'support_chat.php'];
    $inserted = false;
    foreach ($items as $index => $item) {
        if (($item['match'] ?? '') === $insertAfter) {
            array_splice($items, $index + 1, 0, [$supportItem]);
            $inserted = true;
            break;
        }
    }
    if (!$inserted) {
        $items[] = $supportItem;
    }
}
?>

<aside class="kp-sidebar">
    <div class="kp-sidebar-inner p-6 flex flex-col h-full">
        <!-- Brand / Logo -->
        <div class="kp-sidebar-brand flex items-center gap-3 mb-8">
            <button
                type="button"
                class="kp-sidebar-brandtoggle kp-sidebar-brandmark flex items-center justify-center text-white shadow-lg shadow-emerald-200 flex-shrink-0 transition-transform duration-300"
                id="sidebarToggle"
                title="Sembunyikan sidebar"
                aria-label="Sembunyikan sidebar"
                aria-pressed="false"
            >
                <img src="<?php echo e(base_url('assets/images/logo.jpg')); ?>" alt="KASPINDO" class="w-full h-full object-contain rounded-[14px]">
            </button>
            <div class="kp-hide-on-collapse">
                <h1 class="text-lg font-bold tracking-tight text-slate-900 leading-none">KASPINDO</h1>
                <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mt-1">Professional POS</p>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="space-y-1 kp-sidebar-menu">
            <p class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2 kp-hide-on-collapse">Main Menu</p>
            <nav class="kp-nav space-y-1">
                <?php foreach ($items as $item): ?>
                    <?php $active = strpos($currentPath, $item['match']) !== false; ?>
                    <a
                        href="<?php echo e($item['url']); ?>"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group relative <?php echo $active ? 'bg-emerald-50 text-emerald-600 font-semibold shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>"
                        <?php echo !empty($item['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                        title="<?php echo e($item['label']); ?>"
                    >
                        <span class="material-icons-outlined text-[20px] flex-shrink-0 transition-colors <?php echo $active ? 'text-emerald-600' : 'text-slate-400 group-hover:text-emerald-500'; ?>">
                            <?php echo e($item['icon']); ?>
                        </span>
                        <span class="text-sm kp-hide-on-collapse whitespace-nowrap"><?php echo e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>

                <a
                    href="<?php echo e(base_url('logout.php')); ?>"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group relative text-slate-600 hover:bg-slate-50 hover:text-slate-900"
                    title="Keluar Sistem"
                >
                    <span class="material-icons-outlined text-[20px] flex-shrink-0 transition-colors text-slate-400 group-hover:text-slate-600">logout</span>
                    <span class="text-sm kp-hide-on-collapse whitespace-nowrap">Keluar Sistem</span>
                </a>
            </nav>

            <p class="text-center text-[10px] text-slate-400 mt-4 font-medium kp-hide-on-collapse"><?php echo e(__('app.footer_version')); ?></p>
        </div>
    </div>
</aside>

<script>
    (function() {
        const root = document.documentElement;
        const storedState = localStorage.getItem('kp_sidebar_collapsed');
        const toggleBtn = document.getElementById('sidebarToggle');

        const syncToggleState = () => {
            if (!toggleBtn) {
                return;
            }

            const isCollapsed = root.classList.contains('kp-sidebar-collapsed');
            const nextLabel = isCollapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar';
            toggleBtn.setAttribute('aria-pressed', isCollapsed ? 'true' : 'false');
            toggleBtn.setAttribute('aria-label', nextLabel);
            toggleBtn.setAttribute('title', nextLabel);
        };

        if (storedState === 'true') {
            root.classList.add('kp-sidebar-collapsed');
        }
        syncToggleState();

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const isCollapsed = root.classList.toggle('kp-sidebar-collapsed');
                localStorage.setItem('kp_sidebar_collapsed', isCollapsed);
                syncToggleState();
            });
        }
    })();
</script>

<!-- Mobile Sidebar Drawer -->
<div class="offcanvas offcanvas-start border-0" tabindex="-1" id="sidebarDrawer" style="width: 280px;">
    <div class="offcanvas-header p-6 pb-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-200 overflow-hidden">
                <img src="<?php echo e(base_url('assets/images/logo.jpg')); ?>" alt="KASPINDO" class="w-full h-full object-contain">
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-tight text-slate-900 leading-none">KASPINDO</h1>
                <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mt-1">Professional POS</p>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-6">
        <div class="space-y-1">
            <p class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Main Menu</p>
            <nav class="kp-nav space-y-1">
                <?php foreach ($items as $item): ?>
                    <?php $active = strpos($currentPath, $item['match']) !== false; ?>
                    <a
                        href="<?php echo e($item['url']); ?>"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group <?php echo $active ? 'bg-emerald-50 text-emerald-600 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>"
                        <?php echo !empty($item['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                    >
                        <span class="material-icons-outlined text-[20px] <?php echo $active ? 'text-emerald-600' : 'text-slate-400 group-hover:text-slate-600'; ?>">
                            <?php echo e($item['icon']); ?>
                        </span>
                        <span class="text-sm"><?php echo e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</div>
