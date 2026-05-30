<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$isCreatePost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['action'] ?? '') === 'create';
$reopenCreateModal = $isAdmin && $isCreatePost && !empty($errors);
$oldCreate = $isCreatePost ? $_POST : [];
$oldTrackInventory = $isCreatePost ? isset($_POST['track_inventory']) : true;
$productTableColspan = 1 + ($categoryColumn ? 1 : 0) + 1 + ($costColumn ? 2 : 0) + 1 + 1 + ($isAdmin ? 1 : 0);
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
            <h2 class="kp-page-title">Master Produk</h2>
            <p class="kp-page-subtitle">Kelola katalog jual, status aktif, margin, dan kesiapan stok dalam format tabel yang lebih formal.</p>
        </div>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProductCreate">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Tambah Produk
            </button>
        <?php endif; ?>
    </div>
</section>

<section class="kpi-grid mb-6">
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Produk Tampil</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($productSummary['visible_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Dari total <?php echo e((string) ($productSummary['all_total'] ?? 0)); ?> produk.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Produk Aktif</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($productSummary['active_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Siap dijual di kasir.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Stok Kritis</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($productSummary['critical_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Perlu restock segera.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Tracking Inventori</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($productSummary['tracked_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Produk dipantau per stok aktual.</div>
    </div>
</section>

<section class="card kp-filter-card mb-6">
    <form method="GET" class="kp-filter-form">
        <div class="form-group mb-0">
            <label class="form-label">Cari Produk</label>
            <input type="text" name="q" class="form-input" value="<?php echo e((string) ($productFilters['q'] ?? '')); ?>" placeholder="Nama, SKU, atau kategori">
        </div>
        <?php if ($categoryColumn): ?>
            <div class="form-group mb-0">
                <label class="form-label">Kategori</label>
                <select name="category" class="form-input">
                    <option value="0">Semua kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int) $category['id']; ?>" <?php echo ((int) ($productFilters['category'] ?? 0) === (int) $category['id']) ? 'selected' : ''; ?>>
                            <?php echo e((string) ($category['name'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="form-group mb-0">
            <label class="form-label">Status Produk</label>
            <select name="status" class="form-input">
                <option value="all" <?php echo (($productFilters['status'] ?? 'all') === 'all') ? 'selected' : ''; ?>>Semua status</option>
                <option value="active" <?php echo (($productFilters['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Aktif</option>
                <option value="inactive" <?php echo (($productFilters['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Nonaktif</option>
            </select>
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Stok</label>
            <select name="stock" class="form-input">
                <option value="all" <?php echo (($productFilters['stock'] ?? 'all') === 'all') ? 'selected' : ''; ?>>Semua kondisi</option>
                <option value="critical" <?php echo (($productFilters['stock'] ?? '') === 'critical') ? 'selected' : ''; ?>>Kritis</option>
                <option value="out" <?php echo (($productFilters['stock'] ?? '') === 'out') ? 'selected' : ''; ?>>Habis</option>
                <option value="tracked" <?php echo (($productFilters['stock'] ?? '') === 'tracked') ? 'selected' : ''; ?>>Ditrack</option>
                <option value="untracked" <?php echo (($productFilters['stock'] ?? '') === 'untracked') ? 'selected' : ''; ?>>Manual</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-3 items-end">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>
                Terapkan
            </button>
            <a href="<?php echo e(base_url('admin_products.php')); ?>" class="btn btn-secondary">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                Reset
            </a>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Daftar Produk</h3>
            <p class="text-sm text-muted mb-0">Tampilan tabel dirapikan untuk pengecekan harga, margin, dan stok secara cepat.</p>
        </div>
        <?php if (($productSummary['visible_total'] ?? 0) !== ($productSummary['all_total'] ?? 0)): ?>
            <span class="badge badge-neutral">Menampilkan <?php echo e((string) ($productSummary['visible_total'] ?? 0)); ?> dari <?php echo e((string) ($productSummary['all_total'] ?? 0)); ?> produk</span>
        <?php endif; ?>
    </div>
    <div class="p-0">
        <div class="table-wrapper border-0 rounded-none">
            <table class="data-table data-table--sheet">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <?php if ($categoryColumn): ?>
                            <th>Kategori</th>
                        <?php endif; ?>
                        <th class="text-right">Harga Jual</th>
                        <?php if ($costColumn): ?>
                            <th class="text-right">Modal</th>
                            <th class="text-right">Margin</th>
                        <?php endif; ?>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Status</th>
                        <?php if ($isAdmin): ?>
                            <th class="text-right">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $productImage = trim((string) ($product['product_image'] ?? ''));
                        $productImageUrl = $productImage !== '' ? base_url($productImage) : '';
                        $isCritical = !empty($product['is_critical_stock']);
                        $isOut = !empty($product['is_out_of_stock']);
                        $isTracked = !empty($product['inventory_tracked']);
                        $isActiveRow = !empty($product['is_active_row']);
                        ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-3 min-w-[220px]">
                                    <?php if ($productImageUrl !== ''): ?>
                                        <img src="<?php echo e($productImageUrl); ?>" alt="<?php echo e((string) ($product['name'] ?? '')); ?>" class="w-12 h-12 rounded-2xl object-cover border border-slate-200 bg-white shrink-0">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center shrink-0">
                                            <i data-lucide="package" class="w-5 h-5"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-semibold text-slate-900"><?php echo e((string) ($product['name'] ?? '-')); ?></div>
                                        <div class="text-xs text-slate-500 mt-1">
                                            <?php if ($skuColumn): ?>
                                                SKU: <?php echo e((string) ($product['sku'] ?? '-')); ?>
                                            <?php else: ?>
                                                ID Produk: #<?php echo e((string) ($product['id'] ?? '0')); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <?php if ($categoryColumn): ?>
                                <td>
                                    <span class="badge badge-neutral"><?php echo e((string) ($product['category_name'] ?? 'Tanpa kategori')); ?></span>
                                </td>
                            <?php endif; ?>
                            <td class="text-right font-semibold text-slate-900"><?php echo e(format_rupiah((float) ($product['price'] ?? 0))); ?></td>
                            <?php if ($costColumn): ?>
                                <td class="text-right text-slate-600"><?php echo e(format_rupiah((float) ($product['cost_price'] ?? 0))); ?></td>
                                <td class="text-right font-semibold <?php echo ((float) ($product['margin_value'] ?? 0) >= 0) ? 'text-emerald-700' : 'text-red-600'; ?>">
                                    <?php echo e(format_rupiah((float) ($product['margin_value'] ?? 0))); ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-center">
                                <?php if ($isTracked): ?>
                                    <div class="font-semibold text-slate-900"><?php echo e((string) ($product['stock_on_hand'] ?? 0)); ?></div>
                                    <div class="text-xs text-slate-500">Min <?php echo e((string) ($product['min_stock_value'] ?? 0)); ?></div>
                                <?php else: ?>
                                    <span class="text-sm text-slate-400">Manual</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="flex flex-wrap justify-center gap-2">
                                    <span class="badge <?php echo $isActiveRow ? 'badge-success' : 'badge-neutral'; ?>">
                                        <?php echo $isActiveRow ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                    <?php if ($isTracked): ?>
                                        <span class="badge <?php echo $isOut ? 'badge-danger' : ($isCritical ? 'badge-warning' : 'badge-info'); ?>">
                                            <?php echo $isOut ? 'Habis' : ($isCritical ? 'Kritis' : 'Aman'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">Manual</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        <a class="btn btn-secondary btn-sm" href="<?php echo e(base_url('admin_product_edit.php?id=' . (int) ($product['id'] ?? 0))); ?>">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            Edit
                                        </a>
                                        <?php if ($activeColumn): ?>
                                            <form method="POST">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?php echo (int) ($product['id'] ?? 0); ?>">
                                                <input type="hidden" name="active" value="<?php echo $isActiveRow ? 0 : 1; ?>">
                                                <button class="btn btn-secondary btn-sm" type="submit">
                                                    <i data-lucide="<?php echo $isActiveRow ? 'eye-off' : 'eye'; ?>" class="w-4 h-4"></i>
                                                    <?php echo $isActiveRow ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" onsubmit="return confirm('Hapus produk ini?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int) ($product['id'] ?? 0); ?>">
                                            <button class="btn btn-secondary btn-sm text-red-600 hover:text-red-700 hover:border-red-200 hover:bg-red-50" type="submit">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="<?php echo e((string) $productTableColspan); ?>" class="text-center py-10 text-slate-500">
                                Tidak ada produk yang cocok dengan filter saat ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php if ($isAdmin): ?>
    <div class="modal fade" id="modalProductCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-2xl border-0 shadow-xl">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header border-b border-border p-5">
                        <h5 class="font-bold text-lg text-slate-900 m-0">Tambah Produk Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-6 bg-slate-50/50">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="form-group mb-0">
                                <label class="form-label">Nama Produk</label>
                                <input type="text" name="name" class="form-input w-full" value="<?php echo e((string) ($oldCreate['name'] ?? '')); ?>" required>
                            </div>
                            <?php if ($skuColumn): ?>
                                <div class="form-group mb-0">
                                    <label class="form-label">SKU / Kode</label>
                                    <input type="text" name="sku" class="form-input w-full" value="<?php echo e((string) ($oldCreate['sku'] ?? '')); ?>" required>
                                </div>
                            <?php endif; ?>
                            <div class="form-group mb-0">
                                <label class="form-label">Harga Jual</label>
                                <input type="number" name="price" min="0" step="0.01" class="form-input w-full" value="<?php echo e((string) ($oldCreate['price'] ?? '')); ?>" required>
                            </div>
                            <?php if ($costColumn): ?>
                                <div class="form-group mb-0">
                                    <label class="form-label">Harga Modal</label>
                                    <input type="number" name="cost_price" min="0" step="0.01" class="form-input w-full" value="<?php echo e((string) ($oldCreate['cost_price'] ?? '')); ?>" required>
                                </div>
                            <?php endif; ?>
                            <?php if ($categoryColumn): ?>
                                <div class="form-group mb-0">
                                    <label class="form-label">Kategori</label>
                                    <select name="category_id" class="form-input w-full">
                                        <option value="">Pilih kategori yang sudah ada</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo (int) $category['id']; ?>" <?php echo ((string) ($oldCreate['category_id'] ?? '') === (string) $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo e((string) ($category['name'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label">Kategori Baru</label>
                                    <input type="text" name="category_name_new" class="form-input w-full" value="<?php echo e((string) ($oldCreate['category_name_new'] ?? '')); ?>" placeholder="Opsional jika ingin buat kategori baru">
                                </div>
                            <?php endif; ?>
                            <?php if ($activeColumn): ?>
                                <div class="form-group mb-0">
                                    <label class="form-label">Status</label>
                                    <select name="active" class="form-input w-full">
                                        <option value="1" <?php echo ((string) ($oldCreate['active'] ?? '1') === '1') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="0" <?php echo ((string) ($oldCreate['active'] ?? '1') === '0') ? 'selected' : ''; ?>>Nonaktif</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="form-group mb-0">
                                <label class="flex items-center gap-2 cursor-pointer pt-8">
                                    <input class="form-check-input mt-0" type="checkbox" name="track_inventory" value="1" <?php echo $oldTrackInventory ? 'checked' : ''; ?>>
                                    <span class="text-sm font-medium text-slate-700">Track stok produk ini</span>
                                </label>
                                <div class="text-xs text-muted mt-1.5 pl-6">Jika aktif, stok akan dipantau dari inventori.</div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Stok Awal</label>
                                <input type="number" name="stock" min="0" step="1" class="form-input w-full" value="<?php echo e((string) ($oldCreate['stock'] ?? '0')); ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Minimum Stok</label>
                                <input type="number" name="min_stock" min="0" step="1" class="form-input w-full" value="<?php echo e((string) ($oldCreate['min_stock'] ?? '0')); ?>">
                            </div>
                            <div class="md:col-span-2 form-group mb-0">
                                <label class="form-label">Gambar Produk</label>
                                <input type="file" name="product_image" class="form-input w-full file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <div class="text-xs text-muted mt-1.5">Opsional, maksimal 4MB.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-t border-border p-5">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Produk</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($reopenCreateModal): ?>
    <script>
        window.addEventListener('load', function () {
            if (typeof bootstrap === 'undefined') {
                return;
            }
            const modalEl = document.getElementById('modalProductCreate');
            if (!modalEl) {
                return;
            }
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    </script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../layouts/footer.php';
