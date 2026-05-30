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
            <h2 class="kp-page-title">Audit Transaksi</h2>
            <p class="kp-page-subtitle">Monitor transaksi kasir dengan tabel yang lebih formal dan aksi penghapusan yang lebih terkontrol.</p>
        </div>
    </div>
</section>

<section class="kpi-grid mb-6">
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Transaksi Tampil</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e((string) ($transactionSummary['visible_total'] ?? 0)); ?></div>
        <div class="text-sm text-slate-500 mt-2">Sesuai filter tanggal, metode, dan pencarian.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Penjualan Kotor</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e(format_rupiah((float) ($transactionSummary['gross_sales'] ?? 0))); ?></div>
        <div class="text-sm text-slate-500 mt-2">Total nilai jual sebelum refund.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Penjualan Bersih</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e(format_rupiah((float) ($transactionSummary['net_sales'] ?? 0))); ?></div>
        <div class="text-sm text-slate-500 mt-2">Refund periode: <?php echo e(format_rupiah((float) ($transactionSummary['refund_total'] ?? 0))); ?></div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400 mb-2">Rata-rata Tiket</div>
        <div class="text-3xl font-black tracking-tight text-slate-900"><?php echo e(format_rupiah((float) ($transactionSummary['average_ticket'] ?? 0))); ?></div>
        <div class="text-sm text-slate-500 mt-2">Laba kotor: <?php echo e(format_rupiah((float) ($transactionSummary['profit'] ?? 0))); ?></div>
    </div>
</section>

<section class="card kp-filter-card mb-6">
    <form method="GET" class="kp-filter-form">
        <div class="form-group mb-0">
            <label class="form-label">Tanggal Awal</label>
            <input type="date" name="start_date" class="form-input" value="<?php echo e((string) ($filters['start_date'] ?? '')); ?>">
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Tanggal Akhir</label>
            <input type="date" name="end_date" class="form-input" value="<?php echo e((string) ($filters['end_date'] ?? '')); ?>">
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Metode Bayar</label>
            <select name="method" class="form-input">
                <option value="all" <?php echo (($filters['method'] ?? 'all') === 'all') ? 'selected' : ''; ?>>Semua metode</option>
                <option value="cash" <?php echo (($filters['method'] ?? '') === 'cash') ? 'selected' : ''; ?>>Cash</option>
                <option value="qris" <?php echo (($filters['method'] ?? '') === 'qris') ? 'selected' : ''; ?>>QRIS</option>
            </select>
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Cari Transaksi</label>
            <input type="text" name="q" class="form-input" value="<?php echo e((string) ($filters['q'] ?? '')); ?>" placeholder="ID, kasir, member, atau item">
        </div>
        <div class="flex flex-wrap gap-3 items-end">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="filter" class="w-4 h-4"></i>
                Terapkan
            </button>
            <a href="<?php echo e(base_url('admin_transactions.php')); ?>" class="btn btn-secondary">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                Reset
            </a>
        </div>
    </form>
</section>

<form method="POST" id="bulkDeleteForm">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="delete_selected">

    <section class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Daftar Transaksi</h3>
                <p class="text-sm text-muted mb-0">Pilih transaksi yang memang perlu dihapus dan sertakan alasan audit.</p>
            </div>
            <div class="flex flex-wrap gap-3 items-center justify-end">
                <input type="text" name="void_reason" class="form-input w-[260px]" placeholder="Alasan penghapusan transaksi" required>
                <button class="btn btn-danger" type="submit">
                    <i data-lucide="trash" class="w-4 h-4"></i>
                    Hapus Terpilih
                </button>
            </div>
        </div>

        <div class="p-0">
            <div class="table-wrapper border-0 rounded-none">
                <table class="data-table data-table--sheet">
                    <thead>
                        <tr>
                            <th style="width: 44px;">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </th>
                            <th>Transaksi</th>
                            <th>Kasir / Member</th>
                            <th>Metode</th>
                            <th class="text-right">Penjualan</th>
                            <th class="text-right">Modal</th>
                            <th class="text-right">Profit</th>
                            <th>Item</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $row): ?>
                            <tr>
                                <td class="text-center">
                                    <input class="form-check-input" type="checkbox" name="ids[]" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-900">#<?php echo e((string) ($row['id'] ?? '0')); ?></div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        <?php echo e(date('d/m/Y H:i', strtotime((string) ($row['created_at'] ?? 'now')))); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-medium text-slate-900"><?php echo e((string) ($row['cashier'] ?? '-')); ?></div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        <?php if (!empty($row['meta']['customer']['name'])): ?>
                                            Member: <?php echo e((string) ($row['meta']['customer']['name'] ?? '-')); ?>
                                        <?php else: ?>
                                            Tanpa member
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-neutral"><?php echo e(strtoupper((string) ($row['method'] ?? '-'))); ?></span>
                                </td>
                                <td class="text-right font-semibold text-slate-900"><?php echo e(format_rupiah((float) ($row['total_sales'] ?? $row['total_amount'] ?? 0))); ?></td>
                                <td class="text-right text-slate-600"><?php echo e(format_rupiah((float) ($row['total_cost'] ?? 0))); ?></td>
                                <td class="text-right font-semibold <?php echo ((float) ($row['total_profit'] ?? 0) >= 0) ? 'text-emerald-700' : 'text-red-600'; ?>">
                                    <?php echo e(format_rupiah((float) ($row['total_profit'] ?? 0))); ?>
                                </td>
                                <td>
                                    <div class="text-xs text-slate-500 mb-2"><?php echo e((string) ((int) ($row['item_count'] ?? 0))); ?> item</div>
                                    <?php if (!empty($row['items'])): ?>
                                        <div class="space-y-1 text-sm text-slate-700">
                                            <?php foreach (array_slice($row['items'], 0, 2) as $item): ?>
                                                <div><?php echo e((string) ($item['name'] ?? '-')); ?> <span class="text-slate-400">x<?php echo e((string) ($item['qty'] ?? 0)); ?></span></div>
                                            <?php endforeach; ?>
                                            <?php if (count($row['items']) > 2): ?>
                                                <div class="text-xs text-slate-400">+<?php echo e((string) (count($row['items']) - 2)); ?> item lain</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-10 text-slate-500">Tidak ada transaksi yang cocok dengan filter saat ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
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
