<?php
require_once __DIR__ . '/../layouts/header.php';
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

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title">Kelola Stok</h2>
        <p class="kp-page-subtitle">Atur stok virtual tanpa ubah database.</p>
    </div>
</div>

<div class="kp-card-flat p-4">
    <div class="kp-muted small mb-3">Kosongkan stok jika produk tidak ingin ditrack.</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Min Stok</th>
                    <th>Catatan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <?php
                        $productId = (int) $product['id'];
                        $invItem = $inventoryItems[$productId] ?? [];
                        $stock = array_key_exists('stock', $invItem) ? $invItem['stock'] : '';
                        $min = $invItem['min'] ?? 0;
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo e($product['name'] ?? '-'); ?></div>
                            <?php if (!empty($product['category_name'])): ?>
                                <div class="kp-muted small"><?php echo e($product['category_name']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e(format_rupiah((float) ($product['price'] ?? 0))); ?></td>
                        <td>
                            <form method="POST" class="d-flex align-items-center gap-2">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                <input type="number" name="stock" class="form-control form-control-sm" value="<?php echo e((string) $stock); ?>" placeholder="-" min="0">
                        </td>
                        <td>
                                <input type="number" name="min" class="form-control form-control-sm" value="<?php echo e((string) $min); ?>" min="0">
                        </td>
                        <td>
                                <input type="text" name="note" class="form-control form-control-sm" placeholder="Opsional">
                        </td>
                        <td>
                                <button class="btn kp-btn-primary btn-sm">Simpan</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="6" class="kp-muted text-center">Belum ada produk.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
