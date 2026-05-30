<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/ai_assistant_helper.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';

require_role(['superadmin', 'admin', 'bos']);

$pdo = db();
$user = current_user() ?? [];
$today = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime($today . ' -6 days'));
$filters = [
    'start_date' => ai_assistant_normalize_date($_POST['start_date'] ?? $_GET['start_date'] ?? null, $defaultStart),
    'end_date' => ai_assistant_normalize_date($_POST['end_date'] ?? $_GET['end_date'] ?? null, $today),
];
if ($filters['start_date'] > $filters['end_date']) {
    [$filters['start_date'], $filters['end_date']] = [$filters['end_date'], $filters['start_date']];
}

$status = ai_assistant_status();
$question = trim((string) ($_POST['question'] ?? 'Buat ringkasan performa toko dan rekomendasi aksi untuk periode ini.'));
$answer = '';
$resultMeta = [];
$errors = [];
$success = '';

try {
    $snapshot = ai_assistant_business_snapshot($pdo, $user, $filters['start_date'], $filters['end_date']);
} catch (Throwable $e) {
    $snapshot = [
        'period' => $filters,
        'scope' => ['type' => 'unknown', 'store_id' => null, 'store_name' => '-'],
        'metrics' => [],
        'top_products' => [],
        'daily_sales' => [],
        'low_stock_items' => [],
    ];
    $errors[] = 'Snapshot operasional belum bisa dibaca: ' . $e->getMessage();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif (!$status['ready']) {
        $errors[] = 'AI Assistant belum siap: ' . implode(', ', $status['reasons']);
    } else {
        try {
            $result = ai_assistant_answer($pdo, $question, $user, $filters['start_date'], $filters['end_date']);
            $answer = (string) $result['answer'];
            $resultMeta = $result;
            $snapshot = $result['snapshot'];
            $success = 'Insight AI berhasil dibuat.';
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$metrics = $snapshot['metrics'] ?? [];
$title = 'AI Assistant';
require_once __DIR__ . '/../app/views/layouts/header.php';
?>

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title">AI Assistant</h2>
        <p class="kp-page-subtitle">Insight operasional berbasis OpenAI Responses API dan siap dipakai untuk workflow Codex/project AI.</p>
    </div>
    <span class="badge <?php echo $status['ready'] ? 'badge-success' : 'badge-warning'; ?>">
        <?php echo $status['ready'] ? 'AI Ready' : 'Setup Required'; ?>
    </span>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-4">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success mb-4"><?php echo e($success); ?></div>
<?php endif; ?>

<div class="kp-grid kp-grid-4 mb-4">
    <div class="kp-card-flat p-4">
        <div class="kp-muted small">Transaksi</div>
        <div class="kp-kpi-value"><?php echo (int) ($metrics['transactions'] ?? 0); ?></div>
    </div>
    <div class="kp-card-flat p-4">
        <div class="kp-muted small">Net Sales</div>
        <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($metrics['net_sales'] ?? 0))); ?></div>
    </div>
    <div class="kp-card-flat p-4">
        <div class="kp-muted small">Average Ticket</div>
        <div class="kp-kpi-value"><?php echo e(format_rupiah((float) ($metrics['average_ticket'] ?? 0))); ?></div>
    </div>
    <div class="kp-card-flat p-4">
        <div class="kp-muted small">Stok Menipis</div>
        <div class="kp-kpi-value"><?php echo (int) ($metrics['low_stock_count'] ?? 0); ?></div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <section class="card p-6 xl:col-span-2">
        <form method="POST" class="grid gap-4">
            <?php echo csrf_field(); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group mb-0">
                    <label class="form-label">Dari tanggal</label>
                    <input type="date" name="start_date" class="form-input" value="<?php echo e($filters['start_date']); ?>">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Sampai tanggal</label>
                    <input type="date" name="end_date" class="form-input" value="<?php echo e($filters['end_date']); ?>">
                </div>
            </div>

            <div class="form-group mb-0">
                <label class="form-label">Pertanyaan / instruksi AI</label>
                <textarea name="question" class="form-input min-h-[150px]" maxlength="2000"><?php echo e($question); ?></textarea>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn kp-btn-primary" <?php echo $status['ready'] ? '' : 'disabled'; ?>>
                    <span class="material-icons-outlined">auto_awesome</span>
                    Generate Insight
                </button>
                <a class="btn kp-btn-ghost" href="<?php echo e(base_url('admin_ai_assistant.php')); ?>">
                    <span class="material-icons-outlined">refresh</span>
                    Reset
                </a>
            </div>
        </form>

        <?php if ($answer !== ''): ?>
            <div class="mt-6 rounded-2xl border border-emerald-100 bg-emerald-50/40 p-5">
                <div class="flex flex-wrap justify-between gap-2 mb-3">
                    <div class="font-bold text-slate-900">Jawaban AI</div>
                    <div class="text-xs text-muted">
                        Model: <?php echo e((string) ($resultMeta['model'] ?? $status['model'])); ?>
                        <?php if (!empty($resultMeta['request_id'])): ?>
                            | Request: <?php echo e((string) $resultMeta['request_id']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="prose max-w-none text-sm leading-relaxed whitespace-pre-line"><?php echo e($answer); ?></div>
            </div>
        <?php endif; ?>
    </section>

    <aside class="card p-6">
        <h3 class="text-lg font-bold text-slate-900 mb-2">Status Integrasi</h3>
        <div class="space-y-3 text-sm">
            <div>
                <div class="kp-muted small">Provider</div>
                <div class="font-semibold"><?php echo e((string) $status['provider']); ?></div>
            </div>
            <div>
                <div class="kp-muted small">Model</div>
                <div class="font-semibold"><?php echo e((string) $status['model']); ?></div>
            </div>
            <div>
                <div class="kp-muted small">Scope data</div>
                <div class="font-semibold"><?php echo e((string) (($snapshot['scope']['store_name'] ?? '-') ?: '-')); ?></div>
            </div>
            <?php if (!$status['ready']): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-800">
                    <?php foreach ($status['reasons'] as $reason): ?>
                        <div><?php echo e((string) $reason); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <hr class="my-5">

        <h3 class="text-lg font-bold text-slate-900 mb-2">Top Produk</h3>
        <div class="space-y-2">
            <?php foreach (($snapshot['top_products'] ?? []) as $product): ?>
                <div class="flex justify-between gap-3 text-sm">
                    <span><?php echo e((string) ($product['name'] ?? '-')); ?></span>
                    <span class="font-bold"><?php echo (int) ($product['qty'] ?? 0); ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($snapshot['top_products'])): ?>
                <div class="text-sm text-muted">Belum ada data produk pada periode ini.</div>
            <?php endif; ?>
        </div>

        <hr class="my-5">

        <h3 class="text-lg font-bold text-slate-900 mb-2">Stok Perlu Dicek</h3>
        <div class="space-y-2">
            <?php foreach (($snapshot['low_stock_items'] ?? []) as $item): ?>
                <div class="rounded-xl border border-slate-200 p-3 text-sm">
                    <div class="font-semibold"><?php echo e((string) ($item['name'] ?? '-')); ?></div>
                    <div class="text-muted">Stok <?php echo (int) ($item['stock'] ?? 0); ?> / min <?php echo (int) ($item['min_stock'] ?? 0); ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($snapshot['low_stock_items'])): ?>
                <div class="text-sm text-muted">Tidak ada stok kritis pada snapshot ini.</div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php
require_once __DIR__ . '/../app/views/layouts/footer.php';
?>
