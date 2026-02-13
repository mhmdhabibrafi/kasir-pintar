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
        <h2 class="kp-page-title">Kelola Transaksi</h2>
        <p class="kp-page-subtitle">Pilih transaksi yang ingin dihapus atau bersihkan semuanya.</p>
    </div>
    <div class="kp-page-actions">
        <form method="POST" onsubmit="return confirm('Hapus semua transaksi? Tindakan ini tidak bisa dibatalkan.');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="clear_all">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" name="void_reason" class="form-control form-control-sm" placeholder="Alasan hapus transaksi" required>
                <button class="btn kp-btn-ghost" type="submit">
                    <span class="material-icons-outlined">delete_forever</span>
                    Clear Semua
                </button>
            </div>
        </form>
    </div>
</div>

<div class="kp-filter-card mb-4">
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

<div class="kp-grid kp-grid-3 mb-4">
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">receipt_long</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total Transaksi</div>
                <div class="kp-kpi-value"><?php echo e((string) count($transactions)); ?></div>
            </div>
        </div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">payments</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total Harga Jual</div>
                <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($rangeSummary['sales'] ?? 0))); ?></div>
            </div>
        </div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">undo</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total Refund</div>
                <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($refundTotal ?? 0))); ?></div>
            </div>
        </div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">trending_up</span>
            </span>
            <div>
                <div class="kp-kpi-label">Total Profit</div>
                <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($rangeSummary['profit'] ?? 0))); ?></div>
            </div>
        </div>
    </div>
    <div class="kp-card-flat p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
        <div class="kp-stat-card">
            <span class="kp-stat-icon">
                <span class="material-icons-outlined">payments</span>
            </span>
            <div>
                <div class="kp-kpi-label">Net Penjualan</div>
                <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($netSales ?? 0))); ?></div>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="bulkDeleteForm">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="delete_selected">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="kp-muted small">Centang transaksi yang ingin dihapus.</div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <input type="text" name="void_reason" class="form-control form-control-sm" placeholder="Alasan hapus transaksi" required>
            <button class="btn kp-btn-ghost" type="submit">
                <span class="material-icons-outlined">delete</span>
                Hapus Terpilih
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table kp-table align-middle">
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                    </th>
                    <th>ID</th>
                    <th>Tanggal</th>
                    <th class="d-none d-md-table-cell">Kasir</th>
                    <th>Metode</th>
                    <th>Total Jual</th>
                    <th class="d-none d-lg-table-cell">Modal</th>
                    <th class="d-none d-lg-table-cell">Profit</th>
                    <th class="d-none d-md-table-cell">Item</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $row): ?>
                    <tr>
                        <td>
                            <input class="form-check-input" type="checkbox" name="ids[]" value="<?php echo (int) $row['id']; ?>">
                        </td>
                        <td class="fw-semibold">#<?php echo (int) $row['id']; ?></td>
                        <td>
                            <div class="fw-semibold"><?php echo e(date('d/m/Y', strtotime($row['created_at']))); ?></div>
                            <div class="kp-muted small"><?php echo e(date('H:i', strtotime($row['created_at']))); ?></div>
                        </td>
                        <td class="d-none d-md-table-cell"><?php echo e($row['cashier'] ?? '-'); ?></td>
                        <td><span class="badge rounded-pill text-bg-light"><?php echo e(strtoupper($row['method'] ?? '-')); ?></span></td>
                        <td class="fw-semibold"><?php echo e(format_rupiah((float) ($row['total_sales'] ?? $row['total_amount']))); ?></td>
                        <td class="d-none d-lg-table-cell"><?php echo e(format_rupiah((float) ($row['total_cost'] ?? 0))); ?></td>
                        <td class="d-none d-lg-table-cell"><?php echo e(format_rupiah((float) ($row['total_profit'] ?? 0))); ?></td>
                        <td class="d-none d-md-table-cell">
                            <div class="kp-muted small"><?php echo (int) ($row['item_count'] ?? 0); ?> item</div>
                            <?php if (!empty($row['meta']['customer']['name'])): ?>
                                <div class="kp-muted small">Member: <?php echo e((string) ($row['meta']['customer']['name'] ?? '-')); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['items'])): ?>
                                <div class="small">
                                    <?php foreach (array_slice($row['items'], 0, 2) as $item): ?>
                                        <div><?php echo e($item['name']); ?> <span class="kp-muted">x<?php echo (int) $item['qty']; ?></span></div>
                                    <?php endforeach; ?>
                                    <?php if (count($row['items']) > 2): ?>
                                        <div class="kp-muted">+<?php echo count($row['items']) - 2; ?> item</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="9" class="text-center kp-muted">Belum ada transaksi.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('input[name="ids[]"]');
    const bulkForm = document.getElementById('bulkDeleteForm');

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    if (bulkForm) {
        bulkForm.addEventListener('submit', (event) => {
            const anyChecked = Array.from(checkboxes).some((checkbox) => checkbox.checked);
            if (!anyChecked) {
                event.preventDefault();
                alert('Pilih transaksi yang ingin dihapus.');
                return;
            }
            if (!confirm('Hapus transaksi yang dipilih?')) {
                event.preventDefault();
            }
        });
    }
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
