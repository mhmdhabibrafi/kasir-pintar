CREATE TABLE IF NOT EXISTS store_domains (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT NOT NULL,
  domain VARCHAR(190) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  verified_at DATETIME DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_store_domains_domain (domain),
  INDEX idx_store_domains_store_id (store_id),
  INDEX idx_store_domains_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
