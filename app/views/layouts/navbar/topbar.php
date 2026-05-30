<?php
require_once __DIR__ . '/../../../helpers/auth_helper.php';

$user = current_user();
$role = $user['role'] ?? 'guest';
$pageTitle = $pageTitle ?? $title ?? 'Dashboard';
$todayLabel = date('d M Y');
$roleLabel = $role !== 'guest' ? __('role.' . $role) : 'guest';
?>

<nav class="kp-topbar w-full">
    <div class="w-full px-6 flex justify-between items-center h-full">
        <div class="flex items-center gap-4">
            <button class="lg:hidden p-2 rounded-lg hover:bg-slate-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarDrawer">
                <span class="material-icons-outlined">menu</span>
            </button>
            <div>
                <h2 class="text-lg font-bold text-slate-900 leading-none mb-1"><?php echo e($pageTitle); ?></h2>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-widest"><?php echo e($todayLabel); ?></p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <?php if ($user): ?>
                <div class="hidden md:flex items-center gap-3 px-3 py-2 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center text-white text-xs font-bold shadow-sm">
                        <?php echo e(substr($user['name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-900 leading-none mb-1"><?php echo e($user['name']); ?></p>
                        <p class="text-[10px] text-slate-400 uppercase font-bold tracking-tight"><?php echo e($roleLabel); ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="<?php echo e(base_url('shift.php')); ?>" class="p-2 rounded-xl text-slate-400 hover:text-emerald-500 hover:bg-emerald-50 transition-all" title="Shift Status">
                        <span class="material-icons-outlined">schedule</span>
                    </a>
                    <a href="<?php echo e(base_url('logout.php')); ?>" class="p-2 rounded-xl text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all" title="Keluar">
                        <span class="material-icons-outlined">logout</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
