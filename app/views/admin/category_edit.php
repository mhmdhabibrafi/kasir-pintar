<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?php echo e($success); ?>
    </div>
<?php endif; ?>

<div class="card p-6 max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?php echo e(base_url('admin_categories.php')); ?>" class="btn btn-secondary w-10 h-10 p-0 flex items-center justify-center rounded-xl text-slate-500 hover:text-slate-900">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h2 class="card-title text-xl m-0">Edit Kategori</h2>
    </div>

    <?php if ($category): ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="form-group mb-0">
                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="name" class="form-input w-full" value="<?php echo e($category['name']); ?>" required>
                </div>
                <?php if ($categoryColumn): ?>
                    <div class="form-group mb-0">
                        <label class="form-label">Jumlah Produk</label>
                        <input type="text" class="form-input w-full bg-slate-50 text-slate-500" value="<?php echo (int) $productTotal; ?>" readonly>
                        <div class="text-xs text-muted mt-1.5">Jumlah produk yang terhubung dengan kategori ini.</div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-8 pt-6 border-t border-border flex justify-end gap-3">
                <a class="btn btn-secondary" href="<?php echo e(base_url('admin_categories.php')); ?>">Batal</a>
                <button class="btn btn-primary" type="submit">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="p-8 text-center text-slate-500">
            <i data-lucide="info" class="w-8 h-8 mx-auto mb-3 opacity-50"></i>
            <p class="m-0">Kategori tidak ditemukan atau telah dihapus.</p>
            <a class="btn btn-secondary mt-4" href="<?php echo e(base_url('admin_categories.php')); ?>">Kembali ke Daftar</a>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
