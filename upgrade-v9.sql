-- DigitalMax LMS v9 upgrade — lecture resources (multiple files/links per lecture)
-- (Sirf tab jab v8 ya purana version pehle se installed ho.)

CREATE TABLE IF NOT EXISTS lecture_resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lecture_id INT NOT NULL,
  label VARCHAR(150) NOT NULL DEFAULT '',
  file_path VARCHAR(255) DEFAULT NULL,
  url VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
