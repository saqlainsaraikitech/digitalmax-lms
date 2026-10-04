-- DigitalMax LMS v11 upgrade — Referral / Affiliate system
-- (Sirf tab jab v10 ya purana version pehle se installed ho.)

ALTER TABLE users ADD COLUMN referral_code VARCHAR(16) NULL DEFAULT NULL, ADD UNIQUE KEY uniq_refcode (referral_code);

ALTER TABLE payments
  ADD COLUMN referred_by INT NULL DEFAULT NULL,
  ADD COLUMN commission_amount DECIMAL(10,2) NULL DEFAULT NULL,
  ADD COLUMN commission_paid TINYINT(1) NOT NULL DEFAULT 0,
  ADD FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS referral_clicks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referrer_id INT NOT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (k, v) VALUES ('affiliate_commission_percent', '10');
