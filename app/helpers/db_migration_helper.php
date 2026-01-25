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
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'promo_settings'
             LIMIT 1"
        );
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();
        if ($exists) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }

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
SQL;

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}
