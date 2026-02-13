<?php
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="kp-page-header align-items-center">
    <div class="text-center text-md-start w-100">
        <h2 class="kp-page-title">Audit Log</h2>
        <p class="kp-page-subtitle">Riwayat perubahan kas, shift, dan aktivitas sistem.</p>
    </div>
</div>

<div class="kp-card-flat p-4 mb-4">
    <form method="GET" class="kp-filter-form">
        <div>
            <label class="form-label">Dari</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo e($filters['start_date'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label">Sampai</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo e($filters['end_date'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label">Aksi</label>
            <select name="action" class="form-select">
                <option value="">Semua</option>
                <?php foreach ($actionList as $action): ?>
                    <option value="<?php echo e($action); ?>" <?php echo ($filters['action'] ?? '') === $action ? 'selected' : ''; ?>>
                        <?php echo e($action); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Kata Kunci</label>
            <input type="text" name="keyword" class="form-control" placeholder="Cari user, shift, nominal..." value="<?php echo e((string) ($filters['keyword'] ?? '')); ?>">
        </div>
        <div>
            <button class="btn kp-btn-primary" type="submit">
                <span class="material-icons-outlined">filter_alt</span>
                Terapkan
            </button>
        </div>
    </form>
</div>

<div class="kp-card-flat p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2">
        <div class="fw-semibold">Log Terbaru</div>
        <div class="kp-muted small">Menampilkan <?php echo e((string) count($filtered)); ?> entri</div>
    </div>
    <?php if (!empty($filtered)): ?>
        <div class="table-responsive">
            <table class="table text-center align-middle">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Aksi</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Detail</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered as $entry): ?>
                        <?php
                            $details = $entry;
                            unset($details['time'], $details['message'], $details['user_id'], $details['role'], $details['username'], $details['ip']);
                            $detailText = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            if ($detailText === false || $detailText === '[]') {
                                $detailText = '-';
                            }
                        ?>
                        <tr>
                            <td><?php echo e($entry['time'] ?? '-'); ?></td>
                            <td class="fw-semibold"><?php echo e($entry['message'] ?? '-'); ?></td>
                            <td><?php echo e($entry['username'] ?? '-'); ?></td>
                            <td><?php echo e($entry['role'] ?? '-'); ?></td>
                            <td class="text-start"><span class="kp-muted small"><?php echo e($detailText); ?></span></td>
                            <td><?php echo e($entry['ip'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="kp-muted text-center text-md-start">Belum ada log audit pada filter ini.</div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
