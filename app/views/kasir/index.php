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
        <?php if (!empty($lastTransactionId)): ?>
            <div class="mt-2">
                <a class="btn kp-btn-ghost btn-sm" target="_blank" href="<?php echo e(base_url('print_receipt.php?id=' . (int) $lastTransactionId)); ?>">
                    <span class="material-icons-outlined">print</span>
                    Print Struk
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="kp-page-header">
    <div>
        <h2 class="kp-page-title">POS Kasir</h2>
        <p class="kp-page-subtitle">Pilih produk, atur jumlah, lalu bayar.</p>
    </div>
</div>

<?php if (empty($shiftInfo)): ?>
    <div class="alert alert-warning d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
        <div>
            <div class="fw-semibold">Shift belum dibuka.</div>
            <div class="small">Buka shift terlebih dahulu agar transaksi bisa diproses.</div>
        </div>
        <a class="btn kp-btn-primary btn-sm" href="<?php echo e(base_url('shift.php')); ?>">
            <span class="material-icons-outlined">login</span>
            Buka Shift
        </a>
    </div>
<?php else: ?>
    <div class="kp-card-flat p-3 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <div class="fw-semibold">Shift Aktif</div>
                <div class="kp-muted small">ID: <?php echo e($shiftInfo['shift_id'] ?? '-'); ?> &middot; Mulai: <?php echo e($shiftInfo['opened_at'] ?? '-'); ?></div>
            </div>
            <div class="text-md-end">
                <div class="kp-muted small">Estimasi Kas Drawer</div>
                <div class="fw-semibold"><?php echo e(format_rupiah((float) ($shiftBalance ?? 0))); ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>



<div class="kp-card-flat p-3 mb-3">
    <div class="input-group">
        <span class="input-group-text bg-white border-0">
            <span class="material-icons-outlined">search</span>
        </span>
        <input type="text" id="productSearch" class="form-control border-0" placeholder="Cari nama / scan SKU lalu Enter..." data-testid="product-search">
    </div>
</div>

<div class="kp-card-flat p-3 mb-3 d-none d-md-flex justify-content-between align-items-center">
    <div>
        <div class="kp-muted small">Total</div>
        <div class="fw-semibold" id="desktopTotal">Rp 0</div>
    </div>
    <button class="btn kp-btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartPanel">
        <span class="material-icons-outlined">shopping_cart</span>
        Ringkasan
    </button>
</div>

<div class="d-flex flex-wrap gap-2 mb-4" id="categoryChips">
    <button type="button" class="kp-chip active" data-category="all">Semua</button>
    <?php foreach ($categories as $category): ?>
        <button type="button" class="kp-chip" data-category="<?php echo (int) $category['id']; ?>">
            <?php echo e($category['name']); ?>
        </button>
    <?php endforeach; ?>
</div>

<form method="POST" enctype="multipart/form-data" id="posForm">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="checkout_token" value="<?php echo e($checkoutToken); ?>">
    <input type="hidden" name="item_discounts" id="item_discounts" value="">
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4" id="productGrid">
    <?php foreach ($products as $product): ?>
        <?php
            $invItem = $inventoryItems[(int) $product['id']] ?? null;
            $stockTracked = is_array($invItem) && array_key_exists('stock', $invItem) && $invItem['stock'] !== null;
            $stockValue = $stockTracked ? (int) ($invItem['stock'] ?? 0) : null;
            $skuValue = strtolower(trim((string) ($product['sku'] ?? '')));
        ?>
        <div
            class="kp-card kp-product-card p-4 flex flex-col group cursor-pointer transition-all duration-300 hover:shadow-lg"
            data-testid="product-card"
            data-name="<?php echo e(strtolower($product['name'])); ?>"
            data-category="<?php echo e((string) ($product['category_id'] ?? 'all')); ?>"
            data-sku="<?php echo e($skuValue); ?>"
            data-id="<?php echo (int) $product['id']; ?>"
            data-stock="<?php echo $stockTracked ? (int) $stockValue : ''; ?>"
            data-stock-tracked="<?php echo $stockTracked ? '1' : '0'; ?>"
        >
            <div class="mb-3">
                <div class="w-full aspect-square rounded-xl bg-slate-50 flex items-center justify-center text-slate-300 group-hover:bg-emerald-50 group-hover:text-emerald-500 transition-colors">
                    <span class="material-icons-outlined text-4xl">local_cafe</span>
                </div>
            </div>

            <div class="flex-1">
                <h3 class="kp-product-name text-sm font-bold text-slate-900 truncate mb-1"><?php echo e($product['name']); ?></h3>
                <p class="text-emerald-600 font-bold text-sm"><?php echo e(format_rupiah((float) $product['price'])); ?></p>
                <?php if ($stockTracked): ?>
                    <p class="text-[10px] font-bold uppercase tracking-wider <?php echo $stockValue <= 0 ? 'text-red-500' : 'text-slate-400'; ?> mt-1">
                        Stock: <?php echo e($stockValue); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="flex items-center justify-between mt-4 bg-slate-50 rounded-lg p-1">
                <button type="button" class="w-8 h-8 rounded-md bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-red-50 hover:text-red-500 hover:border-red-200 transition-all qty-btn" data-testid="qty-minus" data-action="minus" data-id="<?php echo (int) $product['id']; ?>">
                    <span class="material-icons-outlined text-sm">remove</span>
                </button>
                <span class="text-sm font-bold text-slate-900" id="qty-view-<?php echo (int) $product['id']; ?>">0</span>
                <button type="button" class="w-8 h-8 rounded-md bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-emerald-50 hover:text-emerald-500 hover:border-emerald-200 transition-all qty-btn" data-testid="qty-plus" data-action="plus" data-id="<?php echo (int) $product['id']; ?>" <?php echo ($stockTracked && $stockValue <= 0) ? 'disabled' : ''; ?>>
                    <span class="material-icons-outlined text-sm">add</span>
                </button>
            </div>

            <input type="hidden" name="items[<?php echo (int) $product['id']; ?>]" id="qty-input-<?php echo (int) $product['id']; ?>" value="0" data-price="<?php echo e((string) $product['price']); ?>">
        </div>
    <?php endforeach; ?>
    </div>

    <div class="offcanvas offcanvas-bottom kp-offcanvas" tabindex="-1" id="cartPanel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Ringkasan Transaksi</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="kp-offcanvas-scroll">
                <div id="cartList" class="kp-cart-list"></div>

                <div class="kp-summary-panel mt-3">
                    <div class="p-6 rounded-2xl bg-slate-900 text-white shadow-xl shadow-slate-200">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-slate-400 text-sm font-medium">Total Tagihan</span>
                            <span class="px-2 py-1 rounded bg-slate-800 text-[10px] font-bold uppercase tracking-widest text-slate-400">IDR</span>
                        </div>
                        <div class="text-3xl font-bold mb-4" id="cartTotal">Rp 0</div>
                        <div class="flex justify-between items-center pt-4 border-t border-slate-800">
                            <span class="text-slate-400 text-xs">Kembalian</span>
                            <span class="text-emerald-400 font-bold" id="cashChange">Rp 0</span>
                        </div>
                    </div>

                    <div class="kp-card-flat p-3">
                        <div class="fw-semibold mb-2">Ringkasan Harga</div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Subtotal</span>
                            <span id="subtotalValue">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Diskon Item</span>
                            <span id="itemDiscountValue">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Diskon Order</span>
                            <span id="orderDiscountValue">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Voucher</span>
                            <span id="voucherDiscountValue">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Pajak</span>
                            <span id="taxValue">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Service</span>
                            <span id="serviceValue">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span>Pembulatan</span>
                            <span id="roundingValue">Rp 0</span>
                        </div>
                    </div>

                    <div class="kp-card-flat p-3">
                        <div class="fw-semibold mb-2">Diskon, Voucher, Pajak</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Diskon Order</label>
                                <select class="form-select" id="order_discount_type" name="order_discount_type">
                                    <option value="none">Tidak ada</option>
                                    <option value="percent">Persen (%)</option>
                                    <option value="amount">Nominal (Rp)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nilai Diskon</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="order_discount_value" name="order_discount_value" value="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Kode Voucher</label>
                                <input type="text" class="form-control" id="voucher_code" name="voucher_code" placeholder="Contoh: HEMAT10">
                                <div class="kp-muted small mt-1" id="voucherHint">Opsional.</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Pajak (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="tax_percent" name="tax_percent" value="<?php echo e((string) ($promoDefaults['tax_percent'] ?? 0)); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Service (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="service_percent" name="service_percent" value="<?php echo e((string) ($promoDefaults['service_percent'] ?? 0)); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Pembulatan</label>
                                <select class="form-select" id="rounding_mode" name="rounding_mode">
                                    <?php $roundingMode = $promoDefaults['rounding_mode'] ?? 'none'; ?>
                                    <option value="none" <?php echo $roundingMode === 'none' ? 'selected' : ''; ?>>Tidak ada</option>
                                    <option value="nearest" <?php echo $roundingMode === 'nearest' ? 'selected' : ''; ?>>Terdekat</option>
                                    <option value="up" <?php echo $roundingMode === 'up' ? 'selected' : ''; ?>>Ke atas</option>
                                    <option value="down" <?php echo $roundingMode === 'down' ? 'selected' : ''; ?>>Ke bawah</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Kelipatan</label>
                                <input type="number" min="1" step="1" class="form-control" id="rounding_unit" name="rounding_unit" value="<?php echo e((string) ($promoDefaults['rounding_unit'] ?? 100)); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="kp-card-flat p-3">
                        <div class="fw-semibold mb-2">Metode Pembayaran</div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn kp-btn-ghost w-50 payment-btn" data-method="cash">
                                <span class="material-icons-outlined">payments</span>
                                Cash
                            </button>
                            <button type="button" class="btn kp-btn-ghost w-50 payment-btn" data-method="qris">
                                <span class="material-icons-outlined">qr_code_2</span>
                                QRIS
                            </button>
                        </div>
                        <input type="hidden" name="payment_method" id="payment_method" value="<?php echo e($paymentMethod); ?>">
                    </div>

                    <div class="kp-card-flat p-3">
                        <label class="form-label">Member (Opsional)</label>
                        <select class="form-select" id="customer_id" name="customer_id">
                            <option value="0">Umum / Non-member</option>
                            <?php foreach ($customers as $customer): ?>
                                <?php $customerId = (int) ($customer['id'] ?? 0); ?>
                                <option
                                    value="<?php echo $customerId; ?>"
                                    data-points="<?php echo (int) ($customer['points'] ?? 0); ?>"
                                    data-phone="<?php echo e((string) ($customer['phone'] ?? '')); ?>"
                                    <?php echo $selectedCustomerId === $customerId ? 'selected' : ''; ?>
                                >
                                    <?php echo e($customer['name'] ?? '-'); ?><?php echo !empty($customer['phone']) ? ' (' . e($customer['phone']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="kp-muted small mt-1" id="customerPointHint">Belum pilih member.</div>
                    </div>

                    <div class="kp-card-flat p-3" id="cashSection">
                        <label class="form-label">Uang Tunai</label>
                        <input type="number" step="0.01" min="0" name="cash_received" id="cash_received" class="form-control" value="<?php echo e((string) $cashReceived); ?>" data-testid="cash-received">
                    </div>

                    <div class="kp-card-flat p-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="note" id="note" rows="2" class="form-control" placeholder="Contoh: tanpa gula, extra es."></textarea>
                        <div class="kp-muted small mt-1">Opsional, akan tercetak di struk.</div>
                    </div>

                    <div class="kp-card-flat p-3" id="qrisSection" style="display: none;">
                        <label class="form-label">Upload Bukti QRIS</label>
                        <div class="border border-dashed rounded-4 p-3 text-center">
                            <div class="kp-muted small mb-2">Format JPG/PNG max 2MB. Ambil foto bukti pembayaran.</div>
                            <input type="file" name="qris_proof" id="qris_proof" class="form-control" accept=".jpg,.jpeg,.png">
                            <img id="qrisPreview" class="img-fluid rounded-4 mt-3 d-none" alt="Preview QRIS">
                        </div>
                    </div>

                    <div class="kp-card-flat p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-semibold">Hold Transaksi</div>
                            <button class="btn kp-btn-ghost btn-sm" type="button" id="holdSaveBtn">
                                <span class="material-icons-outlined">pause_circle</span>
                                Simpan Hold
                            </button>
                        </div>
                        <div id="holdList" class="kp-hold-list"></div>
                    </div>
                </div>
            </div>

            <div class="kp-offcanvas-footer">
                <button class="btn kp-btn-primary w-100" type="button" id="payButton" data-testid="pay-button">
                    <span class="material-icons-outlined">paid</span>
                    Bayar Sekarang
                </button>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="confirmPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="kp-card-flat p-3">
                    <div class="kp-muted small">Total</div>
                    <div class="fs-3 fw-semibold" id="confirmTotal">Rp 0</div>
                    <div class="kp-muted small mt-2">Metode</div>
                    <div class="fw-semibold" id="confirmMethod">-</div>
                    <div class="kp-muted small mt-2">Member</div>
                    <div class="fw-semibold" id="confirmCustomer">Umum</div>
                </div>
                <div class="kp-muted small mt-3">Pastikan transaksi sudah benar sebelum dibayar.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn kp-btn-ghost" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn kp-btn-primary" id="confirmPayBtn" data-testid="confirm-pay-button">
                    <span class="material-icons-outlined">check_circle</span>
                    Konfirmasi Bayar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="itemDiscountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Diskon Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="kp-muted small mb-2" id="discountItemLabel">Item</div>
                <div class="mb-2">
                    <label class="form-label">Tipe Diskon</label>
                    <select class="form-select" id="discountType">
                        <option value="none">Tidak ada</option>
                        <option value="percent">Persen (%)</option>
                        <option value="amount">Nominal (Rp)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Nilai Diskon</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="discountValue" value="0">
                    <div class="kp-muted small mt-1">Diskon berlaku untuk total item ini.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn kp-btn-ghost" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn kp-btn-primary" id="discountApplyBtn">
                    <span class="material-icons-outlined">check_circle</span>
                    Terapkan
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="voidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Void Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="kp-muted small mb-2" id="voidItemLabel">Item</div>
                <label class="form-label">Alasan Void</label>
                <input type="text" id="voidReason" class="form-control" placeholder="Contoh: salah input qty">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn kp-btn-ghost" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn kp-btn-primary" id="voidConfirmBtn">
                    <span class="material-icons-outlined">delete</span>
                    Hapus Item
                </button>
            </div>
        </div>
    </div>
</div>

<div id="toastContainer" class="kp-toast-container"></div>

<div class="kp-cart-bar d-md-none">
    <div class="container">
        <div class="kp-card-flat p-3 d-flex justify-content-between align-items-center">
            <div>
                <div class="kp-muted small">Total</div>
                <div class="fw-semibold" id="stickyTotal">Rp 0</div>
            </div>
            <button class="btn kp-btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartPanel">
                <span class="material-icons-outlined">shopping_cart</span>
                Bayar
            </button>
        </div>
    </div>
</div>

<script>
    const holdSeed = <?php echo json_encode($holdCarts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const shouldClearCart = <?php echo !empty($success) ? 'true' : 'false'; ?>;
    const promoDefaults = <?php echo json_encode($promoDefaults, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const promoVouchers = <?php echo json_encode($promoVouchers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const shiftOpen = <?php echo !empty($shiftInfo) ? 'true' : 'false'; ?>;
    const qtyButtons = document.querySelectorAll('.qty-btn');
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const cartList = document.getElementById('cartList');
    const cartTotal = document.getElementById('cartTotal');
    const stickyTotal = document.getElementById('stickyTotal');
    const desktopTotal = document.getElementById('desktopTotal');
    const cashChange = document.getElementById('cashChange');
    const cashInput = document.getElementById('cash_received');
    const paymentButtons = document.querySelectorAll('.payment-btn');
    const paymentMethod = document.getElementById('payment_method');
    const cashSection = document.getElementById('cashSection');
    const qrisSection = document.getElementById('qrisSection');
    const qrisInput = document.getElementById('qris_proof');
    const qrisPreview = document.getElementById('qrisPreview');
    const payButton = document.getElementById('payButton');
    const searchInput = document.getElementById('productSearch');
    const categoryChips = document.querySelectorAll('#categoryChips .kp-chip');
    const productCards = document.querySelectorAll('.kp-product-card');
    const confirmModalEl = document.getElementById('confirmPaymentModal');
    const confirmTotal = document.getElementById('confirmTotal');
    const confirmMethod = document.getElementById('confirmMethod');
    const confirmCustomer = document.getElementById('confirmCustomer');
    const confirmPayBtn = document.getElementById('confirmPayBtn');
    const posForm = document.getElementById('posForm');
    const holdList = document.getElementById('holdList');
    const holdSaveBtn = document.getElementById('holdSaveBtn');
    const voidModalEl = document.getElementById('voidModal');
    const voidItemLabel = document.getElementById('voidItemLabel');
    const voidReason = document.getElementById('voidReason');
    const voidConfirmBtn = document.getElementById('voidConfirmBtn');
    const toastContainer = document.getElementById('toastContainer');
    const orderDiscountType = document.getElementById('order_discount_type');
    const orderDiscountValue = document.getElementById('order_discount_value');
    const voucherCodeInput = document.getElementById('voucher_code');
    const voucherHint = document.getElementById('voucherHint');
    const customerSelect = document.getElementById('customer_id');
    const customerPointHint = document.getElementById('customerPointHint');
    const taxPercentInput = document.getElementById('tax_percent');
    const servicePercentInput = document.getElementById('service_percent');
    const roundingModeInput = document.getElementById('rounding_mode');
    const roundingUnitInput = document.getElementById('rounding_unit');
    const itemDiscountsInput = document.getElementById('item_discounts');
    const subtotalValue = document.getElementById('subtotalValue');
    const itemDiscountValue = document.getElementById('itemDiscountValue');
    const orderDiscountValueEl = document.getElementById('orderDiscountValue');
    const voucherDiscountValue = document.getElementById('voucherDiscountValue');
    const taxValue = document.getElementById('taxValue');
    const serviceValue = document.getElementById('serviceValue');
    const roundingValue = document.getElementById('roundingValue');
    const discountModalEl = document.getElementById('itemDiscountModal');
    const discountItemLabel = document.getElementById('discountItemLabel');
    const discountTypeInput = document.getElementById('discountType');
    const discountValueInput = document.getElementById('discountValue');
    const discountApplyBtn = document.getElementById('discountApplyBtn');

    const formatRupiah = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
    const escapeHtml = (value) => String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    let currentTotal = 0;
    let activeProductId = null;
    let pendingVoid = null;
    let heldCarts = Array.isArray(holdSeed) ? holdSeed : [];
    const cartState = {};
    const itemDiscounts = {};
    let pendingDiscountProduct = null;
    const productLookupBySku = new Map();
    const productLookupByName = new Map();

    productCards.forEach((card) => {
        const productId = String(card.dataset.id || '');
        const sku = String(card.dataset.sku || '').trim().toLowerCase();
        const name = String(card.dataset.name || '').trim().toLowerCase();
        if (sku !== '') {
            productLookupBySku.set(sku, productId);
        }
        if (name !== '') {
            productLookupByName.set(name, productId);
        }
    });

    const showToast = (message, type = 'info') => {
        const toast = document.createElement('div');
        toast.className = `kp-toast kp-toast-${type}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 250);
        }, 2500);
    };

    const localStateKey = 'kp_cart_state_v2';
    const legacyKey = 'kp_cart';

    const saveCartState = () => {
        const payload = {
            items: cartState,
            item_discounts: itemDiscounts,
            order_discount_type: orderDiscountType?.value || 'none',
            order_discount_value: orderDiscountValue?.value || '0',
            voucher_code: voucherCodeInput?.value || '',
            tax_percent: taxPercentInput?.value || '0',
            service_percent: servicePercentInput?.value || '0',
            rounding_mode: roundingModeInput?.value || 'none',
            rounding_unit: roundingUnitInput?.value || '100',
            customer_id: customerSelect?.value || '0',
        };
        localStorage.setItem(localStateKey, JSON.stringify(payload));
    };

    const loadCartState = () => {
        const saved = localStorage.getItem(localStateKey);
        if (saved) {
            const parsed = JSON.parse(saved);
            if (parsed && typeof parsed === 'object') {
                const items = parsed.items || {};
                Object.keys(items).forEach((productId) => {
                    const qty = parseInt(items[productId], 10) || 0;
                    const input = document.getElementById('qty-input-' + productId);
                    const view = document.getElementById('qty-view-' + productId);
                    if (input && view) {
                        input.value = qty;
                        view.textContent = qty;
                        cartState[productId] = qty;
                    }
                });
                if (parsed.item_discounts && typeof parsed.item_discounts === 'object') {
                    Object.keys(parsed.item_discounts).forEach((productId) => {
                        itemDiscounts[productId] = parsed.item_discounts[productId];
                    });
                }
                if (orderDiscountType && parsed.order_discount_type) {
                    orderDiscountType.value = parsed.order_discount_type;
                }
                if (orderDiscountValue && parsed.order_discount_value !== undefined) {
                    orderDiscountValue.value = parsed.order_discount_value;
                }
                if (voucherCodeInput && parsed.voucher_code !== undefined) {
                    voucherCodeInput.value = parsed.voucher_code;
                }
                if (taxPercentInput && parsed.tax_percent !== undefined) {
                    taxPercentInput.value = parsed.tax_percent;
                }
                if (servicePercentInput && parsed.service_percent !== undefined) {
                    servicePercentInput.value = parsed.service_percent;
                }
                if (roundingModeInput && parsed.rounding_mode) {
                    roundingModeInput.value = parsed.rounding_mode;
                }
                if (roundingUnitInput && parsed.rounding_unit !== undefined) {
                    roundingUnitInput.value = parsed.rounding_unit;
                }
                if (customerSelect && parsed.customer_id !== undefined) {
                    customerSelect.value = String(parsed.customer_id);
                }
                return;
            }
        }
        const legacy = localStorage.getItem(legacyKey);
        if (!legacy) {
            return;
        }
        const parsed = JSON.parse(legacy);
        if (!parsed || typeof parsed !== 'object') {
            return;
        }
        Object.keys(parsed).forEach((productId) => {
            const qty = parseInt(parsed[productId], 10) || 0;
            const input = document.getElementById('qty-input-' + productId);
            const view = document.getElementById('qty-view-' + productId);
            if (input && view) {
                input.value = qty;
                view.textContent = qty;
                cartState[productId] = qty;
            }
        });
    };

    const syncStateFromInputs = () => {
        document.querySelectorAll('input[id^="qty-input-"]').forEach((input) => {
            const productId = input.id.replace('qty-input-', '');
            const qty = parseInt(input.value, 10) || 0;
            const view = document.getElementById('qty-view-' + productId);
            if (view) {
                view.textContent = qty;
            }
            if (qty > 0) {
                cartState[productId] = qty;
            } else {
                delete cartState[productId];
            }
        });
    };

    const updateCustomerHint = () => {
        if (!customerSelect || !customerPointHint) {
            return;
        }
        const selected = customerSelect.options[customerSelect.selectedIndex];
        if (!selected || customerSelect.value === '0') {
            customerPointHint.textContent = 'Belum pilih member.';
            return;
        }
        const points = parseInt(selected.dataset.points || '0', 10) || 0;
        const phone = selected.dataset.phone || '-';
        customerPointHint.textContent = `No. HP ${phone} | Poin saat ini ${new Intl.NumberFormat('id-ID').format(points)}.`;
    };

    const getProductName = (productId) => {
        const card = document.querySelector(`.kp-product-card[data-id="${productId}"]`);
        if (!card) {
            return 'Produk';
        }
        const nameEl = card.querySelector('.kp-product-name');
        return nameEl ? nameEl.textContent.trim() : 'Produk';
    };

    const findVoucher = (code) => {
        if (!code) {
            return null;
        }
        const normalized = code.trim().toUpperCase();
        if (!normalized) {
            return null;
        }
        return (promoVouchers || []).find((voucher) => {
            if (!voucher || !voucher.code) {
                return false;
            }
            if (String(voucher.code).toUpperCase() !== normalized) {
                return false;
            }
            if (voucher.active === false) {
                return false;
            }
            if (voucher.expires && String(voucher.expires) < new Date().toISOString().slice(0, 10)) {
                return false;
            }
            return true;
        }) || null;
    };

    const calculateTotals = () => {
        let subtotal = 0;
        let itemDiscountTotal = 0;

        Object.keys(cartState).forEach((productId) => {
            const qty = parseInt(cartState[productId], 10);
            if (qty <= 0) {
                return;
            }
            const input = document.getElementById('qty-input-' + productId);
            const price = parseFloat(input?.dataset.price || '0');
            const lineSubtotal = qty * price;
            subtotal += lineSubtotal;

            const discount = itemDiscounts[productId] || {};
            const type = discount.type || 'none';
            const value = parseFloat(discount.value || '0');
            let amount = 0;
            if (type === 'percent' && value > 0) {
                const pct = Math.min(100, Math.max(0, value));
                amount = (lineSubtotal * pct) / 100;
            } else if (type === 'amount' && value > 0) {
                amount = Math.min(value, lineSubtotal);
            }
            discount.amount = amount;
            itemDiscounts[productId] = discount;
            itemDiscountTotal += amount;
        });

        Object.keys(itemDiscounts).forEach((productId) => {
            if (!cartState[productId]) {
                delete itemDiscounts[productId];
            }
        });

        const subtotalAfterItem = Math.max(0, subtotal - itemDiscountTotal);
        const orderType = orderDiscountType?.value || 'none';
        const orderValue = parseFloat(orderDiscountValue?.value || '0');
        let orderDiscountAmount = 0;
        if (orderType === 'percent' && orderValue > 0) {
            const pct = Math.min(100, Math.max(0, orderValue));
            orderDiscountAmount = (subtotalAfterItem * pct) / 100;
        } else if (orderType === 'amount' && orderValue > 0) {
            orderDiscountAmount = Math.min(orderValue, subtotalAfterItem);
        }

        const voucherCode = voucherCodeInput?.value || '';
        const voucher = findVoucher(voucherCode);
        let voucherDiscountAmount = 0;
        if (voucher) {
            const minTotal = parseFloat(voucher.min_total || 0);
            if (subtotalAfterItem >= minTotal) {
                const type = String(voucher.type || 'amount');
                const value = parseFloat(voucher.value || 0);
                if (type === 'percent') {
                    voucherDiscountAmount = (subtotalAfterItem * Math.min(100, Math.max(0, value))) / 100;
                } else {
                    voucherDiscountAmount = Math.max(0, value);
                }
                if (voucher.max) {
                    voucherDiscountAmount = Math.min(voucherDiscountAmount, parseFloat(voucher.max));
                }
                voucherDiscountAmount = Math.min(voucherDiscountAmount, subtotalAfterItem - orderDiscountAmount);
                voucherHint.textContent = `Voucher ${voucher.code} aktif.`;
            } else {
                voucherHint.textContent = `Minimal belanja Rp ${new Intl.NumberFormat('id-ID').format(minTotal)}.`;
            }
        } else if (voucherCode.trim()) {
            voucherHint.textContent = 'Voucher tidak ditemukan.';
        } else {
            voucherHint.textContent = 'Opsional.';
        }

        const taxPercent = Math.min(100, Math.max(0, parseFloat(taxPercentInput?.value || '0')));
        const servicePercent = Math.min(100, Math.max(0, parseFloat(servicePercentInput?.value || '0')));
        const taxableBase = Math.max(0, subtotalAfterItem - orderDiscountAmount - voucherDiscountAmount);
        const taxAmount = (taxableBase * taxPercent) / 100;
        const serviceAmount = (taxableBase * servicePercent) / 100;
        const grossTotal = taxableBase + taxAmount + serviceAmount;

        let roundingAmount = 0;
        const roundingMode = roundingModeInput?.value || 'none';
        const roundingUnit = Math.max(1, parseInt(roundingUnitInput?.value || '100', 10));
        if (roundingMode !== 'none') {
            if (roundingMode === 'up') {
                roundingAmount = Math.ceil(grossTotal / roundingUnit) * roundingUnit - grossTotal;
            } else if (roundingMode === 'down') {
                roundingAmount = Math.floor(grossTotal / roundingUnit) * roundingUnit - grossTotal;
            } else {
                roundingAmount = Math.round(grossTotal / roundingUnit) * roundingUnit - grossTotal;
            }
        }

        const grandTotal = Math.max(0, Math.round(grossTotal + roundingAmount));

        subtotalValue.textContent = formatRupiah(subtotal);
        itemDiscountValue.textContent = formatRupiah(itemDiscountTotal);
        orderDiscountValueEl.textContent = formatRupiah(orderDiscountAmount);
        voucherDiscountValue.textContent = formatRupiah(voucherDiscountAmount);
        taxValue.textContent = formatRupiah(taxAmount);
        serviceValue.textContent = formatRupiah(serviceAmount);
        roundingValue.textContent = formatRupiah(roundingAmount);

        itemDiscountsInput.value = JSON.stringify(itemDiscounts);
        currentTotal = grandTotal;

        return {
            subtotal,
            itemDiscountTotal,
            orderDiscountAmount,
            voucherDiscountAmount,
            taxAmount,
            serviceAmount,
            roundingAmount,
            grandTotal,
        };
    };


    const resetCartAfterSuccess = () => {
        Object.keys(cartState).forEach((key) => {
            delete cartState[key];
        });
        Object.keys(itemDiscounts).forEach((key) => {
            delete itemDiscounts[key];
        });
        document.querySelectorAll('input[id^="qty-input-"]').forEach((input) => {
            input.value = '0';
            const productId = input.id.replace('qty-input-', '');
            const view = document.getElementById('qty-view-' + productId);
            if (view) {
                view.textContent = '0';
            }
        });
        localStorage.removeItem(localStateKey);
        localStorage.removeItem(legacyKey);
        if (cashInput) {
            cashInput.value = '0';
        }
        if (qrisInput) {
            qrisInput.value = '';
        }
        if (qrisPreview) {
            qrisPreview.src = '';
            qrisPreview.classList.add('d-none');
        }
        const noteField = document.getElementById('note');
        if (noteField) {
            noteField.value = '';
        }
        if (orderDiscountType) {
            orderDiscountType.value = 'none';
        }
        if (orderDiscountValue) {
            orderDiscountValue.value = '0';
        }
        if (voucherCodeInput) {
            voucherCodeInput.value = '';
        }
        if (taxPercentInput) {
            taxPercentInput.value = promoDefaults.tax_percent ?? 0;
        }
        if (servicePercentInput) {
            servicePercentInput.value = promoDefaults.service_percent ?? 0;
        }
        if (roundingModeInput) {
            roundingModeInput.value = promoDefaults.rounding_mode ?? 'none';
        }
        if (roundingUnitInput) {
            roundingUnitInput.value = promoDefaults.rounding_unit ?? 100;
        }
        if (customerSelect) {
            customerSelect.value = '0';
        }
        if (itemDiscountsInput) {
            itemDiscountsInput.value = '{}';
        }
        paymentButtons.forEach((button) => button.classList.remove('kp-btn-primary'));
        const cashButton = Array.from(paymentButtons).find((button) => button.dataset.method === 'cash');
        if (cashButton) {
            cashButton.classList.add('kp-btn-primary');
        }
        paymentMethod.value = 'cash';
        productCards.forEach((card) => card.classList.remove('active'));
        activeProductId = null;
        updateCustomerHint();
        updateCart();
    };

    const updatePayState = () => {
        const isQris = paymentMethod.value === 'qris';

        if (isQris) {
            qrisSection.style.display = 'block';
            cashSection.style.display = 'none';
            qrisInput.setAttribute('required', 'required');
            payButton.disabled = currentTotal <= 0 || qrisInput.files.length === 0 || !shiftOpen;
            cashInput.value = '0';
        } else {
            qrisSection.style.display = 'none';
            cashSection.style.display = 'block';
            qrisInput.removeAttribute('required');
            const existingCash = parseFloat(cashInput.value || '0');
            if (currentTotal > 0 && existingCash <= 0) {
                cashInput.value = currentTotal.toFixed(2).replace(/\.00$/, '');
            }
            const cashReceived = parseFloat(cashInput.value || '0');
            payButton.disabled = currentTotal <= 0 || cashReceived < currentTotal || !shiftOpen;
        }

        const cashReceived = parseFloat(cashInput.value || '0');
        const changeValue = Math.max(cashReceived - currentTotal, 0);
        cashChange.textContent = formatRupiah(changeValue);
    };

    const renderCartList = (targetEl) => {
        targetEl.innerHTML = '';
        let hasItem = false;

        Object.keys(cartState).forEach((productId) => {
            const qty = parseInt(cartState[productId], 10);
            if (qty <= 0) {
                return;
            }
            const input = document.getElementById('qty-input-' + productId);
            const price = parseFloat(input?.dataset.price || '0');
            const lineSubtotal = qty * price;
            const discount = itemDiscounts[productId] || {};
            const discountAmount = parseFloat(discount.amount || '0');
            const lineTotal = Math.max(0, lineSubtotal - discountAmount);
            hasItem = true;

            const productName = getProductName(productId);
            const row = document.createElement('div');
            row.className = 'kp-cart-item';
            row.innerHTML = `
                <div class="kp-cart-item-main">
                    <div class="kp-cart-item-title">${productName}</div>
                    <div class="kp-cart-item-meta">${qty} x ${formatRupiah(price)}</div>
                    ${discountAmount > 0 ? `<div class="kp-cart-item-meta">Diskon: -${formatRupiah(discountAmount)}</div>` : ''}
                </div>
                <div class="kp-cart-item-side">
                    <div class="kp-cart-item-total">${formatRupiah(lineTotal)}</div>
                    <div class="kp-cart-actions">
                        <button type="button" class="kp-void-btn discount-btn" data-id="${productId}" data-name="${productName}">
                            <span class="material-icons-outlined">percent</span>
                            Diskon
                        </button>
                        <button type="button" class="kp-void-btn" data-id="${productId}" data-name="${productName}">
                            <span class="material-icons-outlined">delete</span>
                            Void
                        </button>
                    </div>
                </div>
            `;
            targetEl.appendChild(row);
        });

        if (!hasItem) {
            const emptyCard = document.createElement('div');
            emptyCard.className = 'kp-card-flat p-3 text-center kp-muted';
            emptyCard.textContent = 'Keranjang kosong.';
            targetEl.appendChild(emptyCard);
        }
    };

    const updateCart = () => {
        renderCartList(cartList);
        const totals = calculateTotals();
        cartTotal.textContent = formatRupiah(totals.grandTotal);
        stickyTotal.textContent = formatRupiah(totals.grandTotal);
        if (desktopTotal) {
            desktopTotal.textContent = formatRupiah(totals.grandTotal);
        }

        saveCartState();
        updatePayState();
    };

    const setQty = (productId, qty) => {
        const input = document.getElementById('qty-input-' + productId);
        const view = document.getElementById('qty-view-' + productId);
        if (!input || !view) {
            return;
        }
        let safeQty = Math.max(0, qty);
        const card = document.querySelector(`.kp-product-card[data-id="${productId}"]`);
        if (card && card.dataset.stockTracked === '1') {
            const maxStock = parseInt(card.dataset.stock || '0', 10);
            if (safeQty > maxStock) {
                safeQty = maxStock;
                showToast('Stok tidak mencukupi.', 'error');
            }
        }
        input.value = safeQty;
        view.textContent = safeQty;
        if (safeQty === 0) {
            delete cartState[productId];
            delete itemDiscounts[productId];
        } else {
            cartState[productId] = safeQty;
        }
        updateCart();
    };

    const focusProductCard = (productId) => {
        const card = document.querySelector(`.kp-product-card[data-id="${productId}"]`);
        if (!card) {
            return;
        }
        activeProductId = String(productId);
        productCards.forEach((item) => item.classList.remove('active'));
        card.classList.add('active');
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const quickAddFromSearch = () => {
        const keyword = (searchInput?.value || '').trim().toLowerCase();
        if (!keyword) {
            return false;
        }

        let targetProductId = productLookupBySku.get(keyword) || productLookupByName.get(keyword) || null;
        if (!targetProductId) {
            const visibleCards = Array.from(productCards).filter((card) => card.style.display !== 'none');
            if (visibleCards.length === 1) {
                targetProductId = String(visibleCards[0].dataset.id || '');
            }
        }

        if (!targetProductId) {
            return false;
        }

        const currentQty = parseInt(cartState[targetProductId] || document.getElementById('qty-input-' + targetProductId)?.value || '0', 10);
        setQty(targetProductId, currentQty + 1);
        focusProductCard(targetProductId);
        showToast(`+1 ${getProductName(targetProductId)}`, 'success');
        searchInput.select();
        return true;
    };

    const clearCart = () => {
        Object.keys(cartState).forEach((productId) => {
            setQty(productId, 0);
        });
        localStorage.removeItem(localStateKey);
        localStorage.removeItem(legacyKey);
    };

    const filterProducts = () => {
        const keyword = searchInput.value.toLowerCase().trim();
        const activeChip = document.querySelector('#categoryChips .kp-chip.active');
        const selectedCategory = activeChip ? activeChip.dataset.category : 'all';

        productCards.forEach((card) => {
            const name = card.dataset.name || '';
            const sku = card.dataset.sku || '';
            const category = card.dataset.category || 'all';
            const matchCategory = selectedCategory === 'all' || selectedCategory === category;
            const matchName = name.includes(keyword) || sku.includes(keyword);
            card.style.display = matchCategory && matchName ? 'block' : 'none';
        });
    };

    qtyButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const productId = button.dataset.id;
            const action = button.dataset.action;
            let qty = parseInt(cartState[productId] || document.getElementById('qty-input-' + productId).value, 10);

            if (action === 'plus') {
                qty += 1;
            }
            if (action === 'minus' && qty > 0) {
                qty -= 1;
            }
            setQty(productId, qty);
            activeProductId = productId;
            productCards.forEach((card) => card.classList.remove('active'));
            const card = button.closest('.kp-product-card');
            if (card) {
                card.classList.add('active');
            }
        });
    });

    productCards.forEach((card) => {
        card.addEventListener('click', () => {
            activeProductId = card.dataset.id;
            productCards.forEach((item) => item.classList.remove('active'));
            card.classList.add('active');
        });
    });

    paymentButtons.forEach((button) => {
        button.addEventListener('click', () => {
            paymentButtons.forEach((btn) => btn.classList.remove('kp-btn-primary'));
            button.classList.add('kp-btn-primary');
            paymentMethod.value = button.dataset.method;
            updatePayState();
        });
    });

    qrisInput.addEventListener('change', () => {
        const file = qrisInput.files[0];
        if (!file) {
            qrisPreview.classList.add('d-none');
            updatePayState();
            return;
        }
        qrisPreview.src = URL.createObjectURL(file);
        qrisPreview.classList.remove('d-none');
        updatePayState();
    });

    cashInput.addEventListener('input', updateCart);
    if (orderDiscountType) {
        orderDiscountType.addEventListener('change', updateCart);
    }
    if (orderDiscountValue) {
        orderDiscountValue.addEventListener('input', updateCart);
    }
    if (voucherCodeInput) {
        voucherCodeInput.addEventListener('input', updateCart);
    }
    if (taxPercentInput) {
        taxPercentInput.addEventListener('input', updateCart);
    }
    if (servicePercentInput) {
        servicePercentInput.addEventListener('input', updateCart);
    }
    if (roundingModeInput) {
        roundingModeInput.addEventListener('change', updateCart);
    }
    if (roundingUnitInput) {
        roundingUnitInput.addEventListener('input', updateCart);
    }
    if (customerSelect) {
        customerSelect.addEventListener('change', () => {
            updateCustomerHint();
            saveCartState();
        });
    }

    let searchTimeout = null;
    searchInput.addEventListener('input', () => {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(filterProducts, 250);
    });
    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        if (!quickAddFromSearch()) {
            showToast('Produk tidak ditemukan.', 'error');
        }
    });

    categoryChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            categoryChips.forEach((item) => item.classList.remove('active'));
            chip.classList.add('active');
            filterProducts();
        });
    });

    const renderHoldList = () => {
        if (!holdList) {
            return;
        }
        holdList.innerHTML = '';
        if (!heldCarts.length) {
            const empty = document.createElement('div');
            empty.className = 'kp-muted small';
            empty.textContent = 'Belum ada hold.';
            holdList.appendChild(empty);
            return;
        }
        heldCarts.forEach((hold) => {
            const items = hold.items || {};
            const itemCount = hold.item_count ?? Object.values(items).reduce((sum, qty) => sum + parseInt(qty, 10), 0);
            const customerName = hold.customer && hold.customer.name ? escapeHtml(hold.customer.name) : '';
            const holdDate = escapeHtml(hold.created_at || '-');
            const row = document.createElement('div');
            row.className = 'kp-hold-item';
            row.innerHTML = `
                <div>
                    <div class="fw-semibold">Hold ${itemCount} item</div>
                    <div class="kp-muted small">${holdDate}</div>
                    ${customerName ? `<div class="kp-muted small">Member: ${customerName}</div>` : ''}
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn kp-btn-ghost btn-sm hold-load" data-id="${hold.id}">Lanjutkan</button>
                    <button type="button" class="btn kp-btn-ghost btn-sm hold-delete" data-id="${hold.id}">Hapus</button>
                </div>
            `;
            holdList.appendChild(row);
        });
    };

    const holdRequest = async (payload) => {
        const formData = new FormData();
        Object.keys(payload).forEach((key) => formData.append(key, payload[key]));
        formData.append('csrf_token', csrfToken);
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        return response.json();
    };

    const handleHoldSave = async () => {
        if (currentTotal <= 0) {
            showToast('Keranjang kosong.', 'error');
            return;
        }
        const items = {};
        Object.keys(cartState).forEach((productId) => {
            if (cartState[productId] > 0) {
                items[productId] = cartState[productId];
            }
        });
        const response = await holdRequest({
            action: 'hold_save',
            items: JSON.stringify(items),
            note: document.getElementById('note').value || '',
            item_discounts: JSON.stringify(itemDiscounts),
            order_discount_type: orderDiscountType?.value || 'none',
            order_discount_value: orderDiscountValue?.value || '0',
            voucher_code: voucherCodeInput?.value || '',
            tax_percent: taxPercentInput?.value || '0',
            service_percent: servicePercentInput?.value || '0',
            rounding_mode: roundingModeInput?.value || 'none',
            rounding_unit: roundingUnitInput?.value || '100',
            customer_id: customerSelect?.value || '0',
        });
        if (!response.ok) {
            showToast(response.message || 'Gagal menyimpan hold.', 'error');
            return;
        }
        heldCarts = response.holds || [];
        renderHoldList();
        clearCart();
        showToast('Hold tersimpan.', 'success');
    };

    const handleHoldLoad = async (holdId) => {
        const response = await holdRequest({ action: 'hold_load', hold_id: holdId });
        if (!response.ok) {
            showToast(response.message || 'Hold tidak ditemukan.', 'error');
            return;
        }
        clearCart();
        const items = response.items || {};
        Object.keys(items).forEach((productId) => setQty(productId, parseInt(items[productId], 10) || 0));
        document.getElementById('note').value = response.note || '';
        const pricing = response.pricing || {};
        if (pricing.item_discounts && typeof pricing.item_discounts === 'object') {
            Object.keys(itemDiscounts).forEach((key) => delete itemDiscounts[key]);
            Object.keys(pricing.item_discounts).forEach((productId) => {
                itemDiscounts[productId] = pricing.item_discounts[productId];
            });
        }
        if (orderDiscountType && pricing.order_discount_type) {
            orderDiscountType.value = pricing.order_discount_type;
        }
        if (orderDiscountValue && pricing.order_discount_value !== undefined) {
            orderDiscountValue.value = pricing.order_discount_value;
        }
        if (voucherCodeInput && pricing.voucher_code !== undefined) {
            voucherCodeInput.value = pricing.voucher_code;
        }
        if (taxPercentInput && pricing.tax_percent !== undefined) {
            taxPercentInput.value = pricing.tax_percent;
        }
        if (servicePercentInput && pricing.service_percent !== undefined) {
            servicePercentInput.value = pricing.service_percent;
        }
        if (roundingModeInput && pricing.rounding_mode) {
            roundingModeInput.value = pricing.rounding_mode;
        }
        if (roundingUnitInput && pricing.rounding_unit !== undefined) {
            roundingUnitInput.value = pricing.rounding_unit;
        }
        const holdCustomerId = pricing.customer_id ?? (response.customer ? response.customer.id : 0);
        if (customerSelect && holdCustomerId !== undefined) {
            customerSelect.value = String(holdCustomerId || 0);
        }
        updateCustomerHint();
        updateCart();
        showToast('Hold berhasil dimuat.', 'success');
    };

    const handleHoldDelete = async (holdId) => {
        const response = await holdRequest({ action: 'hold_delete', hold_id: holdId });
        if (!response.ok) {
            showToast('Gagal menghapus hold.', 'error');
            return;
        }
        heldCarts = response.holds || [];
        renderHoldList();
        showToast('Hold dihapus.', 'success');
    };

    const handleVoid = async (productId, reason, qty) => {
        await holdRequest({ action: 'void_log', product_id: productId, reason, qty });
        delete itemDiscounts[productId];
        setQty(productId, 0);
        showToast('Item dihapus.', 'success');
    };

    if (holdSaveBtn) {
        holdSaveBtn.addEventListener('click', handleHoldSave);
    }
    if (holdList) {
        holdList.addEventListener('click', (event) => {
            const loadBtn = event.target.closest('.hold-load');
            const deleteBtn = event.target.closest('.hold-delete');
            if (loadBtn) {
                handleHoldLoad(loadBtn.dataset.id);
            }
            if (deleteBtn) {
                handleHoldDelete(deleteBtn.dataset.id);
            }
        });
    }

    const openPaymentPanel = () => {
        const panel = bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('cartPanel'));
        panel.show();
    };

    const setPayLoading = (isLoading) => {
        if (isLoading) {
            payButton.disabled = true;
            confirmPayBtn.disabled = true;
        } else {
            updatePayState();
            confirmPayBtn.disabled = false;
        }
    };

    payButton.addEventListener('click', () => {
        if (!shiftOpen) {
            showToast('Shift belum dibuka.', 'error');
            return;
        }
        if (currentTotal <= 0) {
            showToast('Keranjang kosong.', 'error');
            return;
        }
        if (!paymentMethod.value) {
            showToast('Pilih metode pembayaran.', 'error');
            return;
        }
        if (paymentMethod.value === 'cash' && parseFloat(cashInput.value || '0') < currentTotal) {
            showToast('Uang tunai tidak mencukupi.', 'error');
            return;
        }
        if (paymentMethod.value === 'qris' && qrisInput.files.length === 0) {
            showToast('Upload bukti QRIS terlebih dahulu.', 'error');
            return;
        }
        confirmTotal.textContent = formatRupiah(currentTotal);
        confirmMethod.textContent = paymentMethod.value.toUpperCase();
        if (confirmCustomer) {
            const selected = customerSelect ? customerSelect.options[customerSelect.selectedIndex] : null;
            confirmCustomer.textContent = (!selected || customerSelect.value === '0') ? 'Umum' : selected.textContent;
        }
        bootstrap.Modal.getOrCreateInstance(confirmModalEl).show();
    });

    confirmPayBtn.addEventListener('click', () => {
        if (confirmPayBtn.disabled) {
            return;
        }
        setPayLoading(true);
        posForm.submit();
    });

    posForm.addEventListener('submit', () => {
        setPayLoading(true);
    });

    const openDiscountModal = (productId, productName) => {
        pendingDiscountProduct = productId;
        discountItemLabel.textContent = productName;
        const current = itemDiscounts[productId] || {};
        discountTypeInput.value = current.type || 'none';
        discountValueInput.value = current.value || 0;
        bootstrap.Modal.getOrCreateInstance(discountModalEl).show();
    };

    const openVoidModal = (productId, productName, qty) => {
        pendingVoid = { productId, productName, qty };
        voidItemLabel.textContent = `${productName} x${qty}`;
        voidReason.value = '';
        bootstrap.Modal.getOrCreateInstance(voidModalEl).show();
    };

    cartList.addEventListener('click', (event) => {
        const discountBtn = event.target.closest('.discount-btn');
        if (discountBtn) {
            const productId = discountBtn.dataset.id;
            const productName = discountBtn.dataset.name;
            if (productId) {
                openDiscountModal(productId, productName);
            }
            return;
        }
        const voidBtn = event.target.closest('.kp-void-btn');
        if (!voidBtn) {
            return;
        }
        const productId = voidBtn.dataset.id;
        const productName = voidBtn.dataset.name;
        const qty = parseInt(cartState[productId] || '0', 10);
        if (qty > 0) {
            openVoidModal(productId, productName, qty);
        }
    });

    discountApplyBtn.addEventListener('click', () => {
        if (!pendingDiscountProduct) {
            return;
        }
        const type = discountTypeInput.value || 'none';
        const value = parseFloat(discountValueInput.value || '0');
        if (type === 'none' || value <= 0) {
            delete itemDiscounts[pendingDiscountProduct];
        } else {
            itemDiscounts[pendingDiscountProduct] = { type, value };
        }
        bootstrap.Modal.getOrCreateInstance(discountModalEl).hide();
        pendingDiscountProduct = null;
        updateCart();
    });

    voidConfirmBtn.addEventListener('click', () => {
        if (!pendingVoid) {
            return;
        }
        const reason = voidReason.value.trim();
        if (!reason) {
            showToast('Alasan void wajib diisi.', 'error');
            return;
        }
        handleVoid(pendingVoid.productId, reason, pendingVoid.qty);
        bootstrap.Modal.getOrCreateInstance(voidModalEl).hide();
        pendingVoid = null;
    });

    document.addEventListener('keydown', (event) => {
        const tag = event.target.tagName.toLowerCase();
        if (['input', 'textarea', 'select'].includes(tag)) {
            return;
        }
        if (event.key === '+' && activeProductId) {
            const qty = parseInt(cartState[activeProductId] || '0', 10);
            setQty(activeProductId, qty + 1);
        }
        if (event.key === '-' && activeProductId) {
            const qty = parseInt(cartState[activeProductId] || '0', 10);
            if (qty > 0) {
                setQty(activeProductId, qty - 1);
            }
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            openPaymentPanel();
        }
        if (event.key === 'Escape') {
            const activeModal = document.querySelector('.modal.show');
            if (activeModal) {
                bootstrap.Modal.getOrCreateInstance(activeModal).hide();
                return;
            }
            const activeOffcanvas = document.querySelector('.offcanvas.show');
            if (activeOffcanvas) {
                bootstrap.Offcanvas.getOrCreateInstance(activeOffcanvas).hide();
            }
        }
    });

    if (paymentMethod.value === 'qris') {
        paymentButtons[1].click();
    } else {
        paymentButtons[0].click();
    }

    loadCartState();
    syncStateFromInputs();
    if (shouldClearCart) {
        resetCartAfterSuccess();
    } else {
        updateCart();
    }
    updateCustomerHint();
    filterProducts();
    renderHoldList();
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
