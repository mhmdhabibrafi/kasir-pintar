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

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h2 class="h4 mb-1">Manajemen Produk</h2>
        <p class="kp-muted mb-0"><?php echo $isAdmin ? 'Kelola harga jual dan harga modal.' : 'Mode lihat saja.'; ?></p>
    </div>
    <?php if ($isAdmin): ?>
        <button class="btn kp-btn-primary" data-bs-toggle="modal" data-bs-target="#modalProductCreate">
            <span class="material-icons-outlined">add</span>
            Tambah Produk
        </button>
    <?php endif; ?>
</div>

<div class="kp-grid kp-grid-3">
    <?php foreach ($products as $product): ?>
        <div class="kp-card-flat p-4 bg-white border border-slate-200 rounded-2xl shadow-sm transition hover:shadow-md kp-admin-product-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold"><?php echo e($product['name']); ?></div>
                    <?php if ($skuColumn): ?>
                        <div class="kp-muted small">SKU: <?php echo e($product['sku'] ?? '-'); ?></div>
                    <?php endif; ?>
                    <?php if ($categoryColumn): ?>
                        <div class="kp-muted small"><?php echo e($product['category_name'] ?? '-'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <div class="fw-semibold">Harga Jual <?php echo e(format_rupiah((float) $product['price'])); ?></div>
                    <?php if ($costColumn && $isAdmin): ?>
                        <div class="kp-muted small">Modal <?php echo e(format_rupiah((float) ($product['cost_price'] ?? 0))); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($activeColumn): ?>
                    <span class="badge rounded-pill <?php echo ((int) ($product['is_active'] ?? 1) === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                        <?php echo ((int) ($product['is_active'] ?? 1) === 1) ? 'Aktif' : 'Nonaktif'; ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($isAdmin): ?>
                <div class="d-flex gap-2 mt-3 kp-admin-product-actions">
                    <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin_product_edit.php?id=' . (int) $product['id'])); ?>">
                        <span class="material-icons-outlined">edit</span>
                        Edit
                    </a>
                    <?php if ($activeColumn): ?>
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo (int) $product['id']; ?>">
                            <input type="hidden" name="active" value="<?php echo ((int) ($product['is_active'] ?? 1) === 1) ? 0 : 1; ?>">
                            <button class="btn kp-btn-ghost btn-sm" type="submit">
                                <span class="material-icons-outlined"><?php echo ((int) ($product['is_active'] ?? 1) === 1) ? 'visibility_off' : 'visibility'; ?></span>
                                <?php echo ((int) ($product['is_active'] ?? 1) === 1) ? 'Nonaktifkan' : 'Aktifkan'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Hapus produk ini?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $product['id']; ?>">
                        <button class="btn kp-btn-ghost btn-sm" type="submit">
                            <span class="material-icons-outlined">delete</span>
                            Hapus
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (empty($products)): ?>
        <div class="kp-card-flat p-3 text-center kp-muted">Belum ada produk.</div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
    <div class="modal fade" id="modalProductCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Produk</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama Produk</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <?php if ($skuColumn): ?>
                                <div class="col-md-6">
                                    <label class="form-label">SKU/Kode</label>
                                    <input type="text" name="sku" class="form-control" required>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <label class="form-label">Harga Jual</label>
                                <input type="number" name="price" min="0" step="0.01" class="form-control" required>
                            </div>
                            <?php if ($costColumn): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Harga Modal</label>
                                    <input type="number" name="cost_price" min="0" step="0.01" class="form-control" required>
                                </div>
                            <?php endif; ?>
                            <?php if ($categoryColumn): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Kategori</label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Pilih kategori</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo (int) $category['id']; ?>"><?php echo e($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <?php if ($activeColumn): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="active" class="form-select">
                                        <option value="1">Aktif</option>
                                        <option value="0">Nonaktif</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn kp-btn-ghost" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn kp-btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
