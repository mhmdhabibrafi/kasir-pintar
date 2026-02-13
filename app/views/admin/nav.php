<?php
require_once __DIR__ . '/../../helpers/auth_helper.php';
?>

<div class="kp-card-flat p-3 mb-4 d-none">
    <div class="d-flex flex-wrap gap-2">
        <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin.php')); ?>">
            <span class="material-icons-outlined">dashboard</span>
            Dashboard
        </a>
        <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin_users.php')); ?>">
            <span class="material-icons-outlined">group</span>
            Kelola User
        </a>
        <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin_categories.php')); ?>">
            <span class="material-icons-outlined">category</span>
            Kelola Kategori
        </a>
        <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin_products.php')); ?>">
            <span class="material-icons-outlined">inventory_2</span>
            Kelola Produk
        </a>
    </div>
</div>
