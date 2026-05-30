-- Tenant isolation baseline for existing KASPINDO installs.
-- Runtime migration code in app/helpers/db_migration_helper.php keeps this idempotent.

ALTER TABLE users ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions_json LONGTEXT DEFAULT NULL;

ALTER TABLE categories ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE transaction_items ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE transaction_meta ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE shifts ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE shift_movements ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE inventory_items ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE inventory_logs ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE promo_settings ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE promo_vouchers ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE customer_point_logs ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE refunds ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE refund_items ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;
ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL;

UPDATE categories SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE products SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE transactions SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE transaction_items SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE transaction_meta SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE payments SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE shifts SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE shift_movements SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE inventory_items SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE inventory_logs SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE promo_settings SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE promo_vouchers SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE customers SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE customer_point_logs SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE refunds SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE refund_items SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
UPDATE notification_settings SET store_id = 1 WHERE store_id IS NULL OR store_id = 0;
