SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM refund_items;
DELETE FROM refunds;
DELETE FROM transaction_meta;
DELETE FROM shift_movements;
DELETE FROM shifts;
DELETE FROM inventory_logs;
DELETE FROM inventory_items;
DELETE FROM promo_vouchers;
DELETE FROM promo_settings;
DELETE FROM payments;
DELETE FROM transaction_items;
DELETE FROM transactions;
DELETE FROM products;
DELETE FROM categories;

ALTER TABLE refund_items AUTO_INCREMENT = 1;
ALTER TABLE refunds AUTO_INCREMENT = 1;
ALTER TABLE transaction_meta AUTO_INCREMENT = 1;
ALTER TABLE shift_movements AUTO_INCREMENT = 1;
ALTER TABLE shifts AUTO_INCREMENT = 1;
ALTER TABLE inventory_logs AUTO_INCREMENT = 1;
ALTER TABLE inventory_items AUTO_INCREMENT = 1;
ALTER TABLE promo_vouchers AUTO_INCREMENT = 1;
ALTER TABLE promo_settings AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE transaction_items AUTO_INCREMENT = 1;
ALTER TABLE transactions AUTO_INCREMENT = 1;
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;
