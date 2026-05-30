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

<div class="card mb-8 border-0 shadow-none bg-transparent">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="card-title text-2xl mb-1"><?php echo e(__('refund.page_title')); ?></h2>
            <p class="text-sm text-muted mb-0"><?php echo e(__('refund.subtitle')); ?></p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <div class="xl:col-span-1">
        <div class="card p-6 h-full">
            <h3 class="text-lg font-semibold text-slate-900 mb-4"><?php echo e(__('refund.input_title')); ?></h3>
            <?php if (!$isAdmin): ?>
                <div class="alert alert-info">Mode lihat saja. Hanya admin yang dapat mencatat refund.</div>
            <?php else: ?>
            <form method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>
                <div class="form-group mb-0">
                    <label class="form-label"><?php echo e(__('refund.transaction_id')); ?></label>
                    <input type="number" name="transaction_id" class="form-input w-full" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label"><?php echo e(__('refund.amount')); ?></label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-input w-full" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label"><?php echo e(__('refund.method')); ?></label>
                    <select name="method" class="form-input w-full">
                        <option value="cash">Cash</option>
                        <option value="qris">QRIS</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label"><?php echo e(__('refund.reason')); ?></label>
                    <input type="text" name="reason" class="form-input w-full" placeholder="<?php echo e(__('refund.reason_placeholder')); ?>" required>
                </div>
                <div class="form-group mb-0 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input class="form-check-input mt-0" type="checkbox" name="restock" id="restockCheck">
                        <span class="text-sm font-medium text-slate-700"><?php echo e(__('refund.restock')); ?></span>
                    </label>
                </div>
                <div class="pt-4 border-t border-border mt-6">
                    <button class="btn btn-primary w-full">
                        <i data-lucide="undo-2" class="w-4 h-4"></i>
                        <?php echo e(__('common.save_refund')); ?>
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="xl:col-span-2 space-y-6">
        <div class="card p-4">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[150px] form-group mb-0">
                    <label class="form-label mb-1"><?php echo e(__('refund.from')); ?></label>
                    <input type="date" name="start_date" class="form-input w-full" value="<?php echo e($filters['start_date']); ?>">
                </div>
                <div class="flex-1 min-w-[150px] form-group mb-0">
                    <label class="form-label mb-1"><?php echo e(__('refund.to')); ?></label>
                    <input type="date" name="end_date" class="form-input w-full" value="<?php echo e($filters['end_date']); ?>">
                </div>
                <div class="flex-1 min-w-[150px] form-group mb-0">
                    <label class="form-label mb-1"><?php echo e(__('refund.method')); ?></label>
                    <select name="method" class="form-input w-full">
                        <option value="all" <?php echo ($filters['method'] === 'all') ? 'selected' : ''; ?>><?php echo e(__('common.all')); ?></option>
                        <option value="cash" <?php echo ($filters['method'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="qris" <?php echo ($filters['method'] === 'qris') ? 'selected' : ''; ?>>QRIS</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-secondary">
                        <i data-lucide="filter" class="w-4 h-4"></i>
                        <?php echo e(__('common.filter')); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="p-4 border-b border-border">
                <h3 class="text-lg font-semibold text-slate-900"><?php echo e(__('refund.list_title')); ?></h3>
            </div>
            <div class="p-0">
                <?php if (!empty($refunds)): ?>
                    <div class="table-wrapper border-0 rounded-none">
                        <table class="data-table w-full">
                            <thead>
                                <tr>
                                    <th><?php echo e(__('refund.col_id')); ?></th>
                                    <th><?php echo e(__('refund.col_transaction')); ?></th>
                                    <th><?php echo e(__('refund.col_amount')); ?></th>
                                    <th><?php echo e(__('refund.col_method')); ?></th>
                                    <th><?php echo e(__('refund.col_reason')); ?></th>
                                    <th><?php echo e(__('refund.col_restock')); ?></th>
                                    <th><?php echo e(__('refund.col_date')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($refunds as $refund): ?>
                                    <tr>
                                        <td class="font-mono text-xs text-muted"><?php echo e($refund['id'] ?? '-'); ?></td>
                                        <td class="font-semibold text-slate-900">#<?php echo e((string) ($refund['transaction_id'] ?? '-')); ?></td>
                                        <td class="font-semibold text-red-600">-<?php echo e(format_rupiah((float) ($refund['amount'] ?? 0))); ?></td>
                                        <td><span class="badge badge-neutral"><?php echo e(strtoupper((string) ($refund['method'] ?? '-'))); ?></span></td>
                                        <td class="max-w-[200px] truncate" title="<?php echo e($refund['reason'] ?? '-'); ?>"><?php echo e($refund['reason'] ?? '-'); ?></td>
                                        <td>
                                            <?php if (!empty($refund['restock'])): ?>
                                                <span class="badge badge-success"><?php echo e(__('common.yes')); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-neutral"><?php echo e(__('common.no')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="whitespace-nowrap text-xs text-muted"><?php echo e($refund['created_at'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-muted"><?php echo e(__('refund.empty')); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
