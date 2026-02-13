<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$categoriesMap = [];
foreach ($categories as $category) {
    $categoriesMap[$category['id']] = $category['name'];
}
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
        <h2 class="h4 mb-3">Edit Produk</h2>
        <?php if ($product): ?>
            <?php if ($isAdmin): ?>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Produk</label>
                            <input type="text" name="name" class="form-control" value="<?php echo e($product['name']); ?>" required>
                        </div>
                        <?php if ($skuColumn): ?>
                            <div class="col-md-6">
                                <label class="form-label">SKU/Kode</label>
                                <input type="text" name="sku" class="form-control" value="<?php echo e($product[$skuColumn] ?? ''); ?>" required>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label">Harga Jual</label>
                            <input type="number" name="price" min="0" step="0.01" class="form-control" value="<?php echo e((string) $product['price']); ?>" required>
                        </div>
                        <?php if ($costColumn): ?>
                            <div class="col-md-6">
                                <label class="form-label">Harga Modal</label>
                                <input type="number" name="cost_price" min="0" step="0.01" class="form-control" value="<?php echo e((string) ($product['cost_price'] ?? 0)); ?>" required>
                            </div>
                        <?php endif; ?>
                        <?php if ($categoryColumn): ?>
                            <div class="col-md-6">
                                <label class="form-label">Kategori</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Pilih kategori</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo (int) $category['id']; ?>" <?php echo ((int) $category['id'] === (int) $product[$categoryColumn]) ? 'selected' : ''; ?>>
                                            <?php echo e($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <?php if ($activeColumn): ?>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="active" class="form-select">
                                    <option value="1" <?php echo ((int) ($product[$activeColumn] ?? 1) === 1) ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="0" <?php echo ((int) ($product[$activeColumn] ?? 1) === 0) ? 'selected' : ''; ?>>Nonaktif</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                    <button class="btn kp-btn-primary" type="submit">Simpan Perubahan</button>
                    <a class="btn kp-btn-ghost" href="<?php echo e(base_url('admin_products.php')); ?>">Kembali</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama Produk</label>
                        <input type="text" class="form-control" value="<?php echo e($product['name']); ?>" readonly>
                    </div>
                    <?php if ($skuColumn): ?>
                        <div class="col-md-6">
                            <label class="form-label">SKU/Kode</label>
                            <input type="text" class="form-control" value="<?php echo e($product[$skuColumn] ?? '-'); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label">Harga Jual</label>
                        <input type="text" class="form-control" value="<?php echo e(format_rupiah((float) $product['price'])); ?>" readonly>
                    </div>
                    <?php if ($costColumn && $isAdmin): ?>
                        <div class="col-md-6">
                            <label class="form-label">Harga Modal</label>
                            <input type="text" class="form-control" value="<?php echo e(format_rupiah((float) ($product['cost_price'] ?? 0))); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    <?php if ($categoryColumn): ?>
                        <div class="col-md-6">
                            <label class="form-label">Kategori</label>
                            <input type="text" class="form-control" value="<?php echo e($categoriesMap[$product[$categoryColumn]] ?? '-'); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    <?php if ($activeColumn): ?>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-control" value="<?php echo ((int) ($product[$activeColumn] ?? 1) === 1) ? 'Aktif' : 'Nonaktif'; ?>" readonly>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-4">
                    <a class="btn kp-btn-ghost" href="<?php echo e(base_url('admin_products.php')); ?>">Kembali</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="kp-muted">Produk tidak ditemukan.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
