<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$reopenCreateModal = $isCreatePost && !empty($errors);
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
            <h2 class="kp-page-title">Master Kategori</h2>
            <p class="kp-page-subtitle">Rapikan pengelompokan produk agar input kasir dan pelaporan lebih konsisten.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoryCreate">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Tambah Kategori
        </button>
    </div>
</section>

<section class="kpi-grid mb-6">
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Kategori Tampil</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($categorySummary['visible_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Total kategori: <?php echo e((string) ($categorySummary['all_total'] ?? 0)); ?></div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Kategori Dipakai</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($categorySummary['used_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Sudah punya produk aktif.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Kategori Kosong</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($categorySummary['empty_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Bisa ditinjau atau dirapikan.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Produk Terkait</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($categorySummary['product_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Total produk pada hasil filter.</div>
    </div>
</section>

<section class="card kp-filter-card mb-6">
    <form method="GET" class="kp-filter-form">
        <div class="form-group mb-0">
            <label class="form-label">Cari Kategori</label>
            <input type="text" name="q" class="form-input" value="<?php echo e((string) ($categoryFilters['q'] ?? '')); ?>" placeholder="Contoh: minuman, kopi, snack">
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Penggunaan</label>
            <select name="usage" class="form-input">
                <option value="all" <?php echo (($categoryFilters['usage'] ?? 'all') === 'all') ? 'selected' : ''; ?>>Semua</option>
                <option value="used" <?php echo (($categoryFilters['usage'] ?? '') === 'used') ? 'selected' : ''; ?>>Sudah dipakai</option>
                <option value="empty" <?php echo (($categoryFilters['usage'] ?? '') === 'empty') ? 'selected' : ''; ?>>Belum dipakai</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-3 items-end">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>
                Terapkan
            </button>
            <a href="<?php echo e(base_url('admin_categories.php')); ?>" class="btn btn-secondary">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                Reset
            </a>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Daftar Kategori</h3>
            <p class="text-sm text-muted mb-0">Tabel formal untuk menjaga struktur katalog produk tetap rapi.</p>
        </div>
        <?php if (($categorySummary['visible_total'] ?? 0) !== ($categorySummary['all_total'] ?? 0)): ?>
            <span class="badge badge-neutral">Menampilkan <?php echo e((string) ($categorySummary['visible_total'] ?? 0)); ?> dari <?php echo e((string) ($categorySummary['all_total'] ?? 0)); ?> kategori</span>
        <?php endif; ?>
    </div>
    <div class="p-0">
        <div class="table-wrapper border-0 rounded-none">
            <table class="data-table data-table--sheet">
                <thead>
                    <tr>
                        <th>Nama Kategori</th>
                        <th class="text-center">Jumlah Produk</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <?php $totalProducts = (int) ($category['total_products'] ?? 0); ?>
                        <tr>
                            <td>
                                <div class="font-semibold text-slate-900"><?php echo e((string) ($category['name'] ?? '-')); ?></div>
                                <div class="text-xs text-slate-500 mt-1">Kategori untuk konsistensi produk dan laporan.</div>
                            </td>
                            <td class="text-center font-semibold text-slate-900"><?php echo e((string) $totalProducts); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $totalProducts > 0 ? 'badge-success' : 'badge-neutral'; ?>">
                                    <?php echo $totalProducts > 0 ? 'Dipakai' : 'Kosong'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <a class="btn btn-secondary btn-sm" href="<?php echo e(base_url('admin_category_edit.php?id=' . (int) ($category['id'] ?? 0))); ?>">
                                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                                        Edit
                                    </a>
                                    <form method="POST" onsubmit="return confirm('Hapus kategori ini?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) ($category['id'] ?? 0); ?>">
                                        <button class="btn btn-secondary btn-sm text-red-600 hover:text-red-700 hover:border-red-200 hover:bg-red-50" type="submit">
                                            <i data-lucide="trash" class="w-4 h-4"></i>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-10 text-slate-500">Tidak ada kategori yang cocok dengan filter saat ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalCategoryCreate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-2xl shadow-lg">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-b border-border p-5">
                    <h5 class="modal-title font-bold">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-6">
                    <div class="form-group mb-0">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="name" class="form-input w-full" value="<?php echo e((string) ($oldCreate['name'] ?? '')); ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-t border-border p-5">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($reopenCreateModal): ?>
    <script>
        window.addEventListener('load', function () {
            if (typeof bootstrap === 'undefined') {
                return;
            }
            const modalEl = document.getElementById('modalCategoryCreate');
            if (!modalEl) {
                return;
            }
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    </script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../layouts/footer.php';
