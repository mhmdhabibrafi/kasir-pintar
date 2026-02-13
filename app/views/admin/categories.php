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

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title">Manajemen Kategori</h2>
        <p class="kp-page-subtitle">Kelola daftar kategori produk.</p>
    </div>
    <div class="kp-page-actions">
        <button class="btn kp-btn-primary w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#modalCategoryCreate">
            <span class="material-icons-outlined">add</span>
            Tambah Kategori
        </button>
    </div>
</div>

<div class="kp-grid kp-grid-3">
    <?php foreach ($categories as $category): ?>
        <div class="kp-card-flat p-4 bg-white border border-slate-200 rounded-2xl shadow-sm transition hover:shadow-md">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold"><?php echo e($category['name']); ?></div>
                    <?php if ($categoryColumn): ?>
                        <div class="kp-muted small"><?php echo (int) $category['total_products']; ?> produk</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <a class="btn kp-btn-ghost btn-sm" href="<?php echo e(base_url('admin_category_edit.php?id=' . (int) $category['id'])); ?>">
                    <span class="material-icons-outlined">edit</span>
                    Edit
                </a>
                <form method="POST" onsubmit="return confirm('Hapus kategori ini?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $category['id']; ?>">
                    <button class="btn kp-btn-ghost btn-sm" type="submit">
                        <span class="material-icons-outlined">delete</span>
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($categories)): ?>
        <div class="kp-card-flat p-3 text-center kp-muted">Belum ada kategori.</div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalCategoryCreate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nama Kategori</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
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

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
