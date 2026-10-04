-- DigitalMax LMS v21 upgrade: Batches, lecture batch-access, vault
-- Purani install par phpMyAdmin me chalana (ya system khud bana lega - self-healing)

CREATE TABLE IF NOT EXISTS batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  batch_name VARCHAR(120) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  duration_months INT NOT NULL DEFAULT 3,
  status ENUM('upcoming','active','completed') NOT NULL DEFAULT 'upcoming',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lecture_batches (
  lecture_id INT NOT NULL,
  batch_id INT NOT NULL,
  PRIMARY KEY (lecture_id, batch_id),
  FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vault_resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  category ENUM('niche','master_prompt','ad_copy','supplier_list') NOT NULL DEFAULT 'master_prompt',
  content LONGTEXT NOT NULL,
  course_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- enrollments: batch link (purani rows me NULL = sab lectures visible)
SET @has_batch := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enrollments' AND COLUMN_NAME = 'batch_id');
SET @sql := IF(@has_batch = 0,
  'ALTER TABLE enrollments ADD COLUMN batch_id INT NULL DEFAULT NULL, ADD CONSTRAINT fk_enr_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- lectures: share-all flag (default 1 = sab batches ko visible, backward compatible)
SET @has_share := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lectures' AND COLUMN_NAME = 'share_all_batches');
SET @sql2 := IF(@has_share = 0,
  'ALTER TABLE lectures ADD COLUMN share_all_batches TINYINT(1) NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
