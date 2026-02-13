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
        <h2 class="kp-page-title"><?php echo e(__('refund.page_title')); ?></h2>
        <p class="kp-page-subtitle"><?php echo e(__('refund.subtitle')); ?></p>
    </div>
</div>

<div class="kp-card-flat p-4 mb-4">
    <div class="fw-semibold mb-2"><?php echo e(__('refund.input_title')); ?></div>
    <form method="POST" class="kp-filter-form">
        <?php echo csrf_field(); ?>
        <div>
            <label class="form-label"><?php echo e(__('refund.transaction_id')); ?></label>
            <input type="number" name="transaction_id" class="form-control" required>
        </div>
        <div>
            <label class="form-label"><?php echo e(__('refund.amount')); ?></label>
            <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
        </div>
        <div>
            <label class="form-label"><?php echo e(__('refund.method')); ?></label>
            <select name="method" class="form-select">
                <option value="cash">Cash</option>
                <option value="qris">QRIS</option>
            </select>
        </div>
        <div>
            <label class="form-label"><?php echo e(__('refund.reason')); ?></label>
            <input type="text" name="reason" class="form-control" placeholder="<?php echo e(__('refund.reason_placeholder')); ?>" required>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="restock" id="restockCheck">
            <label class="form-check-label" for="restockCheck"><?php echo e(__('refund.restock')); ?></label>
        </div>
        <div>
            <button class="btn kp-btn-primary">
                <span class="material-icons-outlined">undo</span>
                <?php echo e(__('common.save_refund')); ?>
            </button>
        </div>
    </form>
</div>

<div class="kp-card-flat p-4 mb-3">
    <div class="fw-semibold mb-2"><?php echo e(__('refund.filter_title')); ?></div>
    <form method="GET" class="kp-filter-form">
        <div>
            <label class="form-label"><?php echo e(__('refund.from')); ?></label>
            <input type="date" name="start_date" class="form-control" value="<?php echo e($filters['start_date']); ?>">
        </div>
        <div>
            <label class="form-label"><?php echo e(__('refund.to')); ?></label>
            <input type="date" name="end_date" class="form-control" value="<?php echo e($filters['end_date']); ?>">
        </div>
        <div>
            <label class="form-label"><?php echo e(__('refund.method')); ?></label>
            <select name="method" class="form-select">
                <option value="all" <?php echo ($filters['method'] === 'all') ? 'selected' : ''; ?>><?php echo e(__('common.all')); ?></option>
                <option value="cash" <?php echo ($filters['method'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                <option value="qris" <?php echo ($filters['method'] === 'qris') ? 'selected' : ''; ?>>QRIS</option>
            </select>
        </div>
        <div>
            <button class="btn kp-btn-primary">
                <span class="material-icons-outlined">filter_alt</span>
                <?php echo e(__('common.filter')); ?>
            </button>
        </div>
    </form>
</div>

<div class="kp-card-flat p-4">
    <div class="fw-semibold mb-2"><?php echo e(__('refund.list_title')); ?></div>
    <?php if (!empty($refunds)): ?>
        <div class="table-responsive">
            <table class="table">
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
                            <td><?php echo e($refund['id'] ?? '-'); ?></td>
                            <td>#<?php echo e((string) ($refund['transaction_id'] ?? '-')); ?></td>
                            <td><?php echo e(format_rupiah((float) ($refund['amount'] ?? 0))); ?></td>
                            <td><?php echo e(strtoupper((string) ($refund['method'] ?? '-'))); ?></td>
                            <td><?php echo e($refund['reason'] ?? '-'); ?></td>
                            <td><?php echo !empty($refund['restock']) ? e(__('common.yes')) : e(__('common.no')); ?></td>
                            <td><?php echo e($refund['created_at'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="kp-muted"><?php echo e(__('refund.empty')); ?></div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
