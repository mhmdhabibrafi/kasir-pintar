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

<div class="card kp-card bg-white border border-slate-200 rounded-2xl shadow-sm">
    <div class="card-body p-4">
        <h2 class="h4 mb-3">Edit Kategori</h2>
        <?php if ($category): ?>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="name" class="form-control" value="<?php echo e($category['name']); ?>" required>
                    </div>
                    <?php if ($categoryColumn): ?>
                        <div class="col-md-6">
                            <label class="form-label">Jumlah Produk</label>
                            <input type="text" class="form-control" value="<?php echo (int) $productTotal; ?>" readonly>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn kp-btn-primary" type="submit">Simpan Perubahan</button>
                    <a class="btn kp-btn-ghost" href="<?php echo e(base_url('admin_categories.php')); ?>">Kembali</a>
                </div>
            </form>
        <?php else: ?>
            <p class="kp-muted">Kategori tidak ditemukan.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
