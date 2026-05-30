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
    <div class="alert alert-success mb-6"><?php echo e($success); ?></div>
<?php endif; ?>

<div class="card max-w-6xl mx-auto">
    <div class="p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-1">Edit Profil Toko</h2>
                <p class="text-sm text-muted mb-0">Lengkapi data toko, pemilik, kontak publik, dan informasi struk dari panel superadmin.</p>
            </div>
            <a class="btn btn-secondary" href="<?php echo e(base_url('superadmin_store_requests.php?focus=stores')); ?>">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Kembali
            </a>
        </div>

        <?php if ($store): ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-1">Kode Toko</div>
                    <div class="font-bold text-slate-900"><?php echo e((string) ($store['store_code'] ?? '-')); ?></div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-1">Status Approval</div>
                    <div class="font-bold text-slate-900"><?php echo e((string) ($store['status'] ?? '-')); ?></div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-1">Status Operasional</div>
                    <div class="font-bold text-slate-900"><?php echo e((string) ($store['operational_status'] ?? 'active')); ?></div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-[10px] font-bold text-muted uppercase tracking-wide mb-1">Admin Login Awal</div>
                    <div class="font-bold text-slate-900">@<?php echo e((string) ($store['admin_username'] ?? '-')); ?></div>
                </div>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <section class="rounded-2xl border border-border p-5">
                        <h3 class="font-bold text-slate-900 mb-4">Data Toko</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group mb-0 md:col-span-2">
                                <label class="form-label">Nama Toko</label>
                                <input type="text" name="store_name" class="form-input w-full" value="<?php echo e((string) ($formStore['store_name'] ?? '')); ?>" required>
                            </div>
                            <div class="form-group mb-0 md:col-span-2">
                                <label class="form-label">Tagline</label>
                                <input type="text" name="store_tagline" class="form-input w-full" value="<?php echo e((string) ($formStore['store_tagline'] ?? '')); ?>">
                            </div>
                            <div class="form-group mb-0 md:col-span-2">
                                <label class="form-label">Alamat</label>
                                <textarea name="store_address" class="form-input w-full min-h-[96px]"><?php echo e((string) ($formStore['store_address'] ?? '')); ?></textarea>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Kota</label>
                                <input type="text" name="store_city" class="form-input w-full" value="<?php echo e((string) ($formStore['store_city'] ?? '')); ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Provinsi</label>
                                <input type="text" name="store_province" class="form-input w-full" value="<?php echo e((string) ($formStore['store_province'] ?? '')); ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Kode Pos</label>
                                <input type="text" name="postal_code" class="form-input w-full" value="<?php echo e((string) ($formStore['postal_code'] ?? '')); ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Jam Operasional</label>
                                <input type="text" name="business_hours" class="form-input w-full" value="<?php echo e((string) ($formStore['business_hours'] ?? '')); ?>" placeholder="08.00 - 22.00">
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-border p-5">
                        <h3 class="font-bold text-slate-900 mb-4">Kontak Publik</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group mb-0">
                                <label class="form-label">Telepon</label>
                                <input type="text" name="store_phone" class="form-input w-full" value="<?php echo e((string) ($formStore['store_phone'] ?? '')); ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">WhatsApp</label>
                                <input type="text" name="store_whatsapp" class="form-input w-full" value="<?php echo e((string) ($formStore['store_whatsapp'] ?? '')); ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Email</label>
                                <input type="email" name="store_email" class="form-input w-full" value="<?php echo e((string) ($formStore['store_email'] ?? '')); ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Instagram</label>
                                <input type="text" name="store_instagram" class="form-input w-full" value="<?php echo e((string) ($formStore['store_instagram'] ?? '')); ?>" placeholder="@kaspindo">
                            </div>
                            <div class="form-group mb-0 md:col-span-2">
                                <label class="form-label">Google Maps URL</label>
                                <input type="url" name="google_maps_url" class="form-input w-full" value="<?php echo e((string) ($formStore['google_maps_url'] ?? '')); ?>">
                            </div>
                            <div class="form-group mb-0 md:col-span-2">
                                <label class="form-label">Footer Struk/PDF</label>
                                <input type="text" name="receipt_footer" class="form-input w-full" value="<?php echo e((string) ($formStore['receipt_footer'] ?? '')); ?>">
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-border p-5">
                        <h3 class="font-bold text-slate-900 mb-4">Pemilik</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group mb-0 md:col-span-2">
                                <label class="form-label">Nama Pemilik</label>
                                <input type="text" name="owner_name" class="form-input w-full" value="<?php echo e((string) ($formStore['owner_name'] ?? '')); ?>" required>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Email Pemilik</label>
                                <input type="email" name="owner_email" class="form-input w-full" value="<?php echo e((string) ($formStore['owner_email'] ?? '')); ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Telepon Pemilik</label>
                                <input type="text" name="owner_phone" class="form-input w-full" value="<?php echo e((string) ($formStore['owner_phone'] ?? '')); ?>">
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-border p-5">
                        <h3 class="font-bold text-slate-900 mb-4">Admin Toko</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group mb-0">
                                <label class="form-label">Nama Admin</label>
                                <input type="text" name="admin_name" class="form-input w-full" value="<?php echo e((string) ($formStore['admin_name'] ?? '')); ?>" required>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Username Login</label>
                                <input type="text" class="form-input w-full bg-slate-50" value="<?php echo e((string) ($store['admin_username'] ?? '')); ?>" readonly>
                            </div>
                            <div class="md:col-span-2 rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm text-slate-600">
                                Username dan password akun aktif dikelola dari menu User Tenant agar sinkron dengan tabel login.
                            </div>
                        </div>
                    </section>
                </div>

                <div class="mt-8 flex flex-wrap gap-3 pt-6 border-t border-border">
                    <button class="btn btn-primary" type="submit">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Simpan Profil Toko
                    </button>
                    <a class="btn btn-secondary" href="<?php echo e(base_url('superadmin_store_requests.php?focus=stores')); ?>">Batal</a>
                </div>
            </form>
        <?php else: ?>
            <p class="text-muted p-4 text-center bg-slate-50 rounded-xl">Toko tidak ditemukan.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
