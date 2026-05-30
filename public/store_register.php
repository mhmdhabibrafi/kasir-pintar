<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/store_registration.php';

$title = 'Pendaftaran Toko KASPINDO';
$hideTopbar = true;
$forceAuthLayout = true;
require_once __DIR__ . '/../app/views/layouts/header.php';
?>

<style>
    body.kp-auth-page {
        background: #f8fafc;
    }
    .kp-auth-page .kp-auth {
        align-items: flex-start;
        padding: 32px 16px;
    }
    .kp-auth-page .kp-auth .kp-auth-shell.kp-register-shell {
        width: min(980px, 100%);
        max-width: min(980px, 100%);
        margin: 0 auto;
    }
    .kp-register-card {
        border: 1px solid rgba(15, 23, 42, 0.10);
        background: #ffffff;
        box-shadow: 0 24px 70px -54px rgba(15, 23, 42, 0.42);
    }
    .kp-register-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        padding-bottom: 22px;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        margin-bottom: 24px;
    }
    .kp-register-brand {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }
    .kp-register-brandmark {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: var(--kp-primary);
    }
    .kp-register-brandmark .material-icons-outlined {
        font-size: 23px;
    }
    .kp-register-brand-title {
        display: block;
        color: #172033;
        font-size: 15px;
        font-weight: 850;
        line-height: 1.15;
    }
    .kp-register-brand-subtitle {
        display: block;
        color: #667085;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.3;
        margin-top: 2px;
    }
    .kp-register-title {
        color: #172033;
        font-size: clamp(24px, 3vw, 32px);
        font-weight: 850;
        line-height: 1.14;
        letter-spacing: 0;
        margin: 0 0 8px;
    }
    .kp-register-subtitle {
        color: #667085;
        font-size: 14px;
        line-height: 1.55;
        margin: 0;
        max-width: 620px;
    }
    .kp-register-login {
        flex: 0 0 auto;
        min-height: 42px;
        border-radius: 12px;
        white-space: nowrap;
    }
    .kp-register-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px 20px;
        align-items: start;
    }
    .kp-register-full {
        grid-column: 1 / -1;
    }
    .kp-register-section {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 8px;
        margin-top: 4px;
        color: #172033;
        font-size: 15px;
        font-weight: 850;
        line-height: 1.35;
    }
    .kp-register-section::before {
        content: "";
        width: 4px;
        height: 18px;
        border-radius: 999px;
        background: var(--kp-primary);
        flex: 0 0 auto;
    }
    .kp-register-field {
        display: grid;
        gap: 8px;
        min-width: 0;
    }
    .kp-register-card .form-label {
        margin: 0;
        color: #243142;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.35;
    }
    .kp-register-required {
        color: #dc2626;
        font-weight: 850;
    }
    .kp-register-card .form-control {
        min-height: 48px;
        border-radius: 12px;
        border-color: rgba(15, 23, 42, 0.14);
        background: #fff;
        color: #172033;
        font-size: 14px;
        font-weight: 600;
        transition: border-color 160ms ease, box-shadow 160ms ease;
    }
    .kp-register-card textarea.form-control {
        min-height: 96px;
        resize: vertical;
    }
    .kp-register-card .form-control:focus {
        border-color: rgba(0, 191, 99, 0.55);
        box-shadow: 0 0 0 4px rgba(0, 191, 99, 0.10);
    }
    .kp-register-help {
        color: #667085;
        font-size: 12px;
        line-height: 1.45;
    }
    .kp-register-referral {
        display: grid;
        gap: 8px;
        padding: 18px;
        border: 1px solid rgba(0, 143, 74, 0.16);
        border-radius: 16px;
        background: #f7fffb;
    }
    .kp-register-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 10px;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
        margin-top: 6px;
    }
    .kp-register-actions .btn {
        min-height: 48px;
        min-width: 170px;
        border-radius: 12px;
        font-weight: 850;
    }
    .kp-register-trap {
        position: absolute;
        left: -9999px;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }
    @media (min-width: 992px) {
        .kp-register-card {
            padding: 34px 38px !important;
        }
    }
    @media (max-width: 767.98px) {
        .kp-auth-page .kp-auth {
            padding: 14px;
        }
        .kp-register-card {
            padding: 20px 16px !important;
            border-radius: 18px;
        }
        .kp-register-header {
            display: grid;
            gap: 14px;
            margin-bottom: 20px;
            padding-bottom: 18px;
        }
        .kp-register-login {
            width: 100%;
            justify-content: center;
        }
        .kp-register-form {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        .kp-register-card .form-control {
            min-height: 48px;
            font-size: 16px;
        }
        .kp-register-actions {
            display: grid;
            grid-template-columns: 1fr;
        }
        .kp-register-actions .btn {
            width: 100%;
            min-width: 0;
        }
    }
</style>

<div class="kp-auth">
    <div class="kp-auth-shell kp-register-shell w-100">
        <div class="kp-card kp-register-card p-4 p-md-5">
            <div class="kp-register-header">
                <div>
                    <div class="kp-register-brand">
                        <span class="kp-register-brandmark">
                            <span class="material-icons-outlined">point_of_sale</span>
                        </span>
                        <span>
                            <span class="kp-register-brand-title">KASPINDO</span>
                            <span class="kp-register-brand-subtitle">Pendaftaran mitra</span>
                        </span>
                    </div>
                    <h1 class="kp-register-title">Pendaftaran Toko</h1>
                    <p class="kp-register-subtitle">Lengkapi data berikut. Pengajuan akan diperiksa sebelum akun admin toko diaktifkan.</p>
                </div>
                <a class="btn kp-btn-ghost kp-register-login" href="<?php echo e(base_url('login.php')); ?>">
                    <span class="material-icons-outlined">login</span>
                    Masuk
                </a>
            </div>

            <?php if (!empty($registrationErrors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($registrationErrors as $error): ?>
                        <div><?php echo e($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($registrationSuccess)): ?>
                <div class="alert alert-success">
                    <?php echo e($registrationSuccess); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($registrationBlocked)): ?>
                <div class="alert alert-warning">
                    <div class="fw-semibold mb-1"><?php echo e((string) ($maintenanceConfig['title'] ?? 'Sistem sedang maintenance')); ?></div>
                    <div><?php echo nl2br(e((string) ($maintenanceConfig['message'] ?? 'Pendaftaran toko sedang ditutup sementara.')), false); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" class="kp-register-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="form_action" value="register_store">
                <div class="kp-register-trap" aria-hidden="true">
                    <label>Website Perusahaan</label>
                    <input type="text" name="company_website" value="" autocomplete="off" tabindex="-1">
                </div>

                <div class="kp-register-full kp-register-referral">
                    <div class="kp-register-field">
                        <label class="form-label">Kode Referral <span class="kp-register-required">*</span></label>
                        <input type="text" class="form-control text-uppercase" name="referral_code" value="<?php echo e($registrationOld['referral_code'] ?? ''); ?>" placeholder="KSP-2603-AB12CD34" autocomplete="off" required <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                        <div class="kp-register-help">Kode referral diberikan oleh superadmin KASPINDO.</div>
                    </div>
                </div>

                <div class="kp-register-full kp-register-section">Profil Toko</div>

                <div class="kp-register-field">
                    <label class="form-label">Nama Toko / Outlet <span class="kp-register-required">*</span></label>
                    <input type="text" class="form-control" name="store_name" value="<?php echo e($registrationOld['store_name'] ?? ''); ?>" autocomplete="organization" required <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-field">
                    <label class="form-label">Tagline / Info Singkat</label>
                    <input type="text" class="form-control" name="store_tagline" value="<?php echo e($registrationOld['store_tagline'] ?? ''); ?>" placeholder="Opsional" <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-full kp-register-field">
                    <label class="form-label">Alamat Toko <span class="kp-register-required">*</span></label>
                    <textarea class="form-control" name="store_address" rows="3" required <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>><?php echo e($registrationOld['store_address'] ?? ''); ?></textarea>
                </div>

                <div class="kp-register-field">
                    <label class="form-label">Telepon Toko</label>
                    <input type="tel" class="form-control" name="store_phone" value="<?php echo e($registrationOld['store_phone'] ?? ''); ?>" inputmode="tel" autocomplete="tel" maxlength="25" <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-field">
                    <label class="form-label">Email Toko</label>
                    <input type="email" class="form-control" name="store_email" value="<?php echo e($registrationOld['store_email'] ?? ''); ?>" autocomplete="email" <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-full kp-register-section">Penanggung Jawab</div>

                <div class="kp-register-field">
                    <label class="form-label">Nama Pemilik <span class="kp-register-required">*</span></label>
                    <input type="text" class="form-control" name="owner_name" value="<?php echo e($registrationOld['owner_name'] ?? ''); ?>" autocomplete="name" required <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-field">
                    <label class="form-label">Telepon Pemilik</label>
                    <input type="tel" class="form-control" name="owner_phone" value="<?php echo e($registrationOld['owner_phone'] ?? ''); ?>" inputmode="tel" autocomplete="tel" maxlength="25" <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-full kp-register-field">
                    <label class="form-label">Email Pemilik</label>
                    <input type="email" class="form-control" name="owner_email" value="<?php echo e($registrationOld['owner_email'] ?? ''); ?>" autocomplete="email" <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-full kp-register-section">Akun Admin Toko</div>

                <div class="kp-register-field">
                    <label class="form-label">Nama Admin <span class="kp-register-required">*</span></label>
                    <input type="text" class="form-control" name="admin_name" value="<?php echo e($registrationOld['admin_name'] ?? ''); ?>" autocomplete="name" required <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-field">
                    <label class="form-label">Username Admin <span class="kp-register-required">*</span></label>
                    <input type="text" class="form-control" name="admin_username" value="<?php echo e($registrationOld['admin_username'] ?? ''); ?>" autocomplete="username" pattern="[A-Za-z0-9._-]{3,50}" minlength="3" maxlength="50" required <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                    <div class="kp-register-help">3-50 karakter. Gunakan huruf, angka, titik, garis bawah, atau strip.</div>
                </div>

                <div class="kp-register-field">
                    <label class="form-label">Password <span class="kp-register-required">*</span></label>
                    <input type="password" class="form-control" name="password" autocomplete="new-password" minlength="8" required <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-field">
                    <label class="form-label">Konfirmasi Password <span class="kp-register-required">*</span></label>
                    <input type="password" class="form-control" name="password_confirmation" autocomplete="new-password" minlength="8" required <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                </div>

                <div class="kp-register-full kp-register-actions">
                    <a class="btn kp-btn-ghost" href="<?php echo e(base_url('login.php')); ?>">
                        Batal
                    </a>
                    <button class="btn kp-btn-primary" type="submit" <?php echo !empty($registrationBlocked) ? 'disabled' : ''; ?>>
                        <span class="material-icons-outlined">send</span>
                        <?php echo !empty($registrationBlocked) ? 'Maintenance Aktif' : 'Kirim Pengajuan'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../app/views/layouts/footer.php';
?>
