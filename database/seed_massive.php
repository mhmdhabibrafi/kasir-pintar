<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';

$pdo = db();

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table"
    );
    $stmt->execute([':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function rand_datetime(int $startTs, int $endTs): string
{
    return date('Y-m-d H:i:s', random_int($startTs, $endTs));
}

function weighted_pick(array $items): string
{
    $sum = 0;
    foreach ($items as $it) {
        $sum += $it['w'];
    }

    $pick = random_int(1, $sum);
    $acc = 0;
    foreach ($items as $it) {
        $acc += $it['w'];
        if ($pick <= $acc) {
            return $it['v'];
        }
    }

    return $items[0]['v'];
}

$users = $pdo->query('SELECT id, name FROM users ORDER BY id')->fetchAll();
if (!$users) {
    throw new RuntimeException('Tabel users kosong, proses dibatalkan.');
}

$userIds = array_map(static fn(array $u): int => (int) $u['id'], $users);
$userNames = [];
foreach ($users as $u) {
    $userNames[(int) $u['id']] = (string) $u['name'];
}

$clearTables = [
    'refund_items',
    'refunds',
    'customer_point_logs',
    'transaction_meta',
    'payments',
    'transaction_items',
    'transactions',
    'shift_movements',
    'shifts',
    'inventory_logs',
    'inventory_items',
    'cash_entries',
    'cash_days',
    'promo_vouchers',
    'promo_settings',
    'notification_settings',
    'products',
    'categories',
    'customers',
];

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($clearTables as $table) {
    if (!table_exists($pdo, $table)) {
        continue;
    }
    $pdo->exec("DELETE FROM `{$table}`");
    $pdo->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$pdo->beginTransaction();

try {
    $categoryNames = [
        'Coffee',
        'Non Coffee',
        'Tea Series',
        'Snack',
        'Pastry',
        'Rice Bowl',
        'Noodle',
        'Dessert',
        'Juice',
        'Smoothies',
        'Mocktail',
        'Breakfast',
        'Add On',
        'Signature',
        'Seasonal',
        'Ice Cream',
        'Sandwich',
        'Bakery',
        'Mineral Water',
        'Traditional Drink',
    ];

    $insertCategory = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
    $categories = [];
    foreach ($categoryNames as $name) {
        $insertCategory->execute([':name' => $name]);
        $categories[] = ['id' => (int) $pdo->lastInsertId(), 'name' => $name];
    }

    $adj = ['Classic', 'Premium', 'Special', 'Signature', 'House', 'Dark', 'Sweet', 'Creamy', 'Iced', 'Hot'];
    $noun = ['Blend', 'Latte', 'Brew', 'Toast', 'Bite', 'Bowl', 'Noodle', 'Tea', 'Shake', 'Punch'];
    $insertProduct = $pdo->prepare(
        'INSERT INTO products (category_id, name, sku, price, cost_price, is_active, created_at)
         VALUES (:category_id, :name, :sku, :price, :cost_price, 1, :created_at)'
    );

    $products = [];
    $skuCounter = 1;
    foreach ($categories as $category) {
        for ($i = 1; $i <= 20; $i++) {
            $price = random_int(12000, 78000);
            $cost = (int) round($price * random_int(45, 70) / 100);
            $name = $category['name'] . ' ' . $adj[array_rand($adj)] . ' ' . $noun[array_rand($noun)] . ' ' . $i;
            $sku = sprintf('SKU-%03d-%05d', $category['id'], $skuCounter++);

            $insertProduct->execute([
                ':category_id' => $category['id'],
                ':name' => $name,
                ':sku' => $sku,
                ':price' => $price,
                ':cost_price' => $cost,
                ':created_at' => rand_datetime(strtotime('-240 days'), strtotime('-20 days')),
            ]);

            $products[] = [
                'id' => (int) $pdo->lastInsertId(),
                'price' => $price,
            ];
        }
    }

    $insertInventoryItem = $pdo->prepare(
        'INSERT INTO inventory_items (product_id, stock, min_stock) VALUES (:product_id, :stock, :min_stock)'
    );
    $insertInventoryLog = $pdo->prepare(
        'INSERT INTO inventory_logs (product_id, delta, stock, min_stock, reason, ref, note, user_id, created_at)
         VALUES (:product_id, :delta, :stock, :min_stock, :reason, :ref, :note, :user_id, :created_at)'
    );

    $inventoryState = [];
    foreach ($products as $product) {
        $stock = random_int(80, 320);
        $minStock = random_int(10, 40);
        $inventoryState[$product['id']] = ['stock' => $stock, 'min_stock' => $minStock];

        $insertInventoryItem->execute([
            ':product_id' => $product['id'],
            ':stock' => $stock,
            ':min_stock' => $minStock,
        ]);

        $insertInventoryLog->execute([
            ':product_id' => $product['id'],
            ':delta' => $stock,
            ':stock' => $stock,
            ':min_stock' => $minStock,
            ':reason' => 'stock_opname',
            ':ref' => 'INIT',
            ':note' => 'Initial stock seed',
            ':user_id' => $userIds[array_rand($userIds)],
            ':created_at' => rand_datetime(strtotime('-180 days'), strtotime('-5 days')),
        ]);

        if (random_int(1, 100) <= 40) {
            $add = random_int(20, 100);
            $stock += $add;
            $inventoryState[$product['id']]['stock'] = $stock;

            $insertInventoryLog->execute([
                ':product_id' => $product['id'],
                ':delta' => $add,
                ':stock' => $stock,
                ':min_stock' => $minStock,
                ':reason' => 'restock',
                ':ref' => 'PO-' . random_int(1000, 9999),
                ':note' => 'Auto restock seed',
                ':user_id' => $userIds[array_rand($userIds)],
                ':created_at' => rand_datetime(strtotime('-180 days'), strtotime('-5 days')),
            ]);
        }
    }

    $insertCustomer = $pdo->prepare(
        'INSERT INTO customers (name, phone, email, address, is_active, points, total_spent, total_visits, last_transaction_at, created_at)
         VALUES (:name, :phone, :email, :address, 1, 0, 0, 0, NULL, :created_at)'
    );
    $customers = [];
    for ($i = 1; $i <= 320; $i++) {
        $insertCustomer->execute([
            ':name' => sprintf('Customer %03d', $i),
            ':phone' => '0812' . str_pad((string) (3000000 + $i), 7, '0', STR_PAD_LEFT),
            ':email' => sprintf('customer%03d@mail.test', $i),
            ':address' => 'Jl. Dummy No. ' . random_int(1, 250) . ', Kota Sample',
            ':created_at' => rand_datetime(strtotime('-300 days'), strtotime('-20 days')),
        ]);
        $customers[] = (int) $pdo->lastInsertId();
    }

    $pdo->exec("INSERT INTO promo_settings (tax_percent, service_percent, rounding_mode, rounding_unit) VALUES (11.00, 5.00, 'nearest', 100)");

    $insertVoucher = $pdo->prepare(
        'INSERT INTO promo_vouchers (code, name, type, value, min_total, max_discount, expires, active, created_at)
         VALUES (:code, :name, :type, :value, :min_total, :max_discount, :expires, :active, :created_at)'
    );
    for ($i = 1; $i <= 80; $i++) {
        $percent = random_int(1, 100) <= 60;
        $insertVoucher->execute([
            ':code' => sprintf('PROMO%03d', $i),
            ':name' => 'Voucher Dummy ' . $i,
            ':type' => $percent ? 'percent' : 'amount',
            ':value' => $percent ? random_int(5, 30) : random_int(5000, 35000),
            ':min_total' => random_int(30000, 150000),
            ':max_discount' => $percent ? random_int(15000, 80000) : 0,
            ':expires' => date('Y-m-d', strtotime('+' . random_int(30, 365) . ' days')),
            ':active' => random_int(1, 100) <= 85 ? 1 : 0,
            ':created_at' => rand_datetime(strtotime('-120 days'), time()),
        ]);
    }

    $pdo->exec(
        "INSERT INTO notification_settings
        (telegram_enabled, telegram_token, telegram_chat_id, whatsapp_enabled, whatsapp_api_url, whatsapp_api_token, whatsapp_session, whatsapp_target_id, whatsapp_target_name,
         telegram_include_logo, telegram_include_items, telegram_format, telegram_notify_transaction, telegram_notify_refund, telegram_notify_shift, telegram_daily_recap_enabled,
         telegram_daily_recap_time, telegram_daily_recap_last_sent, telegram_notify_low_stock)
         VALUES
        (0, 'dummy_token', '123456789', 0, 'https://api.example.test/send', 'dummy_wa_token', 'dummy_session', '081200000000', 'Admin Dummy',
         1, 1, 'detail', 1, 1, 1, 1, '21:00', NULL, 1)"
    );

    $insertShift = $pdo->prepare(
        'INSERT INTO shifts (shift_code, user_id, user_name, opened_at, opening_cash, note, closed_at, closing_cash, close_note, created_at)
         VALUES (:shift_code, :user_id, :user_name, :opened_at, :opening_cash, :note, :closed_at, :closing_cash, :close_note, :created_at)'
    );
    $insertShiftMovement = $pdo->prepare(
        'INSERT INTO shift_movements (shift_id, movement_type, amount, note, created_at)
         VALUES (:shift_id, :movement_type, :amount, :note, :created_at)'
    );

    $shifts = [];
    for ($d = 0; $d < 90; $d++) {
        $date = date('Y-m-d', strtotime('-' . (89 - $d) . ' days'));
        $patterns = [
            ['code' => 'A', 'open' => '07:00:00', 'close' => '15:00:00'],
            ['code' => 'B', 'open' => '15:00:00', 'close' => '23:00:00'],
        ];

        foreach ($patterns as $pattern) {
            $uid = $userIds[array_rand($userIds)];
            $openedAt = $date . ' ' . $pattern['open'];
            $closedAt = $date . ' ' . $pattern['close'];
            $openingCash = random_int(300000, 1200000);
            $closingCash = max(0, $openingCash + random_int(-100000, 350000));
            $shiftCode = 'SHIFT' . date('Ymd', strtotime($date)) . '-' . $pattern['code'];

            $insertShift->execute([
                ':shift_code' => $shiftCode,
                ':user_id' => $uid,
                ':user_name' => $userNames[$uid] ?? 'User',
                ':opened_at' => $openedAt,
                ':opening_cash' => $openingCash,
                ':note' => 'Shift dummy',
                ':closed_at' => $closedAt,
                ':closing_cash' => $closingCash,
                ':close_note' => 'Closed normally',
                ':created_at' => $openedAt,
            ]);

            $shiftId = (int) $pdo->lastInsertId();
            $shifts[] = ['id' => $shiftId, 'code' => $shiftCode];

            $moveCount = random_int(2, 5);
            for ($m = 0; $m < $moveCount; $m++) {
                $type = random_int(1, 100) <= 65 ? 'in' : 'out';
                $insertShiftMovement->execute([
                    ':shift_id' => $shiftId,
                    ':movement_type' => $type,
                    ':amount' => random_int(10000, 200000),
                    ':note' => $type === 'in' ? 'Cash in shift' : 'Operational expense',
                    ':created_at' => rand_datetime(strtotime($openedAt) + 1800, strtotime($closedAt) - 900),
                ]);
            }
        }
    }

    $insertCashDay = $pdo->prepare(
        'INSERT INTO cash_days (cash_date, opening_cash, opening_note, opening_set_by, opening_set_at, created_at)
         VALUES (:cash_date, :opening_cash, :opening_note, :opening_set_by, :opening_set_at, :created_at)'
    );
    $insertCashEntry = $pdo->prepare(
        'INSERT INTO cash_entries (cash_day_id, entry_type, amount, note, created_by, created_at)
         VALUES (:cash_day_id, :entry_type, :amount, :note, :created_by, :created_at)'
    );

    for ($d = 0; $d < 120; $d++) {
        $date = date('Y-m-d', strtotime('-' . (119 - $d) . ' days'));
        $setBy = $userIds[array_rand($userIds)];
        $insertCashDay->execute([
            ':cash_date' => $date,
            ':opening_cash' => random_int(250000, 900000),
            ':opening_note' => 'Opening cash seed',
            ':opening_set_by' => $setBy,
            ':opening_set_at' => $date . ' 06:45:00',
            ':created_at' => $date . ' 06:45:00',
        ]);

        $cashDayId = (int) $pdo->lastInsertId();
        $entryCount = random_int(5, 12);
        for ($e = 0; $e < $entryCount; $e++) {
            $type = weighted_pick([
                ['v' => 'in', 'w' => 50],
                ['v' => 'out', 'w' => 25],
                ['v' => 'expense', 'w' => 25],
            ]);

            $amount = match ($type) {
                'in' => random_int(20000, 350000),
                'out' => random_int(10000, 200000),
                default => random_int(5000, 150000),
            };

            $insertCashEntry->execute([
                ':cash_day_id' => $cashDayId,
                ':entry_type' => $type,
                ':amount' => $amount,
                ':note' => ucfirst($type) . ' dummy entry',
                ':created_by' => $userIds[array_rand($userIds)],
                ':created_at' => rand_datetime(strtotime($date . ' 07:00:00'), strtotime($date . ' 22:59:59')),
            ]);
        }
    }

    $insertTransaction = $pdo->prepare(
        'INSERT INTO transactions (user_id, total, note, created_at) VALUES (:user_id, :total, :note, :created_at)'
    );
    $insertTransactionItem = $pdo->prepare(
        'INSERT INTO transaction_items (transaction_id, product_id, qty, price) VALUES (:transaction_id, :product_id, :qty, :price)'
    );
    $insertPayment = $pdo->prepare(
        'INSERT INTO payments (transaction_id, method, amount, proof, created_at) VALUES (:transaction_id, :method, :amount, :proof, :created_at)'
    );
    $insertMeta = $pdo->prepare(
        'INSERT INTO transaction_meta (transaction_id, shift_code, payment_method, grand_total, total_before_rounding, meta_json, created_at)
         VALUES (:transaction_id, :shift_code, :payment_method, :grand_total, :total_before_rounding, :meta_json, :created_at)'
    );
    $insertPointLog = $pdo->prepare(
        'INSERT INTO customer_point_logs (customer_id, transaction_id, points_delta, amount, note, created_at)
         VALUES (:customer_id, :transaction_id, :points_delta, :amount, :note, :created_at)'
    );

    $transactions = [];
    $transactionItemsByTrx = [];
    $customerAgg = [];

    for ($t = 1; $t <= 1800; $t++) {
        $uid = $userIds[array_rand($userIds)];
        $createdAt = rand_datetime(strtotime('-90 days'), time());
        $itemCount = random_int(1, 6);

        $pickedIndexes = array_rand($products, $itemCount);
        if (!is_array($pickedIndexes)) {
            $pickedIndexes = [$pickedIndexes];
        }

        $subtotal = 0;
        $items = [];
        foreach ($pickedIndexes as $idx) {
            $product = $products[$idx];
            $qty = random_int(1, 4);
            $subtotal += $product['price'] * $qty;
            $items[] = ['product_id' => $product['id'], 'qty' => $qty, 'price' => $product['price']];
        }

        $discount = random_int(1, 100) <= 30 ? (int) round($subtotal * random_int(5, 20) / 100) : 0;
        $afterDiscount = max(0, $subtotal - $discount);
        $tax = (int) round($afterDiscount * 0.11);
        $service = (int) round($afterDiscount * 0.05);
        $totalBeforeRounding = $afterDiscount + $tax + $service;
        $grandTotal = (int) (round($totalBeforeRounding / 100) * 100);

        $insertTransaction->execute([
            ':user_id' => $uid,
            ':total' => $grandTotal,
            ':note' => random_int(1, 100) <= 15 ? 'Order online' : 'Walk-in customer',
            ':created_at' => $createdAt,
        ]);
        $transactionId = (int) $pdo->lastInsertId();

        foreach ($items as $item) {
            $insertTransactionItem->execute([
                ':transaction_id' => $transactionId,
                ':product_id' => $item['product_id'],
                ':qty' => $item['qty'],
                ':price' => $item['price'],
            ]);

            $currentStock = $inventoryState[$item['product_id']]['stock'];
            $minStock = $inventoryState[$item['product_id']]['min_stock'];
            $newStock = max(0, $currentStock - $item['qty']);
            $inventoryState[$item['product_id']]['stock'] = $newStock;

            $insertInventoryLog->execute([
                ':product_id' => $item['product_id'],
                ':delta' => -$item['qty'],
                ':stock' => $newStock,
                ':min_stock' => $minStock,
                ':reason' => 'sale',
                ':ref' => 'TRX-' . $transactionId,
                ':note' => 'Auto reduce from transaction',
                ':user_id' => $uid,
                ':created_at' => $createdAt,
            ]);
        }

        $method = random_int(1, 100) <= 68 ? 'cash' : 'qris';
        $insertPayment->execute([
            ':transaction_id' => $transactionId,
            ':method' => $method,
            ':amount' => $grandTotal,
            ':proof' => $method === 'qris' ? ('qris-' . $transactionId . '.jpg') : null,
            ':created_at' => $createdAt,
        ]);

        $shift = $shifts[array_rand($shifts)];
        $insertMeta->execute([
            ':transaction_id' => $transactionId,
            ':shift_code' => $shift['code'],
            ':payment_method' => $method,
            ':grand_total' => $grandTotal,
            ':total_before_rounding' => $totalBeforeRounding,
            ':meta_json' => json_encode(
                [
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'service' => $service,
                    'items_count' => count($items),
                    'seed' => true,
                ],
                JSON_UNESCAPED_UNICODE
            ),
            ':created_at' => $createdAt,
        ]);

        if (random_int(1, 100) <= 45) {
            $customerId = $customers[array_rand($customers)];
            $points = (int) floor($grandTotal / 10000);
            if ($points > 0) {
                $insertPointLog->execute([
                    ':customer_id' => $customerId,
                    ':transaction_id' => $transactionId,
                    ':points_delta' => $points,
                    ':amount' => $grandTotal,
                    ':note' => 'Point dari transaksi',
                    ':created_at' => $createdAt,
                ]);

                if (!isset($customerAgg[$customerId])) {
                    $customerAgg[$customerId] = [
                        'points' => 0,
                        'spent' => 0,
                        'visits' => 0,
                        'last' => $createdAt,
                    ];
                }

                $customerAgg[$customerId]['points'] += $points;
                $customerAgg[$customerId]['spent'] += $grandTotal;
                $customerAgg[$customerId]['visits']++;
                if ($createdAt > $customerAgg[$customerId]['last']) {
                    $customerAgg[$customerId]['last'] = $createdAt;
                }
            }
        }

        $transactions[] = [
            'id' => $transactionId,
            'total' => $grandTotal,
            'created_at' => $createdAt,
            'user_id' => $uid,
        ];
        $transactionItemsByTrx[$transactionId] = $items;
    }

    $updateCustomer = $pdo->prepare(
        'UPDATE customers
         SET points = points + :points,
             total_spent = total_spent + :spent,
             total_visits = total_visits + :visits,
             last_transaction_at = :last_at
         WHERE id = :id'
    );
    foreach ($customerAgg as $customerId => $agg) {
        $updateCustomer->execute([
            ':points' => $agg['points'],
            ':spent' => $agg['spent'],
            ':visits' => $agg['visits'],
            ':last_at' => $agg['last'],
            ':id' => $customerId,
        ]);
    }

    $insertRefund = $pdo->prepare(
        'INSERT INTO refunds (refund_code, transaction_id, amount, method, reason, restock, user_id, shift_code, created_at)
         VALUES (:refund_code, :transaction_id, :amount, :method, :reason, :restock, :user_id, :shift_code, :created_at)'
    );
    $insertRefundItem = $pdo->prepare(
        'INSERT INTO refund_items (refund_id, product_id, qty, created_at) VALUES (:refund_id, :product_id, :qty, :created_at)'
    );

    $reasons = [
        'Produk tidak sesuai',
        'Kualitas produk kurang baik',
        'Pesanan ganda',
        'Salah input kasir',
        'Pelanggan batal',
        'Kemasan rusak',
    ];

    for ($r = 1; $r <= 220; $r++) {
        $trx = $transactions[array_rand($transactions)];
        $createdAt = date('Y-m-d H:i:s', strtotime($trx['created_at'] . ' +' . random_int(1, 72) . ' hours'));
        $restock = random_int(1, 100) <= 65 ? 1 : 0;
        $amount = (int) max(5000, round($trx['total'] * random_int(20, 80) / 100));

        $insertRefund->execute([
            ':refund_code' => 'RFN' . date('Ymd', strtotime($createdAt)) . sprintf('%04d', $r),
            ':transaction_id' => $trx['id'],
            ':amount' => $amount,
            ':method' => random_int(1, 100) <= 70 ? 'cash' : 'qris',
            ':reason' => $reasons[array_rand($reasons)],
            ':restock' => $restock,
            ':user_id' => $trx['user_id'],
            ':shift_code' => $shifts[array_rand($shifts)]['code'],
            ':created_at' => $createdAt,
        ]);
        $refundId = (int) $pdo->lastInsertId();

        $trxItems = $transactionItemsByTrx[$trx['id']] ?? [];
        if (!$trxItems) {
            continue;
        }

        shuffle($trxItems);
        $picked = array_slice($trxItems, 0, random_int(1, min(2, count($trxItems))));
        foreach ($picked as $item) {
            $qty = random_int(1, max(1, (int) $item['qty']));
            $insertRefundItem->execute([
                ':refund_id' => $refundId,
                ':product_id' => $item['product_id'],
                ':qty' => $qty,
                ':created_at' => $createdAt,
            ]);

            if ($restock === 1) {
                $currentStock = $inventoryState[$item['product_id']]['stock'];
                $minStock = $inventoryState[$item['product_id']]['min_stock'];
                $newStock = $currentStock + $qty;
                $inventoryState[$item['product_id']]['stock'] = $newStock;

                $insertInventoryLog->execute([
                    ':product_id' => $item['product_id'],
                    ':delta' => $qty,
                    ':stock' => $newStock,
                    ':min_stock' => $minStock,
                    ':reason' => 'refund',
                    ':ref' => 'RFN-' . $refundId,
                    ':note' => 'Restock dari refund',
                    ':user_id' => $trx['user_id'],
                    ':created_at' => $createdAt,
                ]);
            }
        }
    }

    $updateInventory = $pdo->prepare('UPDATE inventory_items SET stock = :stock WHERE product_id = :product_id');
    foreach ($inventoryState as $productId => $state) {
        $updateInventory->execute([
            ':stock' => $state['stock'],
            ':product_id' => $productId,
        ]);
    }

    $pdo->commit();

    $summaryTables = [
        'users',
        'categories',
        'products',
        'customers',
        'transactions',
        'transaction_items',
        'payments',
        'transaction_meta',
        'refunds',
        'refund_items',
        'inventory_items',
        'inventory_logs',
        'shifts',
        'shift_movements',
        'cash_days',
        'cash_entries',
        'promo_settings',
        'promo_vouchers',
        'customer_point_logs',
        'notification_settings',
    ];

    echo "SEED BERHASIL\n";
    foreach ($summaryTables as $table) {
        if (!table_exists($pdo, $table)) {
            continue;
        }
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        echo str_pad($table, 22, ' ', STR_PAD_RIGHT) . ': ' . $count . PHP_EOL;
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "SEED GAGAL: {$e->getMessage()}\n");
    exit(1);
}
