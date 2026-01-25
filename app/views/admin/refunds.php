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
        <h2 class="kp-page-title">Refund & Retur</h2>
        <p class="kp-page-subtitle">Catat refund tanpa menghapus transaksi.</p>
    </div>
</div>

<div class="kp-card-flat p-4 mb-4">
    <div class="fw-semibold mb-2">Input Refund</div>
    <form method="POST" class="kp-filter-form">
        <?php echo csrf_field(); ?>
        <div>
            <label class="form-label">ID Transaksi</label>
            <input type="number" name="transaction_id" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Nominal Refund</label>
            <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Metode</label>
            <select name="method" class="form-select">
                <option value="cash">Cash</option>
                <option value="qris">QRIS</option>
            </select>
        </div>
        <div>
            <label class="form-label">Alasan</label>
            <input type="text" name="reason" class="form-control" placeholder="Contoh: pesanan dibatalkan" required>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="restock" id="restockCheck">
            <label class="form-check-label" for="restockCheck">Restock barang (khusus refund full)</label>
        </div>
        <div>
            <button class="btn kp-btn-primary">
                <span class="material-icons-outlined">undo</span>
                Simpan Refund
            </button>
        </div>
    </form>
</div>

<div class="kp-card-flat p-4 mb-3">
    <div class="fw-semibold mb-2">Filter Refund</div>
    <form method="GET" class="kp-filter-form">
        <div>
            <label class="form-label">Dari</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo e($filters['start_date']); ?>">
        </div>
        <div>
            <label class="form-label">Sampai</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo e($filters['end_date']); ?>">
        </div>
        <div>
            <label class="form-label">Metode</label>
            <select name="method" class="form-select">
                <option value="all" <?php echo ($filters['method'] === 'all') ? 'selected' : ''; ?>>Semua</option>
                <option value="cash" <?php echo ($filters['method'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                <option value="qris" <?php echo ($filters['method'] === 'qris') ? 'selected' : ''; ?>>QRIS</option>
            </select>
        </div>
        <div>
            <button class="btn kp-btn-primary">
                <span class="material-icons-outlined">filter_alt</span>
                Filter
            </button>
        </div>
    </form>
</div>

<div class="kp-card-flat p-4">
    <div class="fw-semibold mb-2">Daftar Refund</div>
    <?php if (!empty($refunds)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Transaksi</th>
                        <th>Nominal</th>
                        <th>Metode</th>
                        <th>Alasan</th>
                        <th>Restock</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($refunds as $refund): ?>
                        <tr>
                            <td><?php echo e($refund['id'] ?? '-'); ?></td>
                            <td>#<?php echo e((string) ($refund['transaction_id'] ?? '-')); ?></td>
                            <td><?php echo e(format_rupiah((float) ($refund['amount'] ?? 0))); ?></td>
                            <td><?php echo e(strtoupper((string) ($refund['method'] ?? '-'))); ?></td>
                            <td><?php echo e($refund['reason'] ?? '-'); ?></td>
                            <td><?php echo !empty($refund['restock']) ? 'Ya' : 'Tidak'; ?></td>
                            <td><?php echo e($refund['created_at'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="kp-muted">Belum ada refund.</div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
