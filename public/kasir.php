<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/upload_helper.php';
require_once __DIR__ . '/../app/helpers/telegram_helper.php';
require_once __DIR__ . '/../app/helpers/inventory_helper.php';
require_once __DIR__ . '/../app/helpers/promo_helper.php';
require_once __DIR__ . '/../app/helpers/transaction_meta_helper.php';
require_once __DIR__ . '/../app/helpers/shift_helper.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/Transaction.php';
require_once __DIR__ . '/../app/models/Payment.php';

require_role(['karyawan', 'bos', 'admin']);

$pdo = db();
$user = current_user();
$promoConfig = promo_get_config();
$promoDefaults = $promoConfig['defaults'] ?? [];
$inventoryItems = inventory_get_all();
$activeShift = $user ? shift_get_active((int) $user['id']) : null;
$errors = [];
$success = '';
$lastTransactionId = null;
$paymentMethod = $_POST['payment_method'] ?? 'cash';
$cashReceived = max(0.0, (float) ($_POST['cash_received'] ?? 0));
$note = trim((string) ($_POST['note'] ?? ''));

start_session();
if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = date('Y-m-d H:i:s');
}
if (!isset($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
}
if (!isset($_SESSION['used_checkout_tokens'])) {
    $_SESSION['used_checkout_tokens'] = [];
}
if (!empty($_SESSION['used_checkout_tokens'])) {
    $cutoff = time() - 86400;
    $_SESSION['used_checkout_tokens'] = array_filter(
        $_SESSION['used_checkout_tokens'],
        static fn ($timestamp) => (int) $timestamp >= $cutoff
    );
}

// Handle hold cart and void log actions via JSON.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Token tidak valid.']);
        exit;
    }
    $action = (string) $_POST['action'];
    $userId = (int) ($user['id'] ?? 0);
    if (!isset($_SESSION['held_carts'][$userId])) {
        $_SESSION['held_carts'][$userId] = [];
    }
    if (!isset($_SESSION['void_logs'][$userId])) {
        $_SESSION['void_logs'][$userId] = [];
    }

    $respond = static function (array $payload): void {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    };

    $buildHoldList = static function (array $holds): array {
        $list = [];
        foreach ($holds as $hold) {
            $itemCount = 0;
            foreach ($hold['items'] as $qty) {
                $itemCount += (int) $qty;
            }
            $list[] = [
                'id' => $hold['id'],
                'created_at' => $hold['created_at'],
                'item_count' => $itemCount,
                'note' => $hold['note'] ?? '',
            ];
        }
        return $list;
    };

    if ($action === 'hold_save') {
        $items = json_decode((string) ($_POST['items'] ?? ''), true);
        if (!is_array($items) || empty($items)) {
            $respond(['ok' => false, 'message' => 'Keranjang kosong.']);
        }
        $pricing = [
            'item_discounts' => json_decode((string) ($_POST['item_discounts'] ?? ''), true) ?: [],
            'order_discount_type' => (string) ($_POST['order_discount_type'] ?? 'none'),
            'order_discount_value' => (float) ($_POST['order_discount_value'] ?? 0),
            'voucher_code' => trim((string) ($_POST['voucher_code'] ?? '')),
            'tax_percent' => (float) ($_POST['tax_percent'] ?? ($promoDefaults['tax_percent'] ?? 0)),
            'service_percent' => (float) ($_POST['service_percent'] ?? ($promoDefaults['service_percent'] ?? 0)),
            'rounding_mode' => (string) ($_POST['rounding_mode'] ?? ($promoDefaults['rounding_mode'] ?? 'none')),
            'rounding_unit' => (int) ($_POST['rounding_unit'] ?? ($promoDefaults['rounding_unit'] ?? 100)),
        ];
        $hold = [
            'id' => uniqid('hold_', true),
            'created_at' => date('Y-m-d H:i:s'),
            'items' => $items,
            'note' => trim((string) ($_POST['note'] ?? '')),
            'pricing' => $pricing,
        ];
        $_SESSION['held_carts'][$userId][] = $hold;
        $respond(['ok' => true, 'holds' => $buildHoldList($_SESSION['held_carts'][$userId])]);
    }

    if ($action === 'hold_list') {
        $respond(['ok' => true, 'holds' => $buildHoldList($_SESSION['held_carts'][$userId])]);
    }

    if ($action === 'hold_load') {
        $holdId = (string) ($_POST['hold_id'] ?? '');
        foreach ($_SESSION['held_carts'][$userId] as $hold) {
            if ($hold['id'] === $holdId) {
                $respond([
                    'ok' => true,
                    'items' => $hold['items'],
                    'note' => $hold['note'] ?? '',
                    'pricing' => $hold['pricing'] ?? [],
                ]);
            }
        }
        $respond(['ok' => false, 'message' => 'Hold tidak ditemukan.']);
    }

    if ($action === 'hold_delete') {
        $holdId = (string) ($_POST['hold_id'] ?? '');
        $_SESSION['held_carts'][$userId] = array_values(array_filter(
            $_SESSION['held_carts'][$userId],
            static fn ($hold) => $hold['id'] !== $holdId
        ));
        $respond(['ok' => true, 'holds' => $buildHoldList($_SESSION['held_carts'][$userId])]);
    }

    if ($action === 'void_log') {
        $log = [
            'product_id' => (int) ($_POST['product_id'] ?? 0),
            'qty' => (int) ($_POST['qty'] ?? 0),
            'reason' => trim((string) ($_POST['reason'] ?? '')),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $_SESSION['void_logs'][$userId][] = $log;
        audit_log('void_cart_item', [
            'user_id' => $userId,
            'product_id' => $log['product_id'],
            'qty' => $log['qty'],
            'reason' => $log['reason'],
        ]);
        $respond(['ok' => true]);
    }

    $respond(['ok' => false, 'message' => 'Aksi tidak dikenal.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }
    $items = $_POST['items'] ?? [];
    $selectedItems = [];

    foreach ($items as $productId => $quantity) {
        $qty = (int) $quantity;
        if ($qty > 0) {
            $selectedItems[(int) $productId] = $qty;
        }
    }

    if (empty($selectedItems)) {
        $errors[] = 'Pilih minimal satu produk.';
    }

    if (!in_array($paymentMethod, ['cash', 'qris'], true)) {
        $errors[] = 'Metode pembayaran tidak valid.';
    }

    $productsMap = Product::findByIds($pdo, array_keys($selectedItems));
    $stockColumn = Product::stockColumn($pdo);

    if (!empty($selectedItems)) {
        foreach ($selectedItems as $productId => $qty) {
            if (!isset($productsMap[$productId])) {
                $errors[] = 'Produk tidak ditemukan.';
                break;
            }
        }
    }

    $qrisPath = null;
    if ($paymentMethod === 'qris') {
        $uploadResult = save_qris_upload($_FILES['qris_proof'] ?? []);
        if (!empty($uploadResult['error'])) {
            $errors[] = $uploadResult['error'];
        } else {
            $qrisPath = $uploadResult['path'];
        }
    }

    if (empty($errors)) {
        $activeShift = shift_get_active((int) ($user['id'] ?? 0));
        if (!$activeShift) {
            $errors[] = 'Shift belum dibuka. Silakan buka shift terlebih dahulu.';
        }
    }

    if (empty($errors)) {
        $itemDiscounts = json_decode((string) ($_POST['item_discounts'] ?? ''), true);
        $itemDiscounts = is_array($itemDiscounts) ? $itemDiscounts : [];
        $orderDiscountType = (string) ($_POST['order_discount_type'] ?? 'none');
        $orderDiscountValue = (float) ($_POST['order_discount_value'] ?? 0);
        $voucherCode = trim((string) ($_POST['voucher_code'] ?? ''));
        $taxPercent = (float) ($_POST['tax_percent'] ?? ($promoDefaults['tax_percent'] ?? 0));
        $servicePercent = (float) ($_POST['service_percent'] ?? ($promoDefaults['service_percent'] ?? 0));
        $roundingMode = (string) ($_POST['rounding_mode'] ?? ($promoDefaults['rounding_mode'] ?? 'none'));
        $roundingUnit = (int) ($_POST['rounding_unit'] ?? ($promoDefaults['rounding_unit'] ?? 100));

        if (!in_array($orderDiscountType, ['none', 'percent', 'amount'], true)) {
            $orderDiscountType = 'none';
        }
        if (!in_array($roundingMode, ['none', 'nearest', 'up', 'down'], true)) {
            $roundingMode = 'none';
        }
        $orderDiscountValue = max(0, $orderDiscountValue);
        $taxPercent = max(0, min(100, $taxPercent));
        $servicePercent = max(0, min(100, $servicePercent));
        $roundingUnit = max(1, $roundingUnit);

        $voucher = null;
        if ($voucherCode !== '') {
            $voucher = promo_find_voucher($voucherCode);
            if (!$voucher) {
                $errors[] = 'Voucher tidak valid atau sudah kedaluwarsa.';
            }
        }
    }

    if (empty($errors)) {
        $subtotal = 0.0;
        $itemDiscountTotal = 0.0;
        $lineItems = [];
        $itemDiscountMeta = [];
        $itemMeta = [];

        foreach ($selectedItems as $productId => $qty) {
            $product = $productsMap[$productId];
            $price = (float) $product['price'];
            $lineSubtotal = $price * $qty;
            $subtotal += $lineSubtotal;

            if ($stockColumn) {
                $stock = isset($product['stock']) ? (int) $product['stock'] : null;
                if ($stock !== null && $stock < $qty) {
                    $errors[] = 'Stok produk tidak mencukupi untuk ' . $product['name'] . '.';
                    break;
                }
            }

            $invItem = $inventoryItems[$productId] ?? null;
            if (is_array($invItem) && array_key_exists('stock', $invItem) && $invItem['stock'] !== null) {
                $virtualStock = (int) ($invItem['stock'] ?? 0);
                if ($virtualStock < $qty) {
                    $errors[] = 'Stok virtual tidak mencukupi untuk ' . $product['name'] . '.';
                    break;
                }
            }

            $discountType = 'none';
            $discountValue = 0.0;
            $discountAmount = 0.0;
            if (isset($itemDiscounts[$productId]) && is_array($itemDiscounts[$productId])) {
                $discountType = (string) ($itemDiscounts[$productId]['type'] ?? 'none');
                $discountValue = (float) ($itemDiscounts[$productId]['value'] ?? 0);
                if ($discountType === 'percent') {
                    $discountValue = max(0, min(100, $discountValue));
                    $discountAmount = ($lineSubtotal * $discountValue) / 100;
                } elseif ($discountType === 'amount') {
                    $discountValue = max(0, $discountValue);
                    $discountAmount = min($discountValue, $lineSubtotal);
                } else {
                    $discountType = 'none';
                    $discountValue = 0.0;
                }
            }

            $lineTotal = max(0.0, $lineSubtotal - $discountAmount);
            $itemDiscountTotal += $discountAmount;

            $lineItems[] = [
                'product_id' => $productId,
                'quantity' => $qty,
                'price' => $price,
                'subtotal' => $lineSubtotal,
            ];

            $itemDiscountMeta[$productId] = [
                'type' => $discountType,
                'value' => $discountValue,
                'amount' => $discountAmount,
            ];

            $itemMeta[$productId] = [
                'name' => $product['name'] ?? '',
                'qty' => $qty,
                'price' => $price,
                'subtotal' => $lineSubtotal,
                'discount' => $discountAmount,
                'total' => $lineTotal,
            ];
        }

        $subtotalAfterItem = max(0.0, $subtotal - $itemDiscountTotal);
        $orderDiscountAmount = 0.0;
        if ($orderDiscountType === 'percent' && $orderDiscountValue > 0) {
            $orderDiscountAmount = ($subtotalAfterItem * min(100, $orderDiscountValue)) / 100;
        } elseif ($orderDiscountType === 'amount' && $orderDiscountValue > 0) {
            $orderDiscountAmount = min($orderDiscountValue, $subtotalAfterItem);
        }

        $voucherDiscountAmount = 0.0;
        if ($voucher) {
            $minTotal = (float) ($voucher['min_total'] ?? 0);
            if ($subtotalAfterItem < $minTotal) {
                $errors[] = 'Minimal belanja untuk voucher belum terpenuhi.';
            } else {
                $type = (string) ($voucher['type'] ?? 'amount');
                $value = (float) ($voucher['value'] ?? 0);
                if ($type === 'percent') {
                    $value = max(0, min(100, $value));
                    $voucherDiscountAmount = ($subtotalAfterItem * $value) / 100;
                } else {
                    $voucherDiscountAmount = max(0, $value);
                }
                $maxDiscount = (float) ($voucher['max'] ?? 0);
                if ($maxDiscount > 0) {
                    $voucherDiscountAmount = min($voucherDiscountAmount, $maxDiscount);
                }
                $voucherDiscountAmount = min($voucherDiscountAmount, $subtotalAfterItem - $orderDiscountAmount);
            }
        }

        $taxableBase = max(0.0, $subtotalAfterItem - $orderDiscountAmount - $voucherDiscountAmount);
        $taxAmount = ($taxableBase * $taxPercent) / 100;
        $serviceAmount = ($taxableBase * $servicePercent) / 100;
        $grossTotal = $taxableBase + $taxAmount + $serviceAmount;

        $roundingAmount = 0.0;
        if ($roundingMode !== 'none' && $roundingUnit > 0) {
            if ($roundingMode === 'up') {
                $rounded = ceil($grossTotal / $roundingUnit) * $roundingUnit;
                $roundingAmount = $rounded - $grossTotal;
            } elseif ($roundingMode === 'down') {
                $rounded = floor($grossTotal / $roundingUnit) * $roundingUnit;
                $roundingAmount = $rounded - $grossTotal;
            } else {
                $rounded = round($grossTotal / $roundingUnit) * $roundingUnit;
                $roundingAmount = $rounded - $grossTotal;
            }
        }

        $total = max(0.0, round($grossTotal + $roundingAmount, 0));
        $cashChange = max(0.0, $cashReceived - $total);

        if ($paymentMethod === 'cash' && $cashReceived <= 0 && $total > 0) {
            $cashReceived = $total;
            $cashChange = 0.0;
        }

        if ($paymentMethod === 'cash' && $cashReceived < $total) {
            $errors[] = 'Uang tunai tidak mencukupi.';
        }

        if (empty($errors)) {
            $checkoutToken = (string) ($_POST['checkout_token'] ?? '');
            if ($checkoutToken === '' || !hash_equals((string) ($_SESSION['checkout_token'] ?? ''), $checkoutToken)) {
                $errors[] = 'Token transaksi tidak valid. Silakan refresh halaman.';
            } elseif (isset($_SESSION['used_checkout_tokens'][$checkoutToken])) {
                $errors[] = 'Transaksi sedang diproses atau sudah tersimpan.';
            } else {
                $_SESSION['used_checkout_tokens'][$checkoutToken] = time();
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $user = current_user();
                $transactionId = Transaction::create($pdo, (int) $user['id'], $total, $note);

                foreach ($lineItems as $item) {
                    Transaction::addItem(
                        $pdo,
                        $transactionId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['price'],
                        $item['subtotal']
                    );
                    if ($stockColumn) {
                        if (!Product::reduceStock($pdo, $item['product_id'], $item['quantity'])) {
                            throw new RuntimeException('Stok tidak mencukupi.');
                        }
                    }
                }

                Payment::create($pdo, $transactionId, $paymentMethod, $total, $qrisPath);

                $pdo->commit();
                $lastTransactionId = $transactionId;
                audit_log('transaction_created', [
                    'transaction_id' => $transactionId,
                    'user_id' => (int) ($user['id'] ?? 0),
                    'method' => $paymentMethod,
                    'total' => $total,
                ]);

                foreach ($lineItems as $item) {
                    $productId = (int) $item['product_id'];
                    $invItem = $inventoryItems[$productId] ?? null;
                    if (is_array($invItem) && array_key_exists('stock', $invItem) && $invItem['stock'] !== null) {
                        inventory_adjust(
                            $productId,
                            -1 * (int) $item['quantity'],
                            'sale',
                            'trx#' . $transactionId,
                            (int) ($user['id'] ?? 0)
                        );
                    }
                }

                $meta = [
                    'subtotal' => $subtotal,
                    'item_discounts' => $itemDiscountMeta,
                    'item_discount_total' => $itemDiscountTotal,
                    'order_discount' => [
                        'type' => $orderDiscountType,
                        'value' => $orderDiscountValue,
                        'amount' => $orderDiscountAmount,
                    ],
                    'voucher' => $voucher ? [
                        'code' => $voucher['code'] ?? $voucherCode,
                        'type' => $voucher['type'] ?? 'amount',
                        'value' => $voucher['value'] ?? 0,
                        'amount' => $voucherDiscountAmount,
                        'max' => $voucher['max'] ?? 0,
                        'min_total' => $voucher['min_total'] ?? 0,
                        'name' => $voucher['name'] ?? '',
                    ] : null,
                    'tax' => ['percent' => $taxPercent, 'amount' => $taxAmount],
                    'service' => ['percent' => $servicePercent, 'amount' => $serviceAmount],
                    'rounding' => ['mode' => $roundingMode, 'unit' => $roundingUnit, 'amount' => $roundingAmount],
                    'total_before_rounding' => $grossTotal,
                    'grand_total' => $total,
                    'payment_method' => $paymentMethod,
                    'cash_received' => $cashReceived,
                    'cash_change' => $cashChange,
                    'shift_id' => $activeShift['shift_id'] ?? '',
                    'items' => $itemMeta,
                ];
                transaction_meta_set($transactionId, $meta);

                if (telegram_is_configured()) {
                    $cashierName = $user['name'] ?? 'Kasir';
                    $divider = str_repeat('-', 32);
                    $lines = [];
                    $lines[] = 'KASIR PINTAR';
                    $lines[] = 'Bukti Transaksi Pembayaran';
                    $lines[] = $divider;
                    $lines[] = '';
                    $lines[] = 'No. Transaksi : #' . $transactionId;
                    $lines[] = 'Tanggal       : ' . date('d/m/Y');
                    $lines[] = 'Waktu         : ' . date('H:i');
                    $lines[] = 'Kasir         : ' . $cashierName;
                    $lines[] = 'Metode Bayar  : ' . strtoupper($paymentMethod);
                    $lines[] = '';
                    $lines[] = $divider;
                    $lines[] = 'DETAIL PESANAN';
                    $lines[] = $divider;

                    foreach ($lineItems as $item) {
                        $productName = $productsMap[$item['product_id']]['name'] ?? 'Item';
                        $metaItem = $itemMeta[$item['product_id']] ?? null;
                        $lineTotal = $metaItem ? (float) ($metaItem['total'] ?? 0) : (float) $item['subtotal'];
                        $lineDiscount = $metaItem ? (float) ($metaItem['discount'] ?? 0) : 0.0;
                        $lines[] = $productName;
                        $lines[] = 'Qty  : ' . (int) $item['quantity'];
                        $lines[] = 'Harga: ' . format_rupiah($lineTotal);
                        if ($lineDiscount > 0) {
                            $lines[] = 'Diskon: -' . format_rupiah($lineDiscount);
                        }
                        $lines[] = '';
                    }

                    if ($note !== '') {
                        $lines[] = 'Catatan: ' . $note;
                        $lines[] = '';
                    }

                    $lines[] = $divider;
                    if ($itemDiscountTotal > 0) {
                        $lines[] = 'Diskon Item : -' . format_rupiah($itemDiscountTotal);
                    }
                    if ($orderDiscountAmount > 0) {
                        $lines[] = 'Diskon Order: -' . format_rupiah($orderDiscountAmount);
                    }
                    if ($voucherDiscountAmount > 0) {
                        $lines[] = 'Voucher      : -' . format_rupiah($voucherDiscountAmount);
                    }
                    if ($taxAmount > 0) {
                        $lines[] = 'Pajak        : ' . format_rupiah($taxAmount);
                    }
                    if ($serviceAmount > 0) {
                        $lines[] = 'Service      : ' . format_rupiah($serviceAmount);
                    }
                    if ($roundingAmount != 0.0) {
                        $lines[] = 'Pembulatan   : ' . format_rupiah($roundingAmount);
                    }
                    $lines[] = 'TOTAL BAYAR';
                    $lines[] = $divider;
                    $lines[] = format_rupiah($total);
                    $lines[] = $divider;
                    $lines[] = '';
                    $lines[] = 'Instagram : @kasirpintar';
                    $lines[] = 'Alamat    : Jl. SMA 1 Kel No. RT 16,';
                    $lines[] = '            Aur, Sarolangun,';
                    $lines[] = '            Kab. Sarolangun, Jambi 37481';
                    $lines[] = '';
                    $lines[] = 'Terima kasih atas kunjungan Anda.';

                    $caption = '<pre>' . telegram_escape(implode("\n", $lines)) . '</pre>';
                    $logoPng = __DIR__ . '/assets/images/logo.png';
                    $logoJpg = __DIR__ . '/assets/images/logo.jpg';
                    $logoPath = is_file($logoPng) ? $logoPng : $logoJpg;

                    $sent = false;
                    if (is_file($logoPath)) {
                        $sent = telegram_send_photo($logoPath, $caption, 'HTML');
                    }

                    if (!$sent) {
                        telegram_send_message($caption, 'HTML');
                    }
                }

                $success = 'Transaksi #' . $transactionId . ' berhasil disimpan. Total: ' . format_rupiah($total);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (!empty($checkoutToken)) {
                    unset($_SESSION['used_checkout_tokens'][$checkoutToken]);
                }
                $errors[] = 'Gagal menyimpan transaksi. Silakan coba lagi.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
}

$products = Product::all($pdo);
$categoryColumn = Product::categoryColumn($pdo);
$categories = $categoryColumn ? Category::all($pdo) : [];
$inventoryItems = inventory_get_all();
$promoDefaults = $promoDefaults ?? [];
$promoVouchers = $promoConfig['vouchers'] ?? [];
$shiftInfo = $activeShift;
$shiftBalance = $shiftInfo ? shift_cash_balance($shiftInfo) : null;
$kasirInfo = [
    'name' => $user['name'] ?? 'Kasir',
    'login_time' => $_SESSION['login_time'] ?? date('Y-m-d H:i:s'),
    'role' => $user['role'] ?? '-',
];
$holdCarts = $_SESSION['held_carts'][(int) ($user['id'] ?? 0)] ?? [];
$checkoutToken = $_SESSION['checkout_token'];
$title = 'Kasir';

require_once __DIR__ . '/../app/views/kasir/index.php';

