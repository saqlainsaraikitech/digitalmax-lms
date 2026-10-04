-- DigitalMax LMS v28: payment_settings (dynamic payment methods)

CREATE TABLE IF NOT EXISTS payment_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  method_name VARCHAR(100) NOT NULL,
  account_title VARCHAR(255) NOT NULL DEFAULT '',
  account_number VARCHAR(255) NOT NULL DEFAULT '',
  instructions TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payment_settings (method_name, account_title, account_number, instructions, sort_order)
SELECT * FROM (
  SELECT 'EasyPaisa' AS m, '' AS t, '' AS n, 'EasyPaisa App ya qareebi retailer se send karein' AS ins, 1 AS s
  UNION ALL SELECT 'JazzCash', '', '', 'JazzCash App ya qareebi retailer se send karein', 2
  UNION ALL SELECT 'Bank Transfer / Raast', '', '', 'Neeche diye gaye IBAN par transfer karein', 3
) d WHERE NOT EXISTS (SELECT 1 FROM payment_settings);
