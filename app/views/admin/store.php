<?php
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/nav.php';

$storeCode = trim((string) ($storeInfo['store_code'] ?? ''));
$storeName = trim((string) ($storeInfo['store_name'] ?? ''));
$storeTagline = trim((string) ($storeInfo['store_tagline'] ?? ''));
$storeAddress = trim((string) ($storeInfo['store_address'] ?? ''));
$storeCity = trim((string) ($storeInfo['store_city'] ?? ''));
$storeProvince = trim((string) ($storeInfo['store_province'] ?? ''));
$postalCode = trim((string) ($storeInfo['postal_code'] ?? ''));
$storePhone = trim((string) ($storeInfo['store_phone'] ?? ''));
$storeWhatsapp = trim((string) ($storeInfo['store_whatsapp'] ?? ''));
$storeEmail = trim((string) ($storeInfo['store_email'] ?? ''));
$storeInstagram = trim((string) ($storeInfo['store_instagram'] ?? ''));
$businessHours = trim((string) ($storeInfo['business_hours'] ?? ''));
$googleMapsUrl = trim((string) ($storeInfo['google_maps_url'] ?? ''));
$receiptFooter = trim((string) ($storeInfo['receipt_footer'] ?? ''));
$completionPercent = (int) ($profileCompletion['percent'] ?? 0);
$completionDone = (int) ($profileCompletion['completed'] ?? 0);
$completionTotal = (int) ($profileCompletion['total'] ?? 0);
$completionChecks = $profileCompletion['checks'] ?? [];
$statusLabel = (string) ($storeStatusMeta['label'] ?? 'Profil internal');
$statusTone = (string) ($storeStatusMeta['tone'] ?? 'secondary');
$statusBadge = 'badge-neutral';
if ($statusTone === 'success') {
    $statusBadge = 'badge-success';
} elseif ($statusTone === 'warning') {
    $statusBadge = 'badge-warning';
} elseif ($statusTone === 'danger') {
    $statusBadge = 'badge-danger';
}
$previewAddress = $storeFullAddress !== '' ? $storeFullAddress : 'Alamat outlet belum dilengkapi.';
$previewHours = $businessHours !== '' ? $businessHours : 'Tambahkan jam operasional outlet.';
$previewPrimaryContact = $storePhone !== '' ? 'No. Telp ' . $storePhone : ($storeWhatsapp !== '' ? 'WA ' . $storeWhatsapp : 'No. Telp belum dilengkapi');
$completionLabels = [
    'identitas' => 'Nama outlet dan info singkat',
    'alamat' => 'Alamat outlet lengkap',
    'kontak' => 'Nomor telepon atau WhatsApp',
    'email' => 'Email bisnis',
    'operasional' => 'Jam operasional',
    'kanal_digital' => 'Instagram atau Google Maps',
    'footer' => 'Footer struk siap pakai',
];
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error mb-6"><?php foreach ($errors as $error): ?><div><?php echo e($error); ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success mb-6"><?php echo e($success); ?></div>
<?php endif; ?>

<div class="store-hero card mb-6">
    <div class="store-hero-grid">
        <div>
            <div class="store-hero-pill">
                <i data-lucide="sparkles" class="w-4 h-4"></i>
                Store Identity Studio
            </div>
            <h2 class="text-2xl font-bold text-slate-900 mb-2">Informasi Toko</h2>
            <p class="text-sm text-muted mb-0">Lengkapi identitas outlet agar struk, PDF laporan, dan area operasional terasa rapi, terpercaya, dan siap dipakai live.</p>

            <div class="store-hero-tags mt-4">
                <span class="badge <?php echo e($statusBadge); ?> gap-1.5 px-3 py-2">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    <?php echo e($statusLabel); ?>
                </span>
                <span class="badge badge-neutral gap-1.5 px-3 py-2">
                    <i data-lucide="clock-3" class="w-4 h-4"></i>
                    Update: <?php echo e($updatedAtLabel); ?>
                </span>
                <span class="badge badge-neutral gap-1.5 px-3 py-2">
                    <i data-lucide="badge-info" class="w-4 h-4"></i>
                    <?php echo e($storeCode !== '' ? $storeCode : 'Kode outlet belum dibuat'); ?>
                </span>
            </div>
        </div>

        <div class="store-hero-progress">
            <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Kelengkapan Profil</div>
            <div class="store-hero-progress-value"><?php echo e((string) $completionPercent); ?>%</div>
            <div class="progress-bar mb-3">
                <div class="progress-fill" style="width: <?php echo e((string) $completionPercent); ?>%;"></div>
            </div>
            <div class="text-sm text-muted"><?php echo e((string) $completionDone); ?> dari <?php echo e((string) $completionTotal); ?> elemen outlet profesional sudah terisi.</div>
        </div>
    </div>
</div>

<?php if ($looksLikeDemoProfile): ?>
<div class="alert alert-warning mb-6">
    <div class="font-semibold mb-1">Profil ini masih terlihat seperti data awal sistem.</div>
    <div class="text-sm">Ganti nama outlet, alamat, email, dan kanal bisnis agar tampilan admin, struk, dan laporan terasa profesional saat dipakai live.</div>
</div>
<?php endif; ?>

<div class="kpi-grid mb-6">
    <div class="card p-5 border-t-4 border-t-primary">
        <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Kode Outlet</div>
        <div class="text-xl font-bold text-slate-900 leading-tight mb-2"><?php echo e($storeCode !== '' ? $storeCode : 'Belum dibuat'); ?></div>
        <div class="text-xs text-muted">Kode outlet berguna untuk identitas internal dan koordinasi operasional.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Status Toko</div>
        <div class="text-xl font-bold text-slate-900 leading-tight mb-2">
            <span class="badge <?php echo e($statusBadge); ?> gap-1.5 px-3 py-1.5 text-sm">
                <i data-lucide="store" class="w-4 h-4"></i> <?php echo e($statusLabel); ?>
            </span>
        </div>
        <div class="text-xs text-muted">Status membantu memastikan outlet sudah siap digunakan secara operasional.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Kelengkapan Profil</div>
        <div class="text-xl font-bold text-slate-900 leading-tight mb-2"><?php echo e((string) $completionPercent); ?>%</div>
        <div class="text-xs text-muted"><?php echo e((string) $completionDone); ?> dari <?php echo e((string) $completionTotal); ?> elemen profesional sudah terisi.</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Dipakai di Sistem</div>
        <div class="text-xs text-muted mt-2">Data ini dipakai di struk checkout, PDF laporan, dan blok identitas outlet. Header brand tetap KASPINDO.</div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6">
            <div class="mb-6">
                <h3 class="text-xl font-bold text-slate-900">Profil Outlet</h3>
                <p class="text-sm text-muted mt-1"><?php echo $isAdmin ? 'Isi data inti bisnis Anda dengan bahasa yang rapi, singkat, dan meyakinkan pelanggan.' : 'Mode lihat saja. Perubahan profil outlet hanya dapat dilakukan admin.'; ?></p>
            </div>

            <form method="POST" novalidate>
                <?php echo csrf_field(); ?>
                <?php if (!$isAdmin): ?><fieldset disabled><?php endif; ?>

                <div class="mb-8">
                    <h4 class="text-lg font-bold text-slate-900 mb-1">Identitas Utama</h4>
                    <p class="text-sm text-muted mb-4">Bagian ini membentuk kesan pertama outlet Anda saat muncul di dokumen dan tampilan operasional.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="store_code">
                                <span>Kode outlet</span>
                                <span class="text-xs font-normal text-muted">Read only</span>
                            </label>
                            <input type="text" class="form-input bg-slate-50" id="store_code" value="<?php echo e($storeCode !== '' ? $storeCode : 'Akan mengikuti data toko'); ?>" readonly>
                            <div class="text-xs text-muted mt-1">Kode outlet berasal dari data toko aktif dan tidak diubah dari halaman ini.</div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="business_hours">
                                <span>Jam operasional</span>
                                <span class="text-xs font-normal text-muted">Opsional</span>
                            </label>
                            <input type="text" class="form-input" id="business_hours" name="business_hours" value="<?php echo e($businessHours); ?>" placeholder="Contoh: Senin - Minggu, 08.00 - 22.00">
                            <div class="text-xs text-muted mt-1">Cantumkan jam buka dalam format singkat agar mudah dibaca pelanggan dan tim.</div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="store_name">
                                <span>Nama toko / outlet</span>
                                <span class="text-xs font-normal text-muted">Wajib</span>
                            </label>
                            <input type="text" class="form-input" id="store_name" name="store_name" value="<?php echo e($storeName); ?>" placeholder="Contoh: Kaspindo Sarolangun" maxlength="100" required>
                            <div class="text-xs text-muted mt-1">Gunakan nama outlet yang benar-benar dipakai di operasional dan komunikasi pelanggan.</div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="store_tagline">
                                <span>Info singkat toko</span>
                                <span class="text-xs font-normal text-muted">Wajib tampil rapi</span>
                            </label>
                            <input type="text" class="form-input" id="store_tagline" name="store_tagline" value="<?php echo e($storeTagline); ?>" placeholder="Contoh: Kopi, snack, dan minuman harian" maxlength="180">
                            <div class="text-xs text-muted mt-1">Buat ringkas, jelas, dan profesional. Hindari kata seperti dummy, percobaan, atau test.</div>
                        </div>

                        <div class="form-group mb-0 md:col-span-2">
                            <label class="form-label flex justify-between" for="store_address">
                                <span>Alamat outlet</span>
                                <span class="text-xs font-normal text-muted">Wajib</span>
                            </label>
                            <textarea class="form-input min-h-[100px]" id="store_address" name="store_address" rows="3" placeholder="Tulis alamat jalan, nomor, area, atau patokan utama outlet." required><?php echo e($storeAddress); ?></textarea>
                            <div class="text-xs text-muted mt-1">Alamat ini dipakai pada identitas outlet di struk dan beberapa dokumen PDF.</div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="store_city">
                                <span>Kota / kabupaten</span>
                                <span class="text-xs font-normal text-muted">Opsional</span>
                            </label>
                            <input type="text" class="form-input" id="store_city" name="store_city" value="<?php echo e($storeCity); ?>" placeholder="Contoh: Sarolangun" maxlength="100">
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="store_province">
                                <span>Provinsi</span>
                                <span class="text-xs font-normal text-muted">Opsional</span>
                            </label>
                            <input type="text" class="form-input" id="store_province" name="store_province" value="<?php echo e($storeProvince); ?>" placeholder="Contoh: Jambi" maxlength="100">
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="postal_code">
                                <span>Kode pos</span>
                                <span class="text-xs font-normal text-muted">Opsional</span>
                            </label>
                            <input type="text" class="form-input" id="postal_code" name="postal_code" value="<?php echo e($postalCode); ?>" placeholder="Contoh: 37481" maxlength="20">
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="google_maps_url">
                                <span>Link Google Maps</span>
                                <span class="text-xs font-normal text-muted">Opsional</span>
                            </label>
                            <input type="url" class="form-input" id="google_maps_url" name="google_maps_url" value="<?php echo e($googleMapsUrl); ?>" placeholder="https://maps.app.goo.gl/...">
                            <div class="text-xs text-muted mt-1">Gunakan link yang benar-benar mengarah ke lokasi outlet agar mudah dibagikan ke pelanggan.</div>
                        </div>
                    </div>
                </div>

                <hr class="border-border my-8">

                <div class="mb-8">
                    <h4 class="text-lg font-bold text-slate-900 mb-1">Kontak Bisnis</h4>
                    <p class="text-sm text-muted mb-4">Isi kanal komunikasi utama yang layak tampil ke pelanggan dan tim operasional.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="store_phone">
                                <span>Nomor telepon toko</span>
                                <span class="text-xs font-normal text-muted">Opsional</span>
                            </label>
                            <input type="tel" class="form-input" id="store_phone" name="store_phone" value="<?php echo e($storePhone); ?>" placeholder="Contoh: 0745-123456 atau 0812xxxx" inputmode="tel" autocomplete="tel" maxlength="25">
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="store_whatsapp">
                                <span>WhatsApp bisnis</span>
                                <span class="text-xs font-normal text-muted">Opsional</span>
                            </label>
                            <input type="tel" class="form-input" id="store_whatsapp" name="store_whatsapp" value="<?php echo e($storeWhatsapp); ?>" placeholder="Contoh: 0812xxxx atau +62812xxxx" inputmode="tel" maxlength="25">
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="store_email">
                                <span>Email bisnis</span>
                                <span class="text-xs font-normal text-muted">Opsional</span>
                            </label>
                            <input type="email" class="form-input" id="store_email" name="store_email" value="<?php echo e($storeEmail); ?>" placeholder="Contoh: outlet@domainanda.com" autocomplete="email" maxlength="120">
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label flex justify-between" for="store_instagram">
                                <span>Instagram / social handle</span>
                                <span class="text-xs font-normal text-muted">Opsional</span>
                            </label>
                            <input type="text" class="form-input" id="store_instagram" name="store_instagram" value="<?php echo e($storeInstagram); ?>" placeholder="@namaoutlet atau https://instagram.com/namaoutlet" maxlength="80">
                            <div class="text-xs text-muted mt-1">Boleh diisi handle singkat atau URL lengkap bila kanal utamanya bukan Instagram.</div>
                        </div>
                    </div>
                </div>

                <hr class="border-border my-8">

                <div class="mb-8">
                    <h4 class="text-lg font-bold text-slate-900 mb-1">Footer Struk dan Nada Brand</h4>
                    <p class="text-sm text-muted mb-4">Pastikan penutup struk terdengar sopan, profesional, dan sesuai karakter outlet Anda.</p>

                    <div class="form-group mb-0">
                        <label class="form-label flex justify-between" for="receipt_footer">
                            <span>Ucapan footer struk / PDF</span>
                            <span class="text-xs font-normal text-muted">Maksimal 160 karakter</span>
                        </label>
                        <textarea class="form-input" id="receipt_footer" name="receipt_footer" rows="2" placeholder="Contoh: Terima kasih atas kepercayaan Anda. Sampai jumpa kembali di outlet kami."><?php echo e($receiptFooter); ?></textarea>
                        <div class="text-xs text-muted mt-1">Gunakan kalimat penutup yang hangat namun tetap singkat. Hindari terlalu banyak simbol atau promosi berulang.</div>
                    </div>
                </div>

                <?php if (!$isAdmin): ?></fieldset><?php endif; ?>
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 border-t border-border mt-8">
                    <div class="text-sm text-muted"><?php echo $isAdmin ? 'Perubahan di halaman ini akan memengaruhi tampilan identitas outlet di area yang menggunakan profil toko aktif.' : 'Anda dapat memantau profil outlet tanpa mengubah data.'; ?></div>
                    <?php if ($isAdmin): ?>
                        <button class="btn btn-primary whitespace-nowrap" type="submit">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Profil Outlet
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card p-6 bg-slate-50 border border-border">
            <div class="text-xs font-bold text-muted uppercase tracking-wide mb-4">Preview Identitas</div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-border font-bold text-sm text-slate-900 mb-4 shadow-sm">
                <i data-lucide="receipt" class="w-4 h-4 text-primary"></i> Contoh tampilan struk
            </div>

            <div class="bg-white border border-border rounded-xl p-5 shadow-sm font-mono text-[13px] text-slate-950">
                <div class="text-center">
                    <img src="<?php echo e(base_url('assets/images/logo.jpg')); ?>" alt="KASPINDO" class="w-16 h-16 object-contain mx-auto mb-2">
                    <div class="font-sans text-xl font-medium leading-tight mt-1" id="preview-store-name"><?php echo e($storeName !== '' ? $storeName : 'Nama outlet Anda'); ?></div>
                    <div class="font-sans text-sm leading-snug mt-1" id="preview-store-address"><?php echo e($previewAddress); ?></div>
                    <div class="font-sans text-sm leading-snug mt-1" id="preview-primary-contact"><?php echo e($previewPrimaryContact); ?></div>
                    <div class="font-sans text-sm leading-snug mt-1" id="preview-store-tagline"><?php echo e($storeTagline !== '' ? $storeTagline : 'Info singkat outlet akan tampil di sini.'); ?></div>
                </div>

                <div class="border-t border-dashed border-slate-400 my-4"></div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                    <div><?php echo e(date('Y-m-d')); ?></div>
                    <div class="text-right"><?php echo e($storeCode !== '' ? $storeCode : 'KASPINDO'); ?></div>
                    <div><?php echo e(date('H:i:s')); ?></div>
                    <div class="text-right">Kasir</div>
                </div>
                <div class="mt-2">No.0-1</div>

                <div class="border-t border-dashed border-slate-400 my-4"></div>

                <div class="space-y-2">
                    <div>
                        <div class="font-bold">1. Contoh Produk</div>
                        <div class="grid grid-cols-[1fr_auto] gap-3 pl-4">
                            <span>1 x 12.000</span>
                            <span>Rp 12.000</span>
                        </div>
                    </div>
                    <div>
                        <div class="font-bold">2. Item Tambahan</div>
                        <div class="grid grid-cols-[1fr_auto] gap-3 pl-4">
                            <span>2 x 5.000</span>
                            <span>Rp 10.000</span>
                        </div>
                    </div>
                </div>

                <div class="border-t border-dashed border-slate-400 my-4"></div>

                <div>Total QTY : 3</div>
                <div class="mt-3 space-y-1">
                    <div class="flex justify-between gap-4">
                        <span>Sub Total</span>
                        <span>Rp 22.000</span>
                    </div>
                    <div class="flex justify-between gap-4 font-bold text-base">
                        <span>Total</span>
                        <span>Rp 22.000</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span>Bayar (Cash)</span>
                        <span>Rp 22.000</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span>Kembali</span>
                        <span>Rp 0</span>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <span class="inline-block font-sans text-sm font-medium" id="preview-receipt-footer">
                        <?php echo e($receiptFooter !== '' ? $receiptFooter : 'Terimakasih Telah Berbelanja'); ?>
                    </span>
                    <div class="text-sm text-slate-600 mt-3">Link Kritik dan Saran:</div>
                    <div class="text-xs text-slate-500 break-all"><?php echo e(base_url('print_receipt.php?id=0')); ?></div>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Checklist Profesional</div>
            <div class="text-base font-bold text-slate-900 mb-1">Status kelengkapan profil outlet</div>
            <p class="text-sm text-muted mb-4">Gunakan checklist ini untuk memastikan profil toko terasa meyakinkan saat dipakai live.</p>

            <div class="space-y-3">
                <?php foreach ($completionLabels as $checkKey => $checkLabel): ?>
                    <?php $isDone = !empty($completionChecks[$checkKey]); ?>
                    <div class="flex gap-3 items-center p-3 rounded-xl border border-border bg-white">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 <?php echo $isDone ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?>">
                            <i data-lucide="<?php echo $isDone ? 'check' : 'clock'; ?>" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <div class="font-bold text-sm text-slate-900"><?php echo e($checkLabel); ?></div>
                            <div class="text-xs text-muted"><?php echo $isDone ? 'Sudah siap tampil.' : 'Masih perlu dilengkapi.'; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card p-6 bg-slate-50 border border-border">
            <div class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Catatan Penggunaan</div>
            <div class="text-base font-bold text-slate-900 mb-2">Di mana data toko ini akan dipakai</div>
            <ul class="text-sm text-slate-600 space-y-2 pl-4 list-disc marker:text-slate-400">
                <li>Nama outlet, alamat, dan kontak memperkuat identitas bisnis pada struk dan PDF.</li>
                <li>Jam operasional, kanal digital, dan Google Maps membantu outlet terasa lebih siap dan terpercaya.</li>
                <li>Footer struk sebaiknya dipakai untuk ucapan singkat yang konsisten dengan nada brand outlet Anda.</li>
            </ul>
        </div>
    </div>
</div>

<script>
(() => {
    const fields = {
        storeName: document.getElementById('store_name'),
        storeTagline: document.getElementById('store_tagline'),
        storeAddress: document.getElementById('store_address'),
        storeCity: document.getElementById('store_city'),
        storeProvince: document.getElementById('store_province'),
        postalCode: document.getElementById('postal_code'),
        storePhone: document.getElementById('store_phone'),
        storeWhatsapp: document.getElementById('store_whatsapp'),
        storeEmail: document.getElementById('store_email'),
        storeInstagram: document.getElementById('store_instagram'),
        businessHours: document.getElementById('business_hours'),
        receiptFooter: document.getElementById('receipt_footer'),
    };

    const preview = {
        storeName: document.getElementById('preview-store-name'),
        storeTagline: document.getElementById('preview-store-tagline'),
        storeAddress: document.getElementById('preview-store-address'),
        primaryContact: document.getElementById('preview-primary-contact'),
        receiptFooter: document.getElementById('preview-receipt-footer'),
    };

    const composeAddress = () => {
        const parts = [];
        const address = fields.storeAddress.value.trim();
        const city = fields.storeCity.value.trim();
        const province = fields.storeProvince.value.trim();
        const postalCode = fields.postalCode.value.trim();

        if (address !== '') parts.push(address);
        const region = [city, province].filter((item) => item !== '').join(', ');
        if (region !== '') parts.push(region);
        if (postalCode !== '') parts.push(postalCode);

        return parts.join(', ');
    };

    const render = () => {
        const address = composeAddress();
        preview.storeName.textContent = fields.storeName.value.trim() || 'Nama outlet Anda';
        preview.storeTagline.textContent = fields.storeTagline.value.trim() || 'Info singkat outlet akan tampil di sini.';
        preview.storeAddress.textContent = address || 'Alamat outlet belum dilengkapi.';
        preview.primaryContact.textContent = fields.storePhone.value.trim()
            ? `No. Telp ${fields.storePhone.value.trim()}`
            : (fields.storeWhatsapp.value.trim() ? `WA ${fields.storeWhatsapp.value.trim()}` : 'No. Telp belum dilengkapi');
        preview.receiptFooter.textContent = fields.receiptFooter.value.trim() || 'Terimakasih Telah Berbelanja';
    };

    Object.values(fields).forEach((field) => {
        field.addEventListener('input', render);
        field.addEventListener('change', render);
    });

    render();
})();
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
