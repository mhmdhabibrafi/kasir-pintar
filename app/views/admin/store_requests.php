<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$navItems = [
    'overview' => 'Ringkasan',
    'referrals' => 'Referral',
    'requests' => 'Approval',
    'stores' => 'Toko Aktif',
    'maintenance' => 'Maintenance',
];
$supportEnabled = function_exists('app_env_bool') ? app_env_bool('APP_SUPPORT_ENABLED', false) : false;
$navIcons = [
    'overview' => 'layout-dashboard',
    'referrals' => 'key-round',
    'requests' => 'clipboard-check',
    'stores' => 'store',
    'maintenance' => 'wrench',
];
$navDescriptions = [
    'overview' => 'Status sistem',
    'referrals' => 'Kode undangan',
    'requests' => 'Review mitra',
    'stores' => 'Tenant live',
    'maintenance' => 'Kontrol akses',
];

$statusBadge = static function (string $status): string {
    if ($status === 'approved') {
        return 'badge-success';
    }
    if ($status === 'rejected') {
        return 'badge-danger';
    }
    return 'badge-warning';
};

$healthBadge = static function (string $healthLevel): string {
    if ($healthLevel === 'suspended') {
        return 'badge-danger';
    }
    if ($healthLevel === 'critical') {
        return 'badge-danger';
    }
    if ($healthLevel === 'attention') {
        return 'badge-warning';
    }
    return 'badge-success';
};
?>

<style>
    .sa-hero {
        border: 1px solid rgba(16, 185, 129, 0.18);
        border-radius: 28px;
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 46%, #ecfeff 100%);
        box-shadow: 0 28px 80px -58px rgba(15, 23, 42, 0.38);
        padding: 26px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 22px;
        align-items: center;
    }
    .sa-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(16, 185, 129, 0.10);
        color: #047857;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 12px;
    }
    .sa-hero-title {
        font-size: clamp(28px, 3vw, 42px);
        line-height: 1.05;
        font-weight: 900;
        color: #0f172a;
        margin: 0 0 10px;
        letter-spacing: 0;
    }
    .sa-hero-copy {
        color: #64748b;
        max-width: 680px;
        margin: 0;
        line-height: 1.6;
    }
    .sa-hero-actions {
        min-width: 280px;
        display: grid;
        gap: 12px;
    }
    .sa-stat-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }
    .sa-stat-pill {
        min-height: 72px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.72);
        padding: 12px;
        display: grid;
        gap: 4px;
    }
    .sa-stat-pill .sa-stat-label {
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .sa-stat-pill .sa-stat-value {
        color: #0f172a;
        font-size: 22px;
        font-weight: 900;
        line-height: 1;
    }
    .sa-tabs {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 22px;
    }
    .sa-tab {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 76px;
        padding: 14px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.78);
        color: #475569;
        text-decoration: none;
        transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
    }
    .sa-tab:hover {
        transform: translateY(-1px);
        color: #0f172a;
        border-color: rgba(16, 185, 129, 0.22);
        background: #ffffff;
    }
    .sa-tab.active {
        color: #047857;
        border-color: rgba(16, 185, 129, 0.35);
        background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 100%);
        box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.10);
    }
    .sa-tab-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        background: #f1f5f9;
        color: #64748b;
    }
    .sa-tab.active .sa-tab-icon {
        background: #10b981;
        color: #fff;
    }
    .sa-tab-title {
        display: block;
        font-size: 13px;
        font-weight: 850;
        line-height: 1.25;
        color: inherit;
    }
    .sa-tab-sub {
        display: block;
        margin-top: 2px;
        font-size: 11px;
        color: #94a3b8;
        line-height: 1.25;
    }
    .sa-panel {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 24px;
        box-shadow: 0 24px 70px -58px rgba(15, 23, 42, 0.36);
    }
    .sa-panel-head {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 24px;
    }
    .sa-panel-icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #047857;
        background: #ecfdf5;
        border: 1px solid rgba(16, 185, 129, 0.16);
    }
    .sa-maint-toggle {
        border: 1px solid rgba(15, 23, 42, 0.10);
        border-radius: 20px;
        background: #f8fafc;
        padding: 18px;
    }
    .sa-status-card {
        border-radius: 24px;
        border: 1px solid rgba(16, 185, 129, 0.18);
        box-shadow: 0 20px 64px -56px rgba(15, 23, 42, 0.35);
    }
    .sa-status-card.maintenance {
        border-color: rgba(249, 115, 22, 0.22);
    }
    .sa-referral-codebar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px;
        border-radius: 18px;
        background: #0f172a;
        color: #ffffff;
        box-shadow: 0 20px 42px -32px rgba(15, 23, 42, 0.78);
    }
    .sa-referral-code {
        min-width: 0;
        font-family: "IBM Plex Mono", "Courier New", monospace;
        font-size: 0.96rem;
        font-weight: 900;
        letter-spacing: 0;
        overflow-wrap: anywhere;
    }
    .sa-referral-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .sa-referral-mini-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 34px;
        padding: 7px 10px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        line-height: 1;
    }
    .sa-referral-mini-btn:hover {
        background: rgba(255, 255, 255, 0.16);
    }
    .sa-referral-progress {
        height: 9px;
        overflow: hidden;
        border-radius: 999px;
        background: #e2e8f0;
    }
    .sa-referral-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #10b981, #06b6d4);
    }
    .sa-referral-shell {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 24px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 24px 70px -58px rgba(15, 23, 42, 0.36);
    }
    .sa-referral-locked {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        border-radius: 16px;
        padding: 12px;
        background: #fff7ed;
        color: #9a3412;
        font-size: 12px;
        line-height: 1.45;
    }
    @media (max-width: 991.98px) {
        .sa-hero {
            grid-template-columns: 1fr;
        }
        .sa-hero-actions {
            min-width: 0;
        }
        .sa-tabs {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 575.98px) {
        .sa-hero {
            padding: 20px;
            border-radius: 22px;
        }
        .sa-stat-strip,
        .sa-tabs {
            grid-template-columns: 1fr;
        }
        .sa-referral-codebar {
            align-items: stretch;
            flex-direction: column;
        }
        .sa-referral-actions {
            width: 100%;
        }
        .sa-referral-mini-btn {
            flex: 1 1 0;
            justify-content: center;
        }
    }
</style>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-6">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success mb-6"><?php echo e($success); ?></div>
<?php endif; ?>

<div class="space-y-6">
    <section class="sa-hero">
        <div>
            <div class="sa-eyebrow">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                Superadmin Command Center
            </div>
            <h2 class="sa-hero-title">Panel Super Admin</h2>
            <p class="sa-hero-copy">Kelola onboarding mitra, referral resmi, lifecycle toko, custom domain, maintenance, audit, dan kesehatan sistem KASPINDO dari satu pusat kontrol.</p>
        </div>
        <div class="sa-hero-actions">
            <div class="sa-stat-strip">
                <div class="sa-stat-pill">
                    <span class="sa-stat-label">Pending</span>
                    <span class="sa-stat-value"><?php echo e((string) ($counts['pending'] ?? 0)); ?></span>
                </div>
                <div class="sa-stat-pill">
                    <span class="sa-stat-label">Aktif</span>
                    <span class="sa-stat-value text-emerald-700"><?php echo e((string) ($dashboardStats['active_stores'] ?? 0)); ?></span>
                </div>
                <div class="sa-stat-pill">
                    <span class="sa-stat-label">Suspend</span>
                    <span class="sa-stat-value text-red-700"><?php echo e((string) ($dashboardStats['suspended_stores'] ?? 0)); ?></span>
                </div>
            </div>
        </div>
    </section>

    <nav class="sa-tabs" aria-label="Navigasi panel super admin">
        <?php foreach ($navItems as $key => $label): ?>
            <?php $activeTab = $focusSection === $key; ?>
            <a class="sa-tab <?php echo $activeTab ? 'active' : ''; ?>" href="<?php echo e(base_url('superadmin_store_requests.php?focus=' . $key)); ?>">
                <span class="sa-tab-icon">
                    <i data-lucide="<?php echo e($navIcons[$key] ?? 'circle'); ?>" class="w-5 h-5"></i>
                </span>
                <span>
                    <span class="sa-tab-title"><?php echo e($label); ?></span>
                    <span class="sa-tab-sub"><?php echo e($navDescriptions[$key] ?? ''); ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($focusSection === 'overview'): ?>
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
            <div class="xl:col-span-8 space-y-6">
                <div class="card p-6 border-t-4 border-t-primary">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold mb-4">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Ringkasan Operasional
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-2">Kontrol super admin yang lebih rapi dan mudah dipantau.</h3>
                        <p class="text-slate-600 mb-6">Kelola onboarding toko, referral resmi, dan review harian lewat section yang terpisah, tanpa blok hero besar yang terlalu berat dilihat.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="card p-4 bg-slate-50 border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-emerald-100 text-emerald-700">
                                    <i data-lucide="store" class="w-5 h-5"></i>
                                </div>
                                <div class="text-xs font-bold text-muted uppercase tracking-wide">Toko Aktif</div>
                            </div>
                            <div class="text-3xl font-bold text-slate-900 leading-none mb-2"><?php echo e((string) ($dashboardStats['active_stores'] ?? 0)); ?></div>
                            <div class="text-xs text-muted">Toko yang sudah live dan berjalan.</div>
                        </div>
                        <div class="card p-4 bg-slate-50 border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-emerald-100 text-emerald-700">
                                    <i data-lucide="calendar" class="w-5 h-5"></i>
                                </div>
                                <div class="text-xs font-bold text-muted uppercase tracking-wide">Pending Hari Ini</div>
                            </div>
                            <div class="text-3xl font-bold text-slate-900 leading-none mb-2"><?php echo e((string) ($dashboardStats['pending_today'] ?? 0)); ?></div>
                            <div class="text-xs text-muted">Pengajuan baru yang masuk hari ini.</div>
                        </div>
                        <div class="card p-4 bg-slate-50 border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-emerald-100 text-emerald-700">
                                    <i data-lucide="key" class="w-5 h-5"></i>
                                </div>
                                <div class="text-xs font-bold text-muted uppercase tracking-wide">Referral Aktif</div>
                            </div>
                            <div class="text-3xl font-bold text-slate-900 leading-none mb-2"><?php echo e((string) ($referralStats['active'] ?? 0)); ?></div>
                            <div class="text-xs text-muted">Kode referral yang masih bisa dipakai.</div>
                        </div>
                        <div class="card p-4 bg-slate-50 border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-amber-100 text-amber-700">
                                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                                </div>
                                <div class="text-xs font-bold text-muted uppercase tracking-wide">Terlambat Review</div>
                            </div>
                            <div class="text-3xl font-bold text-slate-900 leading-none mb-2"><?php echo e((string) ($dashboardStats['pending_overdue'] ?? 0)); ?></div>
                            <div class="text-xs text-muted">Pending yang perlu segera ditindak.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-4 space-y-6">
                <div class="card p-6 h-full">
                    <div class="font-bold text-lg text-slate-900 mb-1">Aktivitas Terakhir</div>
                    <p class="text-sm text-muted mb-4">Update onboarding terbaru yang paling penting untuk dipantau.</p>

                    <div class="space-y-4">
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-emerald-100 text-emerald-700">
                                    <i data-lucide="clock" class="w-5 h-5"></i>
                                </div>
                                <div class="text-xs font-bold text-muted uppercase tracking-wide">Pengajuan Terakhir</div>
                            </div>
                            <p class="font-bold text-slate-900 mb-1"><?php echo e((string) ($dashboardStats['latest_submitted_store'] ?? 'Belum ada')); ?></p>
                            <div class="text-xs text-muted"><?php echo e($formatDateTime($dashboardStats['latest_submitted_at'] ?? null)); ?></div>
                        </div>

                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-emerald-100 text-emerald-700">
                                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                                </div>
                                <div class="text-xs font-bold text-muted uppercase tracking-wide">Approval Terakhir</div>
                            </div>
                            <p class="font-bold text-slate-900 mb-1"><?php echo e((string) ($dashboardStats['latest_approved_store'] ?? 'Belum ada')); ?></p>
                            <div class="text-xs text-muted"><?php echo e($formatDateTime($dashboardStats['latest_approved_at'] ?? null)); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($focusSection === 'referrals'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card p-6 lg:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 m-0">Generate Referral</h3>
                        <p class="text-xs text-muted m-0">Buat kode referral resmi untuk calon toko.</p>
                    </div>
                </div>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="generate_referral">
                    <div class="form-group mb-0 md:col-span-2">
                        <label class="form-label">Nama Batch</label>
                        <input type="text" class="form-input w-full" name="label" placeholder="Contoh: Mitra Jambi Batch 1" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Kuota</label>
                        <input type="number" class="form-input w-full" name="max_uses" min="1" value="1" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Kedaluwarsa</label>
                        <input type="datetime-local" class="form-input w-full" name="expires_at">
                    </div>
                    <div class="form-group mb-0 md:col-span-2">
                        <label class="form-label">Catatan</label>
                        <input type="text" class="form-input w-full" name="note" placeholder="Opsional">
                    </div>
                    <div class="form-group mb-0 md:col-span-2 mt-2">
                        <button class="btn btn-primary w-full justify-center" type="submit">
                            <i data-lucide="key" class="w-4 h-4"></i>
                            Generate Kode
                        </button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <div class="card p-5 border-l-4 border-l-emerald-500 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <i data-lucide="key" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase tracking-wide">Referral Aktif</div>
                        <div class="text-2xl font-bold text-slate-900 leading-none mt-1"><?php echo e((string) ($referralStats['active'] ?? 0)); ?></div>
                    </div>
                </div>
                <div class="card p-5 border-l-4 border-l-amber-500 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                        <i data-lucide="clock" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase tracking-wide">Habis / Expired</div>
                        <div class="text-2xl font-bold text-slate-900 leading-none mt-1"><?php echo e((string) (((int) ($referralStats['exhausted'] ?? 0)) + ((int) ($referralStats['expired'] ?? 0)))); ?></div>
                    </div>
                </div>
                <div class="card p-5 border-l-4 border-l-blue-500 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase tracking-wide">Slot Terpakai</div>
                        <div class="text-2xl font-bold text-slate-900 leading-none mt-1"><?php echo e((string) ($referralStats['used_slots'] ?? 0)); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-6 mb-6">
            <form method="GET" class="flex flex-col md:flex-row items-end gap-4">
                <input type="hidden" name="focus" value="referrals">
                <div class="form-group mb-0 flex-1">
                    <label class="form-label text-xs">Cari Referral</label>
                    <input type="text" name="code_q" class="form-input w-full" value="<?php echo e($referralSearch); ?>" placeholder="Cari kode, label, atau catatan">
                </div>
                <button class="btn btn-primary w-full md:w-auto" type="submit">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    Tampilkan
                </button>
            </form>
        </div>

        <?php if (empty($referrals)): ?>
            <div class="card p-10 text-center">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="key" class="w-8 h-8"></i>
                </div>
                <div class="font-bold text-slate-900 mb-1">Belum ada referral code</div>
                <div class="text-sm text-muted">Generate kode pertama untuk membuka akses pendaftaran resmi.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <?php foreach ($referrals as $referral): ?>
                    <?php
                        $refStatus = (string) ($referral['availability_status'] ?? 'inactive');
                        $referralCode = (string) ($referral['code'] ?? '-');
                        $referralLink = base_url('daftar-mitra?ref=' . rawurlencode($referralCode));
                        $referralExpiresInput = '';
                        $referralExpiresRaw = trim((string) ($referral['expires_at'] ?? ''));
                        if ($referralExpiresRaw !== '' && strtotime($referralExpiresRaw) !== false) {
                            $referralExpiresInput = date('Y-m-d\TH:i', strtotime($referralExpiresRaw));
                        }
                        $usedCount = (int) ($referral['used_count'] ?? 0);
                        $maxUses = max(1, (int) ($referral['max_uses'] ?? 1));
                        $pendingTotal = (int) ($referral['pending_total'] ?? 0);
                        $approvedTotal = (int) ($referral['approved_total'] ?? 0);
                        $registrationsTotal = (int) ($referral['registrations_total'] ?? 0);
                        $usagePercent = min(100, (int) round(($usedCount / $maxUses) * 100));
                        $canDeleteReferral = $usedCount === 0 && $registrationsTotal === 0;
                    ?>
                    <div class="sa-referral-shell flex flex-col h-full">
                        <div class="p-5 border-b border-border <?php echo $refStatus === 'active' ? 'bg-emerald-50/40' : 'bg-slate-50/70'; ?>">
                            <div class="flex justify-between items-start gap-4 mb-4">
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 text-lg leading-tight"><?php echo e((string) ($referral['label'] ?? '-')); ?></div>
                                    <div class="text-xs text-muted mt-1"><?php echo e((string) ($referral['note'] ?? 'Tanpa catatan')); ?></div>
                                </div>
                                <span class="badge <?php echo $refStatus === 'active' ? 'badge-success' : ($refStatus === 'inactive' ? 'badge-neutral' : 'badge-warning'); ?> flex items-center gap-1.5 shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full <?php echo $refStatus === 'active' ? 'bg-emerald-500' : ($refStatus === 'inactive' ? 'bg-slate-400' : 'bg-amber-500'); ?>"></span>
                                    <?php echo e(strtoupper($refStatus)); ?>
                                </span>
                            </div>

                            <div class="sa-referral-codebar">
                                <div class="flex items-center gap-2 min-w-0">
                                    <i data-lucide="key-round" class="w-4 h-4 text-emerald-300 shrink-0"></i>
                                    <span class="sa-referral-code"><?php echo e($referralCode); ?></span>
                                </div>
                                <div class="sa-referral-actions">
                                    <button type="button" class="sa-referral-mini-btn" data-copy-value="<?php echo e($referralCode); ?>">
                                        <i data-lucide="copy" class="w-4 h-4"></i>
                                        Kode
                                    </button>
                                    <button type="button" class="sa-referral-mini-btn" data-copy-value="<?php echo e($referralLink); ?>">
                                        <i data-lucide="link" class="w-4 h-4"></i>
                                        Link
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 flex-1">
                            <div class="mb-5">
                                <div class="flex justify-between items-center gap-3 mb-2">
                                    <div class="text-xs font-bold text-muted uppercase tracking-wide">Pemakaian Kuota</div>
                                    <div class="text-xs font-bold text-slate-700"><?php echo e((string) $usedCount); ?>/<?php echo e((string) $maxUses); ?> terpakai</div>
                                </div>
                                <div class="sa-referral-progress" aria-label="Pemakaian referral <?php echo e((string) $usagePercent); ?> persen">
                                    <span style="width: <?php echo $usagePercent; ?>%;"></span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 mb-6">
                                <span class="badge badge-neutral border-0 bg-slate-100 text-slate-700">Dipakai: <?php echo e((string) $usedCount); ?>/<?php echo e((string) $maxUses); ?></span>
                                <span class="badge badge-warning border-0 bg-amber-50 text-amber-700">Pending: <?php echo e((string) $pendingTotal); ?></span>
                                <span class="badge badge-success border-0 bg-emerald-50 text-emerald-700">Approved: <?php echo e((string) $approvedTotal); ?></span>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="text-xs text-muted font-medium mb-1">Generator</div>
                                    <div class="font-medium text-slate-900 truncate"><?php echo e((string) ($referral['generated_by_name'] ?? 'Super Admin')); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted font-medium mb-1">Dibuat</div>
                                    <div class="font-medium text-slate-900"><?php echo e($formatDateTime($referral['created_at'] ?? null)); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted font-medium mb-1">Kedaluwarsa</div>
                                    <div class="font-medium text-slate-900"><?php echo e($formatDateTime($referral['expires_at'] ?? null)); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted font-medium mb-1">Sisa Kuota</div>
                                    <div class="font-medium text-slate-900"><?php echo e((string) ($referral['remaining_uses'] ?? 0)); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 border-t border-border bg-slate-50 mt-auto space-y-3">
                            <details class="rounded-2xl border border-slate-200 bg-white p-4">
                                <summary class="cursor-pointer select-none font-bold text-slate-900 flex items-center gap-2">
                                    <i data-lucide="pencil" class="w-4 h-4 text-emerald-600"></i>
                                    Edit Referral
                                </summary>
                                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_referral">
                                    <input type="hidden" name="referral_id" value="<?php echo (int) ($referral['id'] ?? 0); ?>">
                                    <div class="form-group mb-0 md:col-span-2">
                                        <label class="form-label text-xs">Kode Referral</label>
                                        <input type="text" class="form-input w-full font-mono bg-slate-50" value="<?php echo e((string) ($referral['code'] ?? '-')); ?>" readonly>
                                    </div>
                                    <div class="form-group mb-0 md:col-span-2">
                                        <label class="form-label text-xs">Nama Batch</label>
                                        <input type="text" class="form-input w-full" name="label" value="<?php echo e((string) ($referral['label'] ?? '')); ?>" required>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="form-label text-xs">Kuota</label>
                                        <input type="number" class="form-input w-full" name="max_uses" min="<?php echo max(1, (int) ($referral['used_count'] ?? 0)); ?>" value="<?php echo e((string) ($referral['max_uses'] ?? 1)); ?>" required>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="form-label text-xs">Kedaluwarsa</label>
                                        <input type="datetime-local" class="form-input w-full" name="expires_at" value="<?php echo e($referralExpiresInput); ?>">
                                    </div>
                                    <div class="form-group mb-0 md:col-span-2">
                                        <label class="form-label text-xs">Catatan</label>
                                        <input type="text" class="form-input w-full" name="note" value="<?php echo e((string) ($referral['note'] ?? '')); ?>" placeholder="Opsional">
                                    </div>
                                    <label class="md:col-span-2 inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                        <input class="form-check-input mt-0" type="checkbox" name="is_active" value="1" <?php echo (int) ($referral['is_active'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                        Referral aktif
                                    </label>
                                    <div class="md:col-span-2">
                                        <button class="btn btn-primary w-full justify-center" type="submit">
                                            <i data-lucide="save" class="w-4 h-4"></i>
                                            Simpan Perubahan
                                        </button>
                                    </div>
                                </form>
                            </details>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="referral_id" value="<?php echo (int) ($referral['id'] ?? 0); ?>">
                                    <input type="hidden" name="action" value="<?php echo (int) ($referral['is_active'] ?? 0) === 1 ? 'deactivate_referral' : 'activate_referral'; ?>">
                                    <button class="btn w-full justify-center <?php echo (int) ($referral['is_active'] ?? 0) === 1 ? 'btn-secondary text-red-600 hover:bg-red-50 hover:border-red-200' : 'btn-primary'; ?>" type="submit">
                                        <i data-lucide="<?php echo (int) ($referral['is_active'] ?? 0) === 1 ? 'power-off' : 'power'; ?>" class="w-4 h-4"></i>
                                        <?php echo (int) ($referral['is_active'] ?? 0) === 1 ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                    </button>
                                </form>
                                <form method="POST" class="referral-delete-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="referral_id" value="<?php echo (int) ($referral['id'] ?? 0); ?>">
                                    <input type="hidden" name="action" value="delete_referral">
                                    <button class="btn btn-secondary w-full justify-center text-red-600 hover:bg-red-50 hover:border-red-200" type="submit" <?php echo $canDeleteReferral ? '' : 'disabled'; ?>>
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        Hapus
                                    </button>
                                </form>
                            </div>
                            <?php if (!$canDeleteReferral): ?>
                                <div class="sa-referral-locked">
                                    <i data-lucide="shield-alert" class="w-4 h-4 shrink-0 mt-0.5"></i>
                                    <span>Referral sudah dipakai pengajuan toko, jadi tidak bisa dihapus permanen. Nonaktifkan referral untuk menutup penggunaan baru.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($focusSection === 'requests'): ?>
        <div class="card p-6 mb-6">
            <form method="GET" class="flex flex-col md:flex-row items-end gap-4">
                <input type="hidden" name="focus" value="requests">
                <div class="form-group mb-0 flex-1">
                    <label class="form-label text-xs">Cari Pengajuan</label>
                    <input type="text" name="q" class="form-input w-full" value="<?php echo e($searchQuery); ?>" placeholder="Cari nama toko, kode, owner, admin, email, atau referral">
                </div>
                <div class="form-group mb-0 w-full md:w-48">
                    <label class="form-label text-xs">Status</label>
                    <select name="status" class="form-input w-full">
                        <option value="all" <?php echo $currentFilter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="pending" <?php echo $currentFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $currentFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $currentFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <button class="btn btn-primary w-full md:w-auto" type="submit">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    Filter
                </button>
            </form>
        </div>

        <?php if (empty($stores)): ?>
            <div class="card p-10 text-center">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="store" class="w-8 h-8"></i>
                </div>
                <div class="font-bold text-slate-900 mb-1">Belum ada pengajuan pada filter ini</div>
                <div class="text-sm text-muted">Pastikan calon toko memakai referral aktif saat mendaftar.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <?php foreach ($stores as $store): ?>
                    <?php $status = (string) ($store['status'] ?? 'pending'); ?>
                    <div class="card flex flex-col h-full border <?php echo $status === 'pending' ? 'border-amber-200' : 'border-slate-200'; ?>">
                        <div class="p-5 border-b border-border flex justify-between items-start <?php echo $status === 'pending' ? 'bg-amber-50/30' : 'bg-slate-50/50'; ?>">
                            <div>
                                <div class="font-bold text-slate-900 text-lg mb-1 flex items-center gap-2">
                                    <?php echo e((string) ($store['store_name'] ?? '-')); ?>
                                </div>
                                <div class="text-xs text-muted flex items-center gap-2">
                                    <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-700"><?php echo e((string) ($store['store_code'] ?? '-')); ?></span>
                                    <span>&bull;</span>
                                    <span>Ref: <?php echo e((string) ($store['referral_code'] ?? '-')); ?></span>
                                </div>
                            </div>
                            <span class="badge <?php echo $status === 'pending' ? 'badge-warning' : ($status === 'approved' ? 'badge-success' : 'badge-error'); ?> capitalize">
                                <?php echo e($status); ?>
                            </span>
                        </div>

                        <div class="p-5 flex-1 space-y-6">
                            <div class="flex flex-wrap gap-2">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium">
                                    <i data-lucide="user" class="w-3 h-3 text-slate-500"></i>
                                    <?php echo e((string) ($store['owner_name'] ?? '-')); ?>
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium">
                                    <i data-lucide="shield" class="w-3 h-3 text-slate-500"></i>
                                    Admin: <?php echo e((string) ($store['admin_username'] ?? '-')); ?>
                                </div>
                                <?php if (!empty($store['store_phone'])): ?>
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium">
                                        <i data-lucide="phone" class="w-3 h-3 text-slate-500"></i>
                                        <?php echo e((string) $store['store_phone']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                    <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2">Profil Toko</div>
                                    <div class="text-sm font-medium text-slate-900 mb-1"><?php echo e((string) ($store['store_tagline'] ?: 'Belum ada info singkat.')); ?></div>
                                    <div class="text-xs text-muted leading-relaxed"><?php echo e((string) ($store['store_address'] ?? '-')); ?></div>
                                </div>
                                <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                    <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2">Penanggung Jawab</div>
                                    <div class="text-sm font-medium text-slate-900 mb-1"><?php echo e((string) ($store['owner_name'] ?? '-')); ?></div>
                                    <div class="text-xs text-muted space-y-0.5">
                                        <?php if (!empty($store['owner_phone'])): ?><div><?php echo e((string) $store['owner_phone']); ?></div><?php endif; ?>
                                        <?php if (!empty($store['owner_email'])): ?><div><?php echo e((string) $store['owner_email']); ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2">Akses Admin</div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <div class="text-xs text-muted mb-0.5">Nama Admin</div>
                                        <div class="text-sm font-medium text-slate-900"><?php echo e((string) ($store['admin_name'] ?? '-')); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-muted mb-0.5">Username</div>
                                        <div class="text-sm font-medium text-slate-900"><?php echo e((string) ($store['admin_username'] ?? '-')); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-muted mb-0.5">Batch Referral</div>
                                        <div class="text-sm font-medium text-slate-900 truncate"><?php echo e((string) ($store['referral_label'] ?? 'Tidak diketahui')); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 border-t border-border bg-slate-50 mt-auto">
                            <?php if ($status === 'pending'): ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <form method="POST" class="h-full">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="store_id" value="<?php echo (int) $store['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-primary w-full h-full min-h-[44px] justify-center" type="submit">
                                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                                Setujui & Buat Akun
                                            </button>
                                        </form>
                                    </div>
                                    <div class="bg-white p-3 rounded-lg border border-slate-200">
                                        <form method="POST" class="flex flex-col gap-2">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="store_id" value="<?php echo (int) $store['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="text" class="form-input text-sm px-3 py-1.5 h-auto" name="approval_note" placeholder="Alasan tolak (contoh: data kurang lengkap)">
                                            <button class="btn btn-secondary w-full justify-center text-red-600 hover:bg-red-50 hover:border-red-200 py-1.5 h-auto text-sm" type="submit">
                                                <i data-lucide="x-circle" class="w-4 h-4"></i>
                                                Tolak Pengajuan
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full <?php echo $status === 'approved' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'; ?> flex items-center justify-center shrink-0">
                                        <i data-lucide="<?php echo $status === 'approved' ? 'check-circle' : 'x-circle'; ?>" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-0.5"><?php echo $status === 'approved' ? 'Status Approval' : 'Catatan Penolakan'; ?></div>
                                        <div class="text-sm font-medium text-slate-900">
                                            <?php echo $status === 'approved'
                                                ? e('Disetujui oleh ' . (string) ($store['approved_by_name'] ?? 'superadmin') . ' pada ' . $formatDateTime($store['approved_at'] ?? null) . '.')
                                                : e((string) ($store['approval_note'] ?: 'Tanpa catatan penolakan')); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php elseif ($focusSection === 'stores'): ?>
        <div class="card p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between md:items-end gap-6">
                <div>
                    <h3 class="font-bold text-slate-900 mb-1">Direktori Toko Aktif</h3>
                    <p class="text-sm text-muted mb-0">Kelola lifecycle toko live, cek kesehatan operasional, dan suspend akses.</p>
                </div>
                <form method="GET" class="flex items-end gap-3 w-full md:w-auto">
                    <input type="hidden" name="focus" value="stores">
                    <div class="form-group mb-0 flex-1 md:w-64">
                        <label class="form-label text-xs">Cari Toko</label>
                        <input type="text" name="store_q" class="form-input w-full" value="<?php echo e($activeStoreSearch); ?>" placeholder="Nama, kode, admin...">
                    </div>
                    <button class="btn btn-primary h-11" type="submit">
                        <i data-lucide="search" class="w-4 h-4"></i>
                        Cari
                    </button>
                </form>
            </div>
        </div>

        <?php if (empty($storeDirectory)): ?>
            <div class="card p-10 text-center">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="store" class="w-8 h-8"></i>
                </div>
                <div class="font-bold text-slate-900 mb-1">Belum ada toko aktif pada filter ini</div>
                <div class="text-sm text-muted">Toko live akan tampil di sini setelah approval selesai.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($storeDirectory as $store): ?>
                    <div class="card flex flex-col md:flex-row overflow-hidden border <?php echo ((string) ($store['operational_status'] ?? 'active') === 'suspended') ? 'border-red-200' : 'border-slate-200'; ?>">
                        <!-- Left Panel: Info -->
                        <div class="flex-1 p-6 md:border-r border-border">
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <div class="font-bold text-slate-900 text-xl mb-1"><?php echo e((string) ($store['store_name'] ?? '-')); ?></div>
                                    <div class="text-sm text-slate-600 flex items-center gap-2">
                                        <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-xs font-bold"><?php echo e((string) ($store['store_code'] ?? '-')); ?></span>
                                        <span>&bull;</span>
                                        <span class="flex items-center gap-1"><i data-lucide="user" class="w-3 h-3 text-slate-400"></i> <?php echo e((string) ($store['admin_username'] ?? '-')); ?></span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="badge <?php echo ((string) ($store['operational_status'] ?? 'active') === 'suspended' ? 'badge-error' : 'badge-success'); ?>">
                                        <?php echo e((string) ($store['operational_status_label'] ?? 'Active')); ?>
                                    </span>
                                    <span class="badge <?php echo e($healthBadge((string) ($store['health_level'] ?? 'healthy'))); ?> text-[10px]">
                                        <?php echo e((string) ($store['health_label'] ?? 'Sehat')); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 mb-6">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium">
                                    <i data-lucide="key" class="w-3 h-3 text-slate-500"></i>
                                    Ref: <?php echo e((string) ($store['referral_code'] ?? 'INTERNAL')); ?>
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium">
                                    <i data-lucide="tag" class="w-3 h-3 text-slate-500"></i>
                                    <?php echo e((string) ($store['referral_label'] ?? 'Internal / Demo')); ?>
                                </div>
                                <?php if ($supportEnabled): ?>
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium">
                                        <i data-lucide="message-square" class="w-3 h-3 text-slate-500"></i>
                                        <?php echo e((string) ($store['support_state_label'] ?? 'Belum Ada Room')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-2 <?php echo $supportEnabled ? 'lg:grid-cols-4' : 'lg:grid-cols-3'; ?> gap-4 mb-6">
                                <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                    <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-1">Health Monitor</div>
                                    <div class="text-xs font-medium text-slate-900"><?php echo e((string) ($store['health_note'] ?? 'Tidak ada catatan.')); ?></div>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                    <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-1">Login Terakhir</div>
                                    <div class="text-xs font-medium text-slate-900"><?php echo e($formatDateTime($store['last_login_at'] ?? null)); ?></div>
                                </div>
                                <?php if ($supportEnabled): ?>
                                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                        <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-1">Aktivitas Support</div>
                                        <div class="text-xs font-medium text-slate-900"><?php echo e((string) ($store['support_note'] ?? 'Belum ada aktivitas.')); ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                    <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-1">Approval</div>
                                    <div class="text-xs font-medium text-slate-900"><?php echo e($formatDateTime($store['approved_at'] ?? null)); ?></div>
                                </div>
                            </div>

                            <div class="text-sm">
                                <div class="font-bold text-slate-900 mb-1">Profil Toko</div>
                                <div class="text-slate-600 mb-2"><?php echo e((string) ($store['store_tagline'] ?: 'Belum ada info singkat.')); ?></div>
                                <div class="text-muted text-xs leading-relaxed">
                                    <?php echo e((string) ($store['store_address'] ?? '-')); ?>
                                    <?php if (!empty($store['store_phone']) || !empty($store['store_email'])): ?>
                                        <div class="mt-1">
                                            <?php if (!empty($store['store_phone'])): ?><span class="mr-3"><i data-lucide="phone" class="w-3 h-3 inline"></i> <?php echo e((string) $store['store_phone']); ?></span><?php endif; ?>
                                            <?php if (!empty($store['store_email'])): ?><span><i data-lucide="mail" class="w-3 h-3 inline"></i> <?php echo e((string) $store['store_email']); ?></span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Actions -->
                        <div class="w-full md:w-80 bg-slate-50 p-6 flex flex-col justify-between">
                            <div class="mb-4">
                                <a class="btn btn-primary w-full justify-center" href="<?php echo e(base_url('superadmin_store_edit.php?id=' . (int) ($store['id'] ?? 0))); ?>">
                                    <i data-lucide="settings-2" class="w-4 h-4"></i>
                                    Edit Profil Toko
                                </a>
                            </div>
                            <div class="mb-6">
                                <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                    <i data-lucide="shield-alert" class="w-3 h-3"></i> Kontrol Akses
                                </div>
                                <p class="text-xs text-muted mb-4 leading-relaxed">Suspend akan menahan login web dan API sampai Anda mengaktifkannya kembali.</p>

                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="set_store_operational_status">
                                    <input type="hidden" name="store_id" value="<?php echo (int) ($store['id'] ?? 0); ?>">
                                    <input type="hidden" name="operational_status" value="<?php echo (string) ($store['operational_status'] ?? 'active') === 'suspended' ? 'active' : 'suspended'; ?>">

                                    <div class="mb-3">
                                        <input type="text" class="form-input w-full text-sm" name="operational_note" value="<?php echo e((string) ($store['operational_note'] ?? '')); ?>" placeholder="Alasan / Catatan Opsional">
                                    </div>

                                    <button class="btn w-full justify-center <?php echo (string) ($store['operational_status'] ?? 'active') === 'suspended' ? 'btn-primary' : 'btn-secondary text-red-600 hover:bg-red-50 hover:border-red-200'; ?>" type="submit">
                                        <i data-lucide="<?php echo (string) ($store['operational_status'] ?? 'active') === 'suspended' ? 'play-circle' : 'pause-circle'; ?>" class="w-4 h-4"></i>
                                        <?php echo (string) ($store['operational_status'] ?? 'active') === 'suspended' ? 'Aktifkan Kembali' : 'Suspend Toko Ini'; ?>
                                    </button>
                                </form>
                            </div>

                            <div class="border-t border-slate-200 pt-4 mb-6">
                                <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                    <i data-lucide="globe-2" class="w-3 h-3"></i> Custom Domain
                                </div>
                                <?php $dnsTarget = function_exists('store_domain_dns_target') ? store_domain_dns_target() : ''; ?>
                                <p class="text-xs text-muted mb-3 leading-relaxed">
                                    Arahkan CNAME/A record domain mitra ke server pusat KASPINDO<?php echo $dnsTarget !== '' ? ' (' . e($dnsTarget) . ')' : ''; ?>, lalu daftarkan host-nya di sini.
                                </p>

                                <?php $domains = is_array($store['domains'] ?? null) ? $store['domains'] : []; ?>
                                <?php if (!empty($domains)): ?>
                                    <div class="space-y-2 mb-3">
                                        <?php foreach ($domains as $domain): ?>
                                            <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2">
                                                <div class="min-w-0">
                                                    <div class="text-xs font-bold text-slate-900 truncate"><?php echo e((string) ($domain['domain'] ?? '-')); ?></div>
                                                    <div class="text-[10px] text-muted uppercase"><?php echo e((string) ($domain['status'] ?? 'active')); ?></div>
                                                    <?php if ((string) ($domain['status'] ?? '') === 'active'): ?>
                                                        <a class="text-[10px] font-semibold text-emerald-700" href="<?php echo e('https://' . (string) ($domain['domain'] ?? '')); ?>" target="_blank" rel="noopener noreferrer">Buka domain</a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ((string) ($domain['status'] ?? '') === 'active'): ?>
                                                    <form method="POST" class="m-0">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="deactivate_store_domain">
                                                        <input type="hidden" name="store_id" value="<?php echo (int) ($store['id'] ?? 0); ?>">
                                                        <input type="hidden" name="domain_id" value="<?php echo (int) ($domain['id'] ?? 0); ?>">
                                                        <button class="btn btn-secondary px-2 py-1 h-auto text-xs text-red-600" type="submit">Nonaktifkan</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" class="grid gap-2">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="add_store_domain">
                                    <input type="hidden" name="store_id" value="<?php echo (int) ($store['id'] ?? 0); ?>">
                                    <input type="text" class="form-input w-full text-sm" name="custom_domain" placeholder="kaspindo.mitra.example.com" inputmode="url" autocomplete="off">
                                    <button class="btn btn-primary w-full justify-center" type="submit">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                        Tambah Domain
                                    </button>
                                </form>
                            </div>

                            <div class="border-t border-slate-200 pt-4">
                                <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-2">Riwayat Operasional</div>
                                <div class="text-xs text-slate-700 leading-relaxed italic">
                                    "<?php echo e(trim((string) ($store['operational_note'] ?? '')) !== '' ? (string) ($store['operational_note'] ?? '') : 'Berjalan normal.'); ?>"
                                </div>
                                <?php if (!empty($store['suspended_at'])): ?>
                                    <div class="text-[10px] text-muted mt-2 font-medium">
                                        Terakhir disuspend: <?php echo e($formatDateTime($store['suspended_at'] ?? null)); ?> oleh <?php echo e((string) ($store['suspended_by_name'] ?? 'Super Admin')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($focusSection === 'maintenance'): ?>
        <?php
            $maintenanceActive = !empty($maintenanceConfig['active']);
            $maintenanceUpdatedAt = !empty($maintenanceConfig['updated_at']) ? $formatDateTime((string) $maintenanceConfig['updated_at']) : '-';
            $maintenanceUpdatedBy = trim((string) ($maintenanceConfig['updated_by_name'] ?? ''));
        ?>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
            <div class="card sa-panel p-6 xl:col-span-2">
                <div class="sa-panel-head">
                    <div class="sa-panel-icon">
                        <i data-lucide="wrench" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-emerald-700 uppercase tracking-[0.16em] mb-1">Kontrol Sistem</div>
                        <h3 class="font-bold text-slate-900 text-xl m-0">Pengaturan Maintenance</h3>
                        <p class="text-sm text-muted m-0">Aktifkan saat sistem perlu jeda sementara atau update produksi.</p>
                    </div>
                </div>

                <form method="POST" class="space-y-5">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_maintenance">

                    <div class="sa-maint-toggle <?php echo $maintenanceActive ? 'border-orange-200 bg-orange-50' : ''; ?>">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input class="form-check-input mt-1" type="checkbox" role="switch" id="maintenance_active" name="maintenance_active" value="1" <?php echo $maintenanceActive ? 'checked' : ''; ?>>
                            <div>
                                <span class="font-bold text-slate-900 block flex items-center gap-2">
                                    <i data-lucide="<?php echo $maintenanceActive ? 'shield-alert' : 'shield-check'; ?>" class="w-4 h-4 <?php echo $maintenanceActive ? 'text-orange-600' : 'text-emerald-600'; ?>"></i>
                                    Aktifkan Mode Maintenance
                                </span>
                                <span class="text-xs text-slate-600 mt-1 block leading-relaxed">Saat aktif, akses login dan operasional toko akan ditangguhkan untuk seluruh tenant. Super Admin akan tetap dapat login untuk memantau sistem.</span>
                            </div>
                        </label>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label">Judul Halaman Maintenance</label>
                        <input type="text" class="form-input w-full" name="maintenance_title" value="<?php echo e((string) ($maintenanceConfig['title'] ?? '')); ?>" placeholder="Contoh: Sistem sedang dalam perbaikan rutin" required>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label">Pesan Untuk Pengguna</label>
                        <textarea class="form-input w-full min-h-[120px]" name="maintenance_message" required placeholder="Jelaskan alasan maintenance dan estimasi waktu selesai..."><?php echo e((string) ($maintenanceConfig['message'] ?? '')); ?></textarea>
                    </div>

                    <div class="pt-4 border-t border-border">
                        <button class="btn kp-btn-primary" type="submit">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Simpan Pengaturan Maintenance
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <!-- Status Preview Box -->
                <div class="card sa-status-card <?php echo $maintenanceActive ? 'maintenance' : ''; ?> p-6 border-t-4 <?php echo $maintenanceActive ? 'border-t-orange-500 bg-orange-50/30' : 'border-t-emerald-500 bg-emerald-50/30'; ?>">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="relative flex h-3 w-3">
                            <?php if ($maintenanceActive): ?>
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-orange-500"></span>
                            <?php else: ?>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            <?php endif; ?>
                        </span>
                        <span class="text-xs font-bold uppercase tracking-wider <?php echo $maintenanceActive ? 'text-orange-700' : 'text-emerald-700'; ?>">
                            <?php echo $maintenanceActive ? 'SYSTEM MAINTENANCE' : 'SYSTEM OPERATIONAL'; ?>
                        </span>
                    </div>

                    <div class="w-11 h-11 rounded-2xl <?php echo $maintenanceActive ? 'bg-orange-100 text-orange-700' : 'bg-emerald-100 text-emerald-700'; ?> flex items-center justify-center mb-4">
                        <i data-lucide="<?php echo $maintenanceActive ? 'construction' : 'activity'; ?>" class="w-5 h-5"></i>
                    </div>
                    <h4 class="font-bold text-slate-900 text-xl mb-2"><?php echo e((string) ($maintenanceConfig['title'] ?? '-')); ?></h4>
                    <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line m-0"><?php echo e((string) ($maintenanceConfig['message'] ?? '-')); ?></p>
                </div>

                <!-- Status Log Box -->
                <div class="card sa-panel p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                            <i data-lucide="history" class="w-4 h-4"></i>
                        </div>
                        <div class="text-[10px] font-bold text-muted uppercase tracking-wide">Log Status</div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0">
                                <i data-lucide="clock" class="w-4 h-4 text-slate-500"></i>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-900">Perubahan Terakhir</div>
                                <div class="text-xs text-muted mt-0.5"><?php echo e($maintenanceUpdatedAt); ?></div>
                                <div class="mt-1">
                                    <span class="badge text-[10px] <?php echo $maintenanceActive ? 'badge-warning' : 'badge-success'; ?>"><?php echo $maintenanceActive ? 'Diaktifkan' : 'Dinonaktifkan'; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0">
                                <i data-lucide="user" class="w-4 h-4 text-slate-500"></i>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-900">Diubah Oleh</div>
                                <div class="text-xs text-muted mt-0.5"><?php echo e($maintenanceUpdatedBy !== '' ? $maintenanceUpdatedBy : 'Super Admin'); ?></div>
                                <div class="mt-1">
                                    <span class="badge text-[10px] badge-neutral">System Administrator</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        const fallbackCopy = (value) => {
            const textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.pointerEvents = 'none';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        };

        const showCopyState = (button, label) => {
            if (!button.dataset.originalHtml) {
                button.dataset.originalHtml = button.innerHTML;
            }

            button.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>' + label;
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }

            window.clearTimeout(button._copyTimer);
            button._copyTimer = window.setTimeout(() => {
                button.innerHTML = button.dataset.originalHtml || button.innerHTML;
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            }, 1400);
        };

        document.querySelectorAll('[data-copy-value]').forEach((button) => {
            button.addEventListener('click', async () => {
                const value = button.getAttribute('data-copy-value') || '';
                if (!value) {
                    return;
                }

                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(value);
                    } else {
                        fallbackCopy(value);
                    }
                    showCopyState(button, 'Tersalin');
                } catch (error) {
                    try {
                        fallbackCopy(value);
                        showCopyState(button, 'Tersalin');
                    } catch (fallbackError) {
                        showCopyState(button, 'Gagal');
                    }
                }
            });
        });

        document.querySelectorAll('.referral-delete-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!window.confirm('Hapus referral ini secara permanen? Tindakan ini tidak bisa dibatalkan.')) {
                    event.preventDefault();
                }
            });
        });
    })();

    (function () {
        const endpoint = '<?php echo e(base_url('superadmin_backup_ping.php')); ?>';
        const csrfToken = '<?php echo e(csrf_token()); ?>';
        const intervalMs = 5 * 60 * 1000;

        const payload = new URLSearchParams();
        payload.set('csrf_token', csrfToken);

        const runBackupPing = () => {
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload.toString(),
                credentials: 'same-origin'
            }).catch(() => {});
        };

        runBackupPing();
        window.setInterval(runBackupPing, intervalMs);
    })();
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
