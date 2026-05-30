<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Product.php';

$pdo = db();

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table'
    );
    $stmt->execute([':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column'
    );
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function ensure_role(PDO $pdo, string $role): void
{
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $role]);
    if (!$stmt->fetchColumn()) {
        $insert = $pdo->prepare('INSERT INTO roles (name) VALUES (:name)');
        $insert->execute([':name' => $role]);
    }
}

function ensure_category(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $insert = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
    $insert->execute([':name' => $name]);
    return (int) $pdo->lastInsertId();
}

function ensure_user(PDO $pdo, array $data): void
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $data['username']]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $columns = array_keys($data);
    $placeholders = array_map(static fn ($col) => ':' . $col, $columns);
    $sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $insert = $pdo->prepare($sql);
    $params = [];
    foreach ($data as $column => $value) {
        $params[':' . $column] = $value;
    }
    $insert->execute($params);
}

function ensure_product(PDO $pdo, array $data): void
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $data['name']]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $columns = array_keys($data);
    $placeholders = array_map(static fn ($col) => ':' . $col, $columns);
    $sql = 'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $insert = $pdo->prepare($sql);
    $params = [];
    foreach ($data as $column => $value) {
        $params[':' . $column] = $value;
    }
    $insert->execute($params);
}

if (!table_exists($pdo, 'roles') || !table_exists($pdo, 'users')) {
    echo "Tabel roles/users tidak ditemukan.\n";
    exit(1);
}

ensure_role($pdo, 'admin');
ensure_role($pdo, 'bos');
ensure_role($pdo, 'karyawan');
ensure_role($pdo, 'superadmin');

$roleStmt = $pdo->query('SELECT id, name FROM roles');
$roleMap = [];
foreach ($roleStmt->fetchAll() as $row) {
    $roleMap[$row['name']] = (int) $row['id'];
}

$passwordColumn = User::passwordColumn($pdo);
if (!$passwordColumn) {
    echo "Kolom password tidak ditemukan pada tabel users.\n";
    exit(1);
}

$activeColumn = User::activeColumn($pdo);

$users = [
    [
        'name' => 'Administrator',
        'username' => 'admin',
        'role' => 'admin',
        'password' => 'admin123',
    ],
    [
        'name' => 'Super Admin',
        'username' => 'superadmin',
        'role' => 'superadmin',
        'password' => 'superadmin123',
    ],
    [
        'name' => 'Owner Cafe',
        'username' => 'owner',
        'role' => 'bos',
        'password' => 'bos123',
    ],
    [
        'name' => 'Kasir Utama',
        'username' => 'kasir',
        'role' => 'karyawan',
        'password' => 'kasir123',
    ],
];

foreach ($users as $user) {
    $data = [
        'role_id' => $roleMap[$user['role']] ?? 0,
        'name' => $user['name'],
        'username' => $user['username'],
        $passwordColumn => password_hash($user['password'], PASSWORD_DEFAULT),
    ];

    if ($activeColumn) {
        $data[$activeColumn] = 1;
    }

    if ($data['role_id'] > 0) {
        ensure_user($pdo, $data);
    }
}

if (table_exists($pdo, 'categories') && column_exists($pdo, 'categories', 'name')) {
    $categoryMap = [
        'Coffee' => null,
        'Non Coffee' => null,
        'Snack' => null,
    ];

    foreach (array_keys($categoryMap) as $categoryName) {
        $categoryMap[$categoryName] = ensure_category($pdo, $categoryName);
    }

    if (table_exists($pdo, 'products')) {
        $categoryColumn = Product::categoryColumn($pdo);
        $skuColumn = Product::skuColumn($pdo);
        $activeColumn = Product::activeColumn($pdo);
        $costColumn = Product::costColumn($pdo);
        $stockColumn = Product::stockColumn($pdo);

        $products = [
            ['name' => 'Espresso', 'price' => 18000, 'cost_price' => 12000, 'stock' => 30, 'category' => 'Coffee', 'sku' => 'CFE-ESP'],
            ['name' => 'Cappuccino', 'price' => 24000, 'cost_price' => 16000, 'stock' => 25, 'category' => 'Coffee', 'sku' => 'CFE-CAP'],
            ['name' => 'Caramel Latte', 'price' => 26000, 'cost_price' => 17000, 'stock' => 20, 'category' => 'Coffee', 'sku' => 'CFE-CLT'],
            ['name' => 'Matcha Latte', 'price' => 25000, 'cost_price' => 16500, 'stock' => 20, 'category' => 'Non Coffee', 'sku' => 'NCF-MTC'],
            ['name' => 'Chocolate', 'price' => 22000, 'cost_price' => 14000, 'stock' => 20, 'category' => 'Non Coffee', 'sku' => 'NCF-CHO'],
            ['name' => 'Butter Croissant', 'price' => 15000, 'cost_price' => 9500, 'stock' => 18, 'category' => 'Snack', 'sku' => 'SNK-CRT'],
        ];

        foreach ($products as $product) {
            $data = [
                'name' => $product['name'],
                'price' => $product['price'],
            ];

            if ($categoryColumn) {
                $data[$categoryColumn] = $categoryMap[$product['category']] ?? null;
            }

            if ($skuColumn) {
                $data[$skuColumn] = $product['sku'];
            }

            if ($costColumn) {
                $data[$costColumn] = $product['cost_price'] ?? 0;
            }

            if ($stockColumn) {
                $data[$stockColumn] = $product['stock'] ?? 0;
            }

            if ($activeColumn) {
                $data[$activeColumn] = 1;
            }

            ensure_product($pdo, $data);
        }
    }
}

echo "Seed selesai. Akun default: superadmin/superadmin123, admin/admin123, owner/bos123, kasir/kasir123\n";
