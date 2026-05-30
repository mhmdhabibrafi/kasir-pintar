<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function ensure_update_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $pdo = db();
    } catch (Throwable $e) {
        return;
    }

    $databaseName = '';
    try {
        $databaseName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
    } catch (Throwable $e) {
        return;
    }

    if ($databaseName === '') {
        return;
    }

    $dbHost = trim((string) (getenv('DB_HOST') ?: 'localhost'));
    $dbPort = trim((string) (getenv('DB_PORT') ?: '3306'));
    $dbUser = (string) (getenv('DB_USER') ?: 'root');
    $dbPass = (string) (getenv('DB_PASS') ?: '');
    $dbCharset = trim((string) (getenv('DB_CHARSET') ?: 'utf8mb4'));
    $dbSocket = trim((string) (getenv('DB_SOCKET') ?: ''));

    try {
        if ($dbSocket !== '') {
            $dsn = sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=%s',
                $dbSocket,
                $databaseName,
                $dbCharset
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $dbHost,
                $dbPort,
                $databaseName,
                $dbCharset
            );
        }

        $migrationPdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        return;
    }

    $migrationPdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(80) PRIMARY KEY,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shift_code VARCHAR(30) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  user_name VARCHAR(100) NOT NULL,
  opened_at DATETIME NOT NULL,
  opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL,
  closed_at DATETIME DEFAULT NULL,
  closing_cash DECIMAL(12,2) DEFAULT NULL,
  close_note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_shifts_user (user_id),
  INDEX idx_shifts_opened (opened_at),
  INDEX idx_shifts_closed (closed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shift_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shift_id INT NOT NULL,
  movement_type ENUM('in','out') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_shift_movements_shift (shift_id),
  INDEX idx_shift_movements_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL UNIQUE,
  stock INT DEFAULT NULL,
  min_stock INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_inventory_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  delta INT DEFAULT NULL,
  stock INT DEFAULT NULL,
  min_stock INT NOT NULL DEFAULT 0,
  reason VARCHAR(50) NOT NULL,
  ref VARCHAR(50) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inventory_logs_product (product_id),
  INDEX idx_inventory_logs_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promo_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  service_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  rounding_mode ENUM('none','nearest','up','down') NOT NULL DEFAULT 'none',
  rounding_unit INT NOT NULL DEFAULT 100,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promo_vouchers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) DEFAULT NULL,
  type ENUM('amount','percent') NOT NULL DEFAULT 'amount',
  value DECIMAL(12,2) NOT NULL DEFAULT 0,
  min_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  max_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  expires DATE DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_promo_vouchers_active (active),
  INDEX idx_promo_vouchers_expires (expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_code VARCHAR(30) NOT NULL UNIQUE,
  store_name VARCHAR(100) NOT NULL,
  store_tagline VARCHAR(180) DEFAULT '',
  store_address VARCHAR(255) DEFAULT '',
  store_phone VARCHAR(50) DEFAULT '',
  store_email VARCHAR(120) DEFAULT '',
  store_whatsapp VARCHAR(50) DEFAULT '',
  store_instagram VARCHAR(80) DEFAULT '',
  store_city VARCHAR(100) DEFAULT '',
  store_province VARCHAR(100) DEFAULT '',
  postal_code VARCHAR(20) DEFAULT '',
  business_hours VARCHAR(120) DEFAULT '',
  google_maps_url VARCHAR(255) DEFAULT '',
  receipt_footer VARCHAR(160) DEFAULT 'Terima kasih!',
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  owner_name VARCHAR(100) NOT NULL,
  owner_email VARCHAR(120) DEFAULT '',
  owner_phone VARCHAR(50) DEFAULT '',
  admin_name VARCHAR(100) NOT NULL,
  admin_username VARCHAR(50) NOT NULL UNIQUE,
  admin_password_hash VARCHAR(255) NOT NULL,
  approval_note VARCHAR(255) DEFAULT NULL,
  approved_by INT DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  rejected_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_stores_status (status),
  INDEX idx_stores_name (store_name),
  INDEX idx_stores_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS store_referral_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL,
  note VARCHAR(255) DEFAULT '',
  max_uses INT NOT NULL DEFAULT 1,
  used_count INT NOT NULL DEFAULT 0,
  expires_at DATETIME DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  generated_by INT DEFAULT NULL,
  last_used_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_store_referral_active (is_active),
  INDEX idx_store_referral_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS refunds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  refund_code VARCHAR(40) NOT NULL UNIQUE,
  transaction_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('cash','qris') NOT NULL,
  reason VARCHAR(255) NOT NULL,
  restock TINYINT(1) NOT NULL DEFAULT 0,
  user_id INT NOT NULL,
  shift_code VARCHAR(30) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_refunds_transaction (transaction_id),
  INDEX idx_refunds_created (created_at),
  INDEX idx_refunds_method (method),
  INDEX idx_refunds_shift (shift_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS refund_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  refund_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_refund_items_refund (refund_id),
  INDEX idx_refund_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transaction_meta (
  transaction_id INT PRIMARY KEY,
  shift_code VARCHAR(30) DEFAULT NULL,
  payment_method ENUM('cash','qris') DEFAULT NULL,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_before_rounding DECIMAL(12,2) NOT NULL DEFAULT 0,
  meta_json LONGTEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_transaction_meta_shift (shift_code),
  INDEX idx_transaction_meta_method (payment_method),
  INDEX idx_transaction_meta_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  email VARCHAR(120) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  points INT NOT NULL DEFAULT 0,
  total_spent DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_visits INT NOT NULL DEFAULT 0,
  last_transaction_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customers_phone (phone),
  INDEX idx_customers_name (name),
  INDEX idx_customers_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_point_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  transaction_id INT DEFAULT NULL,
  points_delta INT NOT NULL DEFAULT 0,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer_point_customer (customer_id),
  INDEX idx_customer_point_trx (transaction_id),
  INDEX idx_customer_point_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $migrationPdo->exec($statement);
    }

    ensure_table_column_exists($migrationPdo, 'users', 'store_id', 'INT DEFAULT NULL');
    ensure_table_column_exists($migrationPdo, 'users', 'permissions_json', 'LONGTEXT DEFAULT NULL');
    ensure_table_column_exists($migrationPdo, 'users', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    ensure_table_index_exists($migrationPdo, 'users', 'idx_users_store_id', 'CREATE INDEX idx_users_store_id ON users (store_id)');
    ensure_table_index_exists($migrationPdo, 'users', 'idx_users_is_active', 'CREATE INDEX idx_users_is_active ON users (is_active)');
    ensure_table_column_exists($migrationPdo, 'stores', 'referral_code_id', 'INT DEFAULT NULL');
    ensure_table_column_exists($migrationPdo, 'stores', 'referral_code', 'VARCHAR(40) DEFAULT \'\'');
    ensure_table_column_exists($migrationPdo, 'stores', 'store_whatsapp', 'VARCHAR(50) DEFAULT \'\'');
    ensure_table_column_exists($migrationPdo, 'stores', 'store_instagram', 'VARCHAR(80) DEFAULT \'\'');
    ensure_table_column_exists($migrationPdo, 'stores', 'store_city', 'VARCHAR(100) DEFAULT \'\'');
    ensure_table_column_exists($migrationPdo, 'stores', 'store_province', 'VARCHAR(100) DEFAULT \'\'');
    ensure_table_column_exists($migrationPdo, 'stores', 'postal_code', 'VARCHAR(20) DEFAULT \'\'');
    ensure_table_column_exists($migrationPdo, 'stores', 'business_hours', 'VARCHAR(120) DEFAULT \'\'');
    ensure_table_column_exists($migrationPdo, 'stores', 'google_maps_url', 'VARCHAR(255) DEFAULT \'\'');
    ensure_table_column_exists($migrationPdo, 'stores', 'operational_status', 'VARCHAR(20) NOT NULL DEFAULT \'active\'');
    ensure_table_column_exists($migrationPdo, 'stores', 'operational_note', 'VARCHAR(255) DEFAULT NULL');
    ensure_table_column_exists($migrationPdo, 'stores', 'suspended_at', 'DATETIME DEFAULT NULL');
    ensure_table_column_exists($migrationPdo, 'stores', 'suspended_by', 'INT DEFAULT NULL');
    ensure_table_column_exists($migrationPdo, 'stores', 'last_login_at', 'DATETIME DEFAULT NULL');
    ensure_table_index_exists($migrationPdo, 'stores', 'idx_stores_referral_code_id', 'CREATE INDEX idx_stores_referral_code_id ON stores (referral_code_id)');
    ensure_table_index_exists($migrationPdo, 'stores', 'idx_stores_operational_status', 'CREATE INDEX idx_stores_operational_status ON stores (operational_status)');
    ensure_table_index_exists($migrationPdo, 'stores', 'idx_stores_last_login_at', 'CREATE INDEX idx_stores_last_login_at ON stores (last_login_at)');
    ensure_store_domain_schema($migrationPdo);
    ensure_api_token_schema($migrationPdo);
    ensure_role_exists($migrationPdo, 'superadmin');
    ensure_default_superadmin_account($migrationPdo);
    ensure_default_demo_store($migrationPdo);
    ensure_internal_demo_referral_consistency($migrationPdo);
    ensure_tenant_schema($migrationPdo);
}

function ensure_tenant_schema(PDO $pdo): void
{
    $defaultStoreId = ensure_default_demo_store_id($pdo);
    $tenantTables = [
        'categories',
        'products',
        'transactions',
        'transaction_items',
        'transaction_meta',
        'payments',
        'shifts',
        'shift_movements',
        'inventory_items',
        'inventory_logs',
        'promo_settings',
        'promo_vouchers',
        'customers',
        'customer_point_logs',
        'refunds',
        'refund_items',
        'notification_settings',
    ];

    foreach ($tenantTables as $table) {
        if (!ensure_table_exists($pdo, $table)) {
            continue;
        }
        ensure_table_column_exists($pdo, $table, 'store_id', 'INT DEFAULT NULL');
        ensure_table_index_exists($pdo, $table, 'idx_' . $table . '_store_id', 'CREATE INDEX idx_' . $table . '_store_id ON ' . $table . ' (store_id)');
        $stmt = $pdo->prepare('UPDATE ' . $table . ' SET store_id = :store_id WHERE store_id IS NULL OR store_id = 0');
        $stmt->execute([':store_id' => $defaultStoreId]);
    }

    ensure_tenant_backfill_from_parent($pdo, 'transaction_items', 'transactions', 'transaction_id');
    ensure_tenant_backfill_from_parent($pdo, 'transaction_meta', 'transactions', 'transaction_id');
    ensure_tenant_backfill_from_parent($pdo, 'payments', 'transactions', 'transaction_id');
    ensure_tenant_backfill_from_parent($pdo, 'shift_movements', 'shifts', 'shift_id', 'id');
    ensure_tenant_backfill_from_parent($pdo, 'refund_items', 'refunds', 'refund_id');
    ensure_tenant_backfill_from_parent($pdo, 'customer_point_logs', 'customers', 'customer_id');

    ensure_tenant_unique_index($pdo, 'categories', ['store_id', 'name'], ['name']);
    ensure_tenant_unique_index($pdo, 'products', ['store_id', 'sku'], ['sku']);
    ensure_tenant_unique_index($pdo, 'inventory_items', ['store_id', 'product_id'], ['product_id']);
    ensure_tenant_unique_index($pdo, 'promo_vouchers', ['store_id', 'code'], ['code']);
    ensure_tenant_unique_index($pdo, 'customers', ['store_id', 'phone'], ['uq_customers_phone', 'phone']);
    ensure_tenant_unique_index($pdo, 'promo_settings', ['store_id'], []);
    ensure_table_index_exists($pdo, 'shifts', 'idx_shifts_store_closed', 'CREATE INDEX idx_shifts_store_closed ON shifts (store_id, closed_at)');
    ensure_table_index_exists($pdo, 'shifts', 'idx_shifts_store_opened', 'CREATE INDEX idx_shifts_store_opened ON shifts (store_id, opened_at)');
    ensure_table_index_exists($pdo, 'shift_movements', 'idx_shift_movements_store_shift', 'CREATE INDEX idx_shift_movements_store_shift ON shift_movements (store_id, shift_id)');
    ensure_table_index_exists($pdo, 'transaction_meta', 'idx_transaction_meta_store_shift', 'CREATE INDEX idx_transaction_meta_store_shift ON transaction_meta (store_id, shift_code)');
    ensure_table_index_exists($pdo, 'refunds', 'idx_refunds_store_shift', 'CREATE INDEX idx_refunds_store_shift ON refunds (store_id, shift_code)');

    ensure_schema_migration_recorded($pdo, '001_create_schema_migrations');
    ensure_schema_migration_recorded($pdo, '002_store_multitenant_backfill');
}

function ensure_store_domain_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS store_domains (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL,
            domain VARCHAR(190) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "active",
            verified_at DATETIME DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_store_domains_domain (domain),
            INDEX idx_store_domains_store_id (store_id),
            INDEX idx_store_domains_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    ensure_table_column_exists($pdo, 'store_domains', 'store_id', 'INT NOT NULL');
    ensure_table_column_exists($pdo, 'store_domains', 'domain', 'VARCHAR(190) NOT NULL');
    ensure_table_column_exists($pdo, 'store_domains', 'status', 'VARCHAR(20) NOT NULL DEFAULT "active"');
    ensure_table_column_exists($pdo, 'store_domains', 'verified_at', 'DATETIME DEFAULT NULL');
    ensure_table_column_exists($pdo, 'store_domains', 'created_by', 'INT DEFAULT NULL');
    ensure_table_column_exists($pdo, 'store_domains', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    ensure_table_column_exists($pdo, 'store_domains', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    ensure_table_index_exists($pdo, 'store_domains', 'uq_store_domains_domain', 'CREATE UNIQUE INDEX uq_store_domains_domain ON store_domains (domain)');
    ensure_table_index_exists($pdo, 'store_domains', 'idx_store_domains_store_id', 'CREATE INDEX idx_store_domains_store_id ON store_domains (store_id)');
    ensure_table_index_exists($pdo, 'store_domains', 'idx_store_domains_status', 'CREATE INDEX idx_store_domains_status ON store_domains (status)');
    ensure_schema_migration_recorded($pdo, '003_store_custom_domains');
}

function ensure_api_token_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS api_tokens (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            device_name VARCHAR(120) DEFAULT NULL,
            last_used_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            revoked_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_api_tokens_user_id (user_id),
            INDEX idx_api_tokens_expires_at (expires_at),
            INDEX idx_api_tokens_revoked_at (revoked_at),
            CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    ensure_table_column_exists($pdo, 'api_tokens', 'user_id', 'INT NOT NULL');
    ensure_table_column_exists($pdo, 'api_tokens', 'token_hash', 'CHAR(64) NOT NULL');
    ensure_table_column_exists($pdo, 'api_tokens', 'device_name', 'VARCHAR(120) DEFAULT NULL');
    ensure_table_column_exists($pdo, 'api_tokens', 'last_used_at', 'DATETIME DEFAULT NULL');
    ensure_table_column_exists($pdo, 'api_tokens', 'expires_at', 'DATETIME DEFAULT NULL');
    ensure_table_column_exists($pdo, 'api_tokens', 'revoked_at', 'DATETIME DEFAULT NULL');
    ensure_table_column_exists($pdo, 'api_tokens', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    ensure_table_index_exists($pdo, 'api_tokens', 'idx_api_tokens_user_id', 'CREATE INDEX idx_api_tokens_user_id ON api_tokens (user_id)');
    ensure_table_index_exists($pdo, 'api_tokens', 'idx_api_tokens_expires_at', 'CREATE INDEX idx_api_tokens_expires_at ON api_tokens (expires_at)');
    ensure_table_index_exists($pdo, 'api_tokens', 'idx_api_tokens_revoked_at', 'CREATE INDEX idx_api_tokens_revoked_at ON api_tokens (revoked_at)');
}

function ensure_schema_migration_recorded(PDO $pdo, string $version): void
{
    if (!ensure_table_exists($pdo, 'schema_migrations')) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO schema_migrations (version, applied_at)
         VALUES (:version, NOW())'
    );
    $stmt->execute([':version' => $version]);
}

function ensure_default_demo_store_id(PDO $pdo): int
{
    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE store_code = 'KSPDMO0001' LIMIT 1");
        $stmt->execute();
        $storeId = (int) $stmt->fetchColumn();
        if ($storeId > 0) {
            return $storeId;
        }

        $stmt = $pdo->query('SELECT id FROM stores ORDER BY id ASC LIMIT 1');
        $storeId = (int) $stmt->fetchColumn();
        if ($storeId > 0) {
            return $storeId;
        }
    } catch (Throwable $e) {
        return 1;
    }

    return 1;
}

function ensure_table_exists(PDO $pdo, string $table): bool
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

function ensure_tenant_backfill_from_parent(
    PDO $pdo,
    string $childTable,
    string $parentTable,
    string $childForeignKey,
    string $parentKey = 'id'
): void {
    if (
        !ensure_table_exists($pdo, $childTable)
        || !ensure_table_exists($pdo, $parentTable)
    ) {
        return;
    }

    $pdo->exec(
        'UPDATE ' . $childTable . ' AS child
         INNER JOIN ' . $parentTable . ' AS parent ON parent.' . $parentKey . ' = child.' . $childForeignKey . '
         SET child.store_id = parent.store_id
         WHERE parent.store_id IS NOT NULL'
    );
}

function ensure_tenant_unique_index(PDO $pdo, string $table, array $columns, array $legacyIndexNames): void
{
    if (!ensure_table_exists($pdo, $table)) {
        return;
    }

    foreach ($columns as $column) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table
               AND column_name = :column'
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            return;
        }
    }

    foreach ($legacyIndexNames as $indexName) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = :table
               AND index_name = :index_name
               AND non_unique = 0'
        );
        $stmt->execute([':table' => $table, ':index_name' => $indexName]);
        if ((int) $stmt->fetchColumn() > 0) {
            try {
                $pdo->exec('ALTER TABLE ' . $table . ' DROP INDEX ' . $indexName);
            } catch (Throwable $e) {
                // If MySQL refuses to drop a compatibility index, keep going.
            }
        }
    }

    $indexName = 'uq_' . $table . '_' . implode('_', $columns);
    $columnSql = implode(', ', $columns);
    ensure_table_index_exists($pdo, $table, $indexName, 'CREATE UNIQUE INDEX ' . $indexName . ' ON ' . $table . ' (' . $columnSql . ')');
}

function ensure_table_column_exists(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column'
    );
    $stmt->execute([
        ':table' => $table,
        ':column' => $column,
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $pdo->exec(sprintf(
        'ALTER TABLE %s ADD COLUMN %s %s',
        $table,
        $column,
        $definition
    ));
}

function ensure_table_index_exists(PDO $pdo, string $table, string $indexName, string $createSql): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND index_name = :index_name'
    );
    $stmt->execute([
        ':table' => $table,
        ':index_name' => $indexName,
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $pdo->exec($createSql);
}

function ensure_role_exists(PDO $pdo, string $roleName): void
{
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $roleName]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO roles (name) VALUES (:name)');
    $insert->execute([':name' => $roleName]);
}

function ensure_default_superadmin_account(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => 'superadmin']);
    if ($stmt->fetchColumn()) {
        return;
    }

    $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $roleStmt->execute([':name' => 'superadmin']);
    $roleId = (int) $roleStmt->fetchColumn();
    if ($roleId <= 0) {
        return;
    }

    $passwordColumn = 'password_hash';
    $activeColumn = null;
    try {
        $columnStmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'users'
               AND column_name IN ('password_hash', 'password')
             ORDER BY FIELD(COLUMN_NAME, 'password_hash', 'password')
             LIMIT 1"
        );
        $columnStmt->execute();
        $passwordColumn = (string) ($columnStmt->fetchColumn() ?: 'password_hash');

        $activeStmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'users'
               AND column_name IN ('is_active', 'active', 'status')
             ORDER BY FIELD(COLUMN_NAME, 'is_active', 'active', 'status')
             LIMIT 1"
        );
        $activeStmt->execute();
        $activeColumn = $activeStmt->fetchColumn() ?: null;
    } catch (Throwable $e) {
        $activeColumn = null;
    }

    $columns = ['role_id', 'name', 'username', $passwordColumn];
    $placeholders = [':role_id', ':name', ':username', ':password_hash'];
    $params = [
        ':role_id' => $roleId,
        ':name' => 'Super Admin',
        ':username' => 'superadmin',
        ':password_hash' => password_hash('superadmin123', PASSWORD_DEFAULT),
    ];

    if ($activeColumn) {
        $columns[] = $activeColumn;
        $placeholders[] = ':active';
        $params[':active'] = 1;
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', $placeholders) . ')'
    );
    $insert->execute($params);
}

function ensure_default_demo_store(PDO $pdo): void
{
    $storeLookup = $pdo->prepare("SELECT id FROM stores WHERE store_code = 'KSPDMO0001' LIMIT 1");
    $storeLookup->execute();
    $storeId = (int) $storeLookup->fetchColumn();

    if ($storeId <= 0) {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM stores');
        if ((int) $countStmt->fetchColumn() > 0) {
            return;
        }

        $demoReferralId = ensure_default_demo_referral($pdo);
        $storeInsert = $pdo->prepare(
            "INSERT INTO stores (
                store_code, store_name, store_tagline, store_address, store_phone, store_email, receipt_footer,
                status, owner_name, owner_email, owner_phone,
                admin_name, admin_username, admin_password_hash,
                approved_by, approved_at, referral_code_id, referral_code
             ) VALUES (
                :store_code, :store_name, :store_tagline, :store_address, :store_phone, :store_email, :receipt_footer,
                :status, :owner_name, :owner_email, :owner_phone,
                :admin_name, :admin_username, :admin_password_hash,
                :approved_by, NOW(), :referral_code_id, :referral_code
             )"
        );

        $storeInsert->execute([
            ':store_code' => 'KSPDMO0001',
            ':store_name' => 'KASPINDO Demo Store',
            ':store_tagline' => 'Toko dummy aktif untuk onboarding awal sistem',
            ':store_address' => 'Jl. Demo Kaspindo No. 1',
            ':store_phone' => '081200000001',
            ':store_email' => 'demo-store@kaspindo.local',
            ':receipt_footer' => 'Terima kasih!',
            ':status' => 'approved',
            ':owner_name' => 'Tim Kaspindo',
            ':owner_email' => 'owner-demo@kaspindo.local',
            ':owner_phone' => '081200000002',
            ':admin_name' => 'Administrator',
            ':admin_username' => 'admin',
            ':admin_password_hash' => '',
            ':approved_by' => null,
            ':referral_code_id' => $demoReferralId > 0 ? $demoReferralId : null,
            ':referral_code' => 'INTERNAL-DEMO',
        ]);

        $storeId = (int) $pdo->lastInsertId();
        if ($storeId <= 0) {
            return;
        }

        ensure_referral_usage_count($pdo, $demoReferralId, 'INTERNAL-DEMO');
    } else {
        $demoReferralId = ensure_default_demo_referral($pdo);
        $backfillStore = $pdo->prepare(
            'UPDATE stores
             SET referral_code_id = COALESCE(referral_code_id, :referral_code_id),
                 referral_code = CASE WHEN referral_code IS NULL OR referral_code = "" THEN :referral_code ELSE referral_code END
             WHERE id = :id'
        );
        $backfillStore->execute([
            ':referral_code_id' => $demoReferralId > 0 ? $demoReferralId : null,
            ':referral_code' => 'INTERNAL-DEMO',
            ':id' => $storeId,
        ]);
        ensure_referral_usage_count($pdo, $demoReferralId, 'INTERNAL-DEMO');
    }

    $hasStoreIdColumn = false;
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'users'
               AND column_name = 'store_id'"
        );
        $stmt->execute();
        $hasStoreIdColumn = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        $hasStoreIdColumn = false;
    }

    if (!$hasStoreIdColumn) {
        return;
    }

    $superadminIdStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'superadmin' LIMIT 1");
    $superadminIdStmt->execute();
    $superadminRoleId = (int) $superadminIdStmt->fetchColumn();

    $updateUsers = $pdo->prepare(
        'UPDATE users
         SET store_id = :store_id
         WHERE (store_id IS NULL OR store_id = 0)
           AND (:superadmin_role_id_check = 0 OR role_id <> :superadmin_role_id_value)'
    );
    $updateUsers->execute([
        ':store_id' => $storeId,
        ':superadmin_role_id_check' => $superadminRoleId,
        ':superadmin_role_id_value' => $superadminRoleId,
    ]);
}

function ensure_default_demo_referral(PDO $pdo): int
{
    $lookup = $pdo->prepare("SELECT id FROM store_referral_codes WHERE code = 'INTERNAL-DEMO' LIMIT 1");
    $lookup->execute();
    $existingId = (int) $lookup->fetchColumn();
    if ($existingId > 0) {
        return $existingId;
    }

    $insert = $pdo->prepare(
        "INSERT INTO store_referral_codes (
            code, label, note, max_uses, used_count, expires_at, is_active, generated_by
         ) VALUES (
            :code, :label, :note, :max_uses, :used_count, :expires_at, :is_active, :generated_by
         )"
    );
    $insert->execute([
        ':code' => 'INTERNAL-DEMO',
        ':label' => 'Internal Demo',
        ':note' => 'Kode referral internal bawaan untuk demo dan data awal sistem.',
        ':max_uses' => 999,
        ':used_count' => 0,
        ':expires_at' => null,
        ':is_active' => 1,
        ':generated_by' => null,
    ]);

    return (int) $pdo->lastInsertId();
}

function ensure_internal_demo_referral_consistency(PDO $pdo): void
{
    $lookup = $pdo->prepare("SELECT id FROM store_referral_codes WHERE code = 'INTERNAL-DEMO' LIMIT 1");
    $lookup->execute();
    $referralId = (int) $lookup->fetchColumn();
    if ($referralId <= 0) {
        return;
    }

    $linkedStores = ensure_referral_usage_count($pdo, $referralId, 'INTERNAL-DEMO');
    if ($linkedStores > 0) {
        return;
    }

    $delete = $pdo->prepare(
        "DELETE FROM store_referral_codes
         WHERE id = :id
           AND code = 'INTERNAL-DEMO'
           AND generated_by IS NULL"
    );
    $delete->execute([':id' => $referralId]);
}

function ensure_referral_usage_count(PDO $pdo, int $referralId, string $referralCode): int
{
    if ($referralId <= 0 || trim($referralCode) === '') {
        return 0;
    }

    $count = $pdo->prepare(
        'SELECT COUNT(DISTINCT id)
         FROM stores
         WHERE referral_code_id = :referral_id
            OR referral_code = :referral_code'
    );
    $count->execute([
        ':referral_id' => $referralId,
        ':referral_code' => $referralCode,
    ]);
    $linkedStores = (int) $count->fetchColumn();

    $update = $pdo->prepare(
        'UPDATE store_referral_codes
         SET used_count = :used_count,
             last_used_at = CASE WHEN :used_count_value > 0 THEN COALESCE(last_used_at, NOW()) ELSE NULL END
         WHERE id = :id'
    );
    $update->execute([
        ':used_count' => $linkedStores,
        ':used_count_value' => $linkedStores,
        ':id' => $referralId,
    ]);

    return $linkedStores;
}
