<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';
?>

<div class="card mb-8 border-0 shadow-none bg-transparent">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="card-title text-2xl mb-1"><?php echo e((string) ($pageHeading ?? 'Audit Log')); ?></h2>
            <p class="text-sm text-muted mb-0"><?php echo e((string) ($pageSubtitle ?? 'Riwayat perubahan kas, shift, dan aktivitas sistem.')); ?></p>
        </div>
    </div>
</div>

<div class="kpi-grid mb-6">
    <div class="card p-6">
        <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Total Entri</div>
        <div class="text-3xl font-bold text-slate-900 leading-none mb-2"><?php echo e((string) ($auditSummary['total'] ?? 0)); ?></div>
        <div class="text-xs text-muted">Jumlah log pada filter yang sedang aktif.</div>
    </div>
    <div class="card p-6">
        <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Hari Ini</div>
        <div class="text-3xl font-bold text-slate-900 leading-none mb-2"><?php echo e((string) ($auditSummary['today'] ?? 0)); ?></div>
        <div class="text-xs text-muted">Aktivitas audit yang tercatat hari ini.</div>
    </div>
    <div class="card p-6">
        <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Jenis Aksi</div>
        <div class="text-3xl font-bold text-slate-900 leading-none mb-2"><?php echo e((string) ($auditSummary['unique_actions'] ?? 0)); ?></div>
        <div class="text-xs text-muted">Variasi action yang muncul di filter ini.</div>
    </div>
    <div class="card p-6">
        <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">User Tercatat</div>
        <div class="text-3xl font-bold text-slate-900 leading-none mb-2"><?php echo e((string) ($auditSummary['unique_users'] ?? 0)); ?></div>
        <div class="text-xs text-muted">Jumlah user unik yang masuk ke log.</div>
    </div>
</div>

<div class="card mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[150px] form-group mb-0">
            <label class="form-label mb-1">Dari</label>
            <input type="date" name="start_date" class="form-input w-full" value="<?php echo e($filters['start_date'] ?? ''); ?>">
        </div>
        <div class="flex-1 min-w-[150px] form-group mb-0">
            <label class="form-label mb-1">Sampai</label>
            <input type="date" name="end_date" class="form-input w-full" value="<?php echo e($filters['end_date'] ?? ''); ?>">
        </div>
        <div class="flex-1 min-w-[150px] form-group mb-0">
            <label class="form-label mb-1">Aksi</label>
            <select name="action" class="form-input w-full">
                <option value="">Semua</option>
                <?php foreach ($actionList as $action): ?>
                    <option value="<?php echo e($action); ?>" <?php echo ($filters['action'] ?? '') === $action ? 'selected' : ''; ?>>
                        <?php echo e($action); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-[200px] form-group mb-0">
            <label class="form-label mb-1">Kata Kunci</label>
            <input type="text" name="keyword" class="form-input w-full" placeholder="Cari user, shift, nominal..." value="<?php echo e((string) ($filters['keyword'] ?? '')); ?>">
        </div>
        <div>
            <button class="btn btn-primary" type="submit">
                <i data-lucide="filter" class="w-4 h-4"></i> Terapkan
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-4 border-b border-border">
        <div class="font-semibold text-lg text-slate-900">Log Terbaru</div>
        <div class="text-sm text-muted">Menampilkan <?php echo e((string) count($filtered)); ?> entri</div>
    </div>
    <div class="p-0">
        <?php if (!empty($filtered)): ?>
            <div class="table-wrapper border-0 rounded-none">
                <table class="data-table w-full">
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
                                <td class="whitespace-nowrap"><?php echo e($entry['time'] ?? '-'); ?></td>
                                <td class="font-semibold text-slate-900"><?php echo e($entry['message'] ?? '-'); ?></td>
                                <td><?php echo e($entry['username'] ?? '-'); ?></td>
                                <td><span class="badge badge-neutral"><?php echo e($entry['role'] ?? '-'); ?></span></td>
                                <td class="max-w-[300px] truncate"><span class="text-xs text-muted"><?php echo e($detailText); ?></span></td>
                                <td class="text-xs text-muted font-mono"><?php echo e($entry['ip'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-6 text-center text-muted">Belum ada log audit pada filter ini.</div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
