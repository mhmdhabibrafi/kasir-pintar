<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$categoriesMap = [];
foreach ($categories as $category) {
    $categoriesMap[$category['id']] = $category['name'];
}

$currentImagePath = ($imageColumn && is_array($product)) ? trim((string) ($product[$imageColumn] ?? '')) : '';
$currentImageUrl = $currentImagePath !== '' ? base_url($currentImagePath) : '';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-6 max-w-4xl mx-auto">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success mb-6 max-w-4xl mx-auto">
        <?php echo e($success); ?>
    </div>
<?php endif; ?>

<div class="card p-6 max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?php echo e(base_url('admin_products.php')); ?>" class="btn btn-secondary w-10 h-10 p-0 flex items-center justify-center rounded-xl text-slate-500 hover:text-slate-900">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h2 class="card-title text-xl m-0">Edit Produk</h2>
    </div>

    <?php if ($currentImageUrl !== ''): ?>
        <div class="mb-6 flex items-start gap-4 p-4 rounded-xl border border-slate-200 bg-slate-50">
            <img
                src="<?php echo e($currentImageUrl); ?>"
                alt="Gambar produk"
                class="w-20 h-20 object-cover rounded-lg border border-slate-200 shadow-sm shrink-0 bg-white"
            >
            <div>
                <div class="font-bold text-slate-900 mb-1">Gambar Saat Ini</div>
                <div class="text-xs text-muted">Gambar yang sedang digunakan untuk produk ini.</div>
            </div>
        </div>
    <?php endif; ?>
        <?php if ($product): ?>
            <?php if ($isAdmin): ?>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="form-group mb-0">
                            <label class="form-label">Nama Produk</label>
                            <input type="text" name="name" class="form-input w-full" value="<?php echo e((string) ($formState['name'] ?? '')); ?>" required>
                        </div>
                        <?php if ($skuColumn): ?>
                            <div class="form-group mb-0">
                                <label class="form-label">SKU/Kode</label>
                                <input type="text" name="sku" class="form-input w-full" value="<?php echo e((string) ($formState['sku'] ?? '')); ?>" required>
                            </div>
                        <?php endif; ?>
                        <div class="form-group mb-0">
                            <label class="form-label">Harga Jual</label>
                            <input type="number" name="price" min="0" step="0.01" class="form-input w-full" value="<?php echo e((string) ($formState['price'] ?? '0')); ?>" required>
                        </div>
                        <?php if ($costColumn): ?>
                            <div class="form-group mb-0">
                                <label class="form-label">Harga Modal</label>
                                <input type="number" name="cost_price" min="0" step="0.01" class="form-input w-full" value="<?php echo e((string) ($formState['cost_price'] ?? '0')); ?>" required>
                            </div>
                        <?php endif; ?>
                        <?php if ($categoryColumn): ?>
                            <div class="form-group mb-0">
                                <label class="form-label">Kategori</label>
                                <select name="category_id" class="form-input w-full">
                                    <option value="">Gunakan kategori saat ini / default</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo (int) $category['id']; ?>" <?php echo ((string) ($formState['category_id'] ?? '') === (string) $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="text-xs text-muted mt-1.5">Kosongkan jika ingin mempertahankan kategori saat ini.</div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Kategori Baru</label>
                                <input type="text" name="category_name_new" class="form-input w-full" value="<?php echo e((string) ($formState['category_name_new'] ?? '')); ?>" placeholder="Isi jika ingin buat kategori baru">
                            </div>
                        <?php endif; ?>
                        <?php if ($activeColumn): ?>
                            <div class="form-group mb-0">
                                <label class="form-label">Status</label>
                                <select name="active" class="form-input w-full">
                                    <option value="1" <?php echo ((string) ($formState['active'] ?? '1') === '1') ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="0" <?php echo ((string) ($formState['active'] ?? '1') === '0') ? 'selected' : ''; ?>>Nonaktif</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="form-group mb-0">
                            <label class="flex items-center gap-2 cursor-pointer pt-8">
                                <input class="form-check-input mt-0" type="checkbox" name="track_inventory" value="1" <?php echo !empty($formState['track_inventory']) ? 'checked' : ''; ?>>
                                <span class="text-sm font-medium text-slate-700">Track stok produk ini</span>
                            </label>
                            <div class="text-xs text-muted mt-1.5 pl-6">Jika dimatikan, stok produk tidak dibatasi inventori.</div>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label">Stok Saat Ini</label>
                            <input type="number" name="stock" min="0" step="1" class="form-input w-full" value="<?php echo e((string) ($formState['stock'] ?? '')); ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label">Min. Stok</label>
                            <input type="number" name="min_stock" min="0" step="1" class="form-input w-full" value="<?php echo e((string) ($formState['min_stock'] ?? '0')); ?>">
                        </div>
                        <div class="form-group mb-0 md:col-span-2">
                            <label class="form-label">Upload Gambar Baru</label>
                            <input type="file" name="product_image" class="form-input w-full file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="text-xs text-muted mt-1.5">Opsional, maksimal 4MB. Gambar lama akan tertimpa.</div>
                        </div>
                        <?php if ($currentImageUrl !== ''): ?>
                            <div class="form-group mb-0 md:col-span-2 pt-2 border-t border-slate-100">
                                <label class="flex items-center gap-2 cursor-pointer text-red-600 hover:text-red-700">
                                    <input class="form-check-input border-red-300 checked:bg-red-500 checked:border-red-500" type="checkbox" id="remove_image" name="remove_image" value="1">
                                    <span class="text-sm font-medium">Hapus gambar produk saat ini secara permanen</span>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-8 pt-6 border-t border-border flex justify-end gap-3">
                        <a class="btn btn-secondary" href="<?php echo e(base_url('admin_products.php')); ?>">Batal</a>
                        <button class="btn btn-primary" type="submit">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="form-group mb-0">
                        <label class="form-label">Nama Produk</label>
                        <input type="text" class="form-input w-full bg-slate-50" value="<?php echo e($product['name']); ?>" readonly>
                    </div>
                    <?php if ($skuColumn): ?>
                        <div class="form-group mb-0">
                            <label class="form-label">SKU/Kode</label>
                            <input type="text" class="form-input w-full bg-slate-50" value="<?php echo e($product[$skuColumn] ?? '-'); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    <div class="form-group mb-0">
                        <label class="form-label">Harga Jual</label>
                        <input type="text" class="form-input w-full bg-slate-50" value="<?php echo e(format_rupiah((float) $product['price'])); ?>" readonly>
                    </div>
                    <?php if ($costColumn && $isAdmin): ?>
                        <div class="form-group mb-0">
                            <label class="form-label">Harga Modal</label>
                            <input type="text" class="form-input w-full bg-slate-50" value="<?php echo e(format_rupiah((float) ($product['cost_price'] ?? 0))); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    <?php if ($categoryColumn): ?>
                        <div class="form-group mb-0">
                            <label class="form-label">Kategori</label>
                            <input type="text" class="form-input w-full bg-slate-50" value="<?php echo e($categoriesMap[$product[$categoryColumn]] ?? '-'); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    <?php if ($activeColumn): ?>
                        <div class="form-group mb-0">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-input w-full bg-slate-50" value="<?php echo ((int) ($product[$activeColumn] ?? 1) === 1) ? 'Aktif' : 'Nonaktif'; ?>" readonly>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-8 pt-6 border-t border-border flex justify-end">
                    <a class="btn btn-secondary" href="<?php echo e(base_url('admin_products.php')); ?>">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Kembali ke Daftar
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="p-8 text-center text-slate-500">
                <i data-lucide="info" class="w-8 h-8 mx-auto mb-3 opacity-50"></i>
                <p class="m-0">Produk tidak ditemukan atau telah dihapus.</p>
                <a class="btn btn-secondary mt-4" href="<?php echo e(base_url('admin_products.php')); ?>">Kembali</a>
            </div>
        <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
