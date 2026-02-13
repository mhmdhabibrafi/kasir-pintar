<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/auth_helper.php';
require_once __DIR__ . '/../../../helpers/telegram_helper.php';

$user = current_user();
$user = $user ?: null;
if ($user) {
    telegram_maybe_send_daily_recap();
}
$role = $user['role'] ?? 'guest';
$pageTitle = $pageTitle ?? $title ?? 'Dashboard';
$todayLabel = date('d M Y');

$roleStyles = [
    'admin' => 'bg-primary-subtle text-primary',
    'bos' => 'bg-warning-subtle text-warning',
    'karyawan' => 'bg-success-subtle text-success',
];

$roleClass = $roleStyles[$role] ?? 'bg-secondary-subtle text-secondary';
$roleLabel = $role !== 'guest' ? __('role.' . $role) : 'guest';
?>

<nav class="navbar kp-topbar">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center w-100 kp-topbar-layout">
            <div class="kp-topbar-left">
                <div class="d-flex align-items-center gap-2 d-lg-none kp-topbar-brand">
                    <div class="kp-logo-wrap sm">
                        <img src="<?php echo e(base_url('assets/images/logo.jpg')); ?>" alt="KASPINDO">
                    </div>
                    <div class="kp-brand">KASPINDO</div>
                </div>
                <div class="fw-semibold kp-topbar-title"><?php echo e($pageTitle); ?></div>
                <div class="kp-muted small d-none d-md-block kp-topbar-date"><?php echo e($todayLabel); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3 kp-topbar-user">
                <?php if ($user): ?>
                    <div class="d-flex align-items-center gap-2 kp-user-block">
                        <span class="kp-avatar">
                            <span class="material-icons-outlined">person</span>
                        </span>
                        <div class="kp-user-meta">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="fw-semibold"><?php echo e($user['name']); ?></span>
                                <span class="badge <?php echo e($roleClass); ?>"><?php echo e($roleLabel); ?></span>
                            </div>
                        </div>
                    </div>
                    <a class="kp-icon-btn kp-logout-btn" href="<?php echo e(base_url('logout.php')); ?>" title="<?php echo e(__('topbar.logout')); ?>">
                        <span class="material-icons-outlined">logout</span>
                    </a>
                <?php else: ?>
                    <span class="kp-muted"><?php echo e(__('topbar.not_logged_in')); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
