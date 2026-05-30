<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-6">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success mb-6">
        <?php echo e($success); ?>
    </div>
<?php endif; ?>

<section class="card p-0 mb-6">
    <div class="kp-sheet-toolbar">
        <div>
            <h2 class="kp-page-title">Kontrol Inventori</h2>
            <p class="kp-page-subtitle"><?php echo $isAdmin ? 'Pantau stok seperti lembar kerja operasional: jelas, cepat discan, dan mudah diperbarui per produk.' : 'Mode lihat saja untuk memantau kondisi stok outlet.'; ?></p>
        </div>
    </div>
</section>

<section class="kpi-grid mb-6">
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Produk Tampil</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($inventorySummary['visible_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Total referensi: <?php echo e((string) ($inventorySummary['all_total'] ?? 0)); ?> produk.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Ditrack</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($inventorySummary['tracked_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Stok dicatat otomatis di inventori.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Stok Kritis</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($inventorySummary['critical_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Menyentuh minimum stok.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Stok Habis</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($inventorySummary['out_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Perlu tindak restock segera.</div>
    </div>
</section>

<section class="card kp-filter-card mb-6">
    <form method="GET" class="kp-filter-form">
        <div class="form-group mb-0">
            <label class="form-label">Cari Produk</label>
            <input type="text" name="q" class="form-input" value="<?php echo e((string) ($inventoryFilters['q'] ?? '')); ?>" placeholder="Nama, SKU, atau kategori">
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Kondisi Stok</label>
            <select name="stock_status" class="form-input">
                <option value="all" <?php echo (($inventoryFilters['stock_status'] ?? 'all') === 'all') ? 'selected' : ''; ?>>Semua kondisi</option>
                <option value="critical" <?php echo (($inventoryFilters['stock_status'] ?? '') === 'critical') ? 'selected' : ''; ?>>Kritis</option>
                <option value="out" <?php echo (($inventoryFilters['stock_status'] ?? '') === 'out') ? 'selected' : ''; ?>>Habis</option>
                <option value="tracked" <?php echo (($inventoryFilters['stock_status'] ?? '') === 'tracked') ? 'selected' : ''; ?>>Ditrack</option>
                <option value="untracked" <?php echo (($inventoryFilters['stock_status'] ?? '') === 'untracked') ? 'selected' : ''; ?>>Manual</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-3 items-end">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>
                Terapkan
            </button>
            <a href="<?php echo e(base_url('admin_inventory.php')); ?>" class="btn btn-secondary">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                Reset
            </a>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Tabel Stok Produk</h3>
            <p class="text-sm text-muted mb-0">Kosongkan stok jika suatu produk tidak ingin dibatasi oleh inventori.</p>
        </div>
        <?php if (($inventorySummary['visible_total'] ?? 0) !== ($inventorySummary['all_total'] ?? 0)): ?>
            <span class="badge badge-neutral">Menampilkan <?php echo e((string) ($inventorySummary['visible_total'] ?? 0)); ?> dari <?php echo e((string) ($inventorySummary['all_total'] ?? 0)); ?> produk</span>
        <?php endif; ?>
    </div>
    <div class="p-0">
        <div class="table-wrapper border-0 rounded-none">
            <table class="data-table data-table--sheet data-table--compact">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-right">Harga</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Min</th>
                        <?php if ($isAdmin): ?>
                            <th>Catatan</th>
                            <th class="text-right">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $productId = (int) ($product['id'] ?? 0);
                        $formId = 'inventory-form-' . $productId;
                        $isTracked = !empty($product['inventory_tracked']);
                        $isCritical = !empty($product['is_critical_stock']);
                        $isOut = !empty($product['is_out_of_stock']);
                        ?>
                        <tr>
                            <td>
                                <div class="min-w-[220px]">
                                    <div class="font-semibold text-slate-900"><?php echo e((string) ($product['name'] ?? '-')); ?></div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        <?php if (!empty($product['category_name'])): ?>
                                            <?php echo e((string) ($product['category_name'] ?? '')); ?>
                                        <?php else: ?>
                                            Tanpa kategori
                                        <?php endif; ?>
                                        <?php if (!empty($product['sku'])): ?>
                                            <span class="mx-1 text-slate-300">|</span> SKU <?php echo e((string) ($product['sku'] ?? '')); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right font-semibold text-slate-900"><?php echo e(format_rupiah((float) ($product['price'] ?? 0))); ?></td>
                            <td class="text-center">
                                <div class="flex flex-wrap justify-center gap-2">
                                    <span class="badge <?php echo $isTracked ? ($isOut ? 'badge-danger' : ($isCritical ? 'badge-warning' : 'badge-success')) : 'badge-neutral'; ?>">
                                        <?php echo $isTracked ? ($isOut ? 'Habis' : ($isCritical ? 'Kritis' : 'Aman')) : 'Manual'; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($isAdmin): ?>
                                    <input type="number" name="stock" form="<?php echo e($formId); ?>" class="form-input !min-h-[42px] !rounded-2xl text-center" value="<?php echo e($isTracked ? (string) ($product['stock_on_hand'] ?? 0) : ''); ?>" placeholder="-" min="0">
                                <?php else: ?>
                                    <div class="font-semibold text-slate-900"><?php echo e($isTracked ? (string) ($product['stock_on_hand'] ?? 0) : '-'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isAdmin): ?>
                                    <input type="number" name="min" form="<?php echo e($formId); ?>" class="form-input !min-h-[42px] !rounded-2xl text-center" value="<?php echo e($isTracked ? (string) ($product['min_stock_value'] ?? 0) : '0'); ?>" min="0">
                                <?php else: ?>
                                    <div class="text-slate-600"><?php echo e($isTracked ? (string) ($product['min_stock_value'] ?? 0) : '-'); ?></div>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <input type="text" name="note" form="<?php echo e($formId); ?>" class="form-input !min-h-[42px] !rounded-2xl" placeholder="Catatan perubahan stok">
                                </td>
                                <td>
                                    <form method="POST" id="<?php echo e($formId); ?>" class="hidden">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="product_id" value="<?php echo e((string) $productId); ?>">
                                    </form>
                                    <div class="flex justify-end">
                                        <button class="btn btn-primary btn-sm" type="submit" form="<?php echo e($formId); ?>">
                                            <i data-lucide="save" class="w-4 h-4"></i>
                                            Simpan
                                        </button>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="<?php echo $isAdmin ? '7' : '5'; ?>" class="text-center py-10 text-slate-500">Tidak ada produk yang cocok dengan filter saat ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/../layouts/footer.php';
