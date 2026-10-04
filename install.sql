-- =====================================================
-- DigitalMax LMS - Database schema
-- (install.php ye file auto-run karta hai; ya phpMyAdmin me import kar lein)
-- =====================================================

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  role ENUM('admin','team','student') NOT NULL DEFAULT 'student',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  current_session_id VARCHAR(255) DEFAULT NULL,
  referral_code VARCHAR(16) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_refcode (referral_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS team_permissions (
  user_id INT PRIMARY KEY,
  p_courses TINYINT(1) NOT NULL DEFAULT 0,
  p_lectures TINYINT(1) NOT NULL DEFAULT 0,
  p_assignments TINYINT(1) NOT NULL DEFAULT 0,
  p_students TINYINT(1) NOT NULL DEFAULT 0,
  p_payments TINYINT(1) NOT NULL DEFAULT 0,
  p_team TINYINT(1) NOT NULL DEFAULT 0,
  p_tasks TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  thumbnail VARCHAR(255) DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lectures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  section_id INT DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  youtube_url VARCHAR(255) DEFAULT NULL,
  duration VARCHAR(20) DEFAULT NULL,
  notes TEXT,
  resource_file VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_preview TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  due_date DATE DEFAULT NULL,
  max_marks INT NOT NULL DEFAULT 100,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  student_id INT NOT NULL,
  file_path VARCHAR(255) DEFAULT NULL,
  answer_text TEXT,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  marks INT DEFAULT NULL,
  feedback TEXT,
  status ENUM('submitted','graded') NOT NULL DEFAULT 'submitted',
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fee_status ENUM('paid','pending') NOT NULL DEFAULT 'pending',
  fee_amount DECIMAL(10,2) NULL DEFAULT NULL,
  UNIQUE KEY uniq_enroll (student_id, course_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lecture_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  lecture_id INT NOT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_prog (student_id, lecture_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('easypaisa','jazzcash','bank','other') NOT NULL DEFAULT 'easypaisa',
  sender_number VARCHAR(30) DEFAULT NULL,
  trx_id VARCHAR(100) DEFAULT NULL,
  screenshot VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  verified_by INT DEFAULT NULL,
  referred_by INT DEFAULT NULL,
  commission_amount DECIMAL(10,2) DEFAULT NULL,
  commission_paid TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_trx (trx_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
  FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  assigned_to INT DEFAULT NULL,
  assigned_by INT DEFAULT NULL,
  status ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
  due_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  cert_code VARCHAR(32) NOT NULL UNIQUE,
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cert (student_id, course_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referral_clicks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referrer_id INT NOT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS video_progress (
  student_id INT NOT NULL,
  lecture_id INT NOT NULL,
  seconds INT NOT NULL DEFAULT 0,
  watched_sec INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id, lecture_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(100) PRIMARY KEY,
  v TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (k, v) VALUES
  ('site_name', 'DigitalMax LMS'),
  ('currency', 'Rs'),
  ('whatsapp_number', ''),
  ('affiliate_commission_percent', '10'),
  ('backup_secret_key', ''),
  ('batch_deadline', ''),
  ('broadcast_template', ''),
  ('seats_remaining', '7'),
  ('seats_total', '30'),
  ('installed', '1');
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
-- DigitalMax LMS v22 upgrade: Announcements & Notice Board
-- Purani install par phpMyAdmin me chalana (ya system khud bana lega - self-healing)

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  target_type ENUM('all_students','specific_course','specific_batch') NOT NULL DEFAULT 'all_students',
  course_id INT NULL,
  batch_id INT NULL,
  priority ENUM('normal','urgent','pinned') NOT NULL DEFAULT 'normal',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcement_reads (
  announcement_id INT NOT NULL,
  student_id INT NOT NULL,
  read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id, student_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- DigitalMax LMS v23 upgrade: VIP WhatsApp Community links
-- Purani install par phpMyAdmin me chalana (ya system khud bana lega - self-healing)

SET @has1 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'batches' AND COLUMN_NAME = 'whatsapp_group_link');
SET @s1 := IF(@has1 = 0,
  'ALTER TABLE batches ADD COLUMN whatsapp_group_link VARCHAR(255) NULL DEFAULT NULL',
  'SELECT 1');
PREPARE st1 FROM @s1; EXECUTE st1; DEALLOCATE PREPARE st1;

SET @has2 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'whatsapp_group_link');
SET @s2 := IF(@has2 = 0,
  'ALTER TABLE courses ADD COLUMN whatsapp_group_link VARCHAR(255) NULL DEFAULT NULL',
  'SELECT 1');
PREPARE st2 FROM @s2; EXECUTE st2; DEALLOCATE PREPARE st2;
-- DigitalMax LMS v24 upgrade: Dynamic WhatsApp Community Rules + Dual links
-- Purani install par phpMyAdmin me chalana (ya system khud bana lega - self-healing)

CREATE TABLE IF NOT EXISTS community_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rule_en TEXT NOT NULL,
  rule_ur TEXT NOT NULL,
  rule_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- default 5 rules (sirf tab jab table khali ho) — EXACT approved text
INSERT INTO community_rules (rule_en, rule_ur, rule_order)
SELECT * FROM (
  SELECT 'No Personal Messages (DMs): Strictly do not message any student or team member directly without permission.' AS en,
         'پرسنل میسج (ان باکس) کرنا سخت منع ہے: کسی بھی طالب علم یا ٹیم ممبر کو بغیر اجازت پرسنل میسج کرنا سخت منع ہے۔' AS ur, 1 AS ord
  UNION ALL SELECT 'No Buying, Selling, or Self-Promotion: Promoting your own services, accounts, stores, or third-party links is 100% prohibited.',
         'خرید و فروخت یا پروموشن کی قطعی ممانعت: گروپ میں اپنی سروسز، ٹک ٹاک اکاؤنٹس، شاپائف اسٹورز یا کوئی بھی پروموشنل لنک شیئر کرنا 100 فیصد منع ہے۔', 2
  UNION ALL SELECT 'Course-Related Discussions Only: All discussions must strictly pertain to TikTok Monetization, Shopify, and the course curriculum.',
         'صرف کورس سے متعلق گفتگو: گروپ میں صرف اور صرف ٹک ٹاک مونیٹائزیشن، شاپائف اور لیکچرز سے متعلق سوال و جواب کی اجازت ہوگی۔', 3
  UNION ALL SELECT 'Mutual Respect & Privacy: Respect everyone''s privacy. Harassment, abusive language, or spamming will result in immediate action.',
         'باہمی احترام اور پرائیویسی: تمام ممبران کے احترام اور پرائیویسی کا خیال رکھیں۔ بدتمیزی، بیہودہ گفتگو یا اسپامنگ پر فوری ایکشن لیا جائے گا۔', 4
  UNION ALL SELECT 'ZERO TOLERANCE POLICY: Violating any rule will lead to an PERMANENT BAN from both the WhatsApp group and the LMS platform, with NO REFUND under any circumstances.',
         'زیرو ٹالرنس پالیسی (Zero Tolerance Policy): کسی بھی اصول کی خلاف ورزی کی صورت میں بغیر کسی وارننگ کے واٹس ایپ گروپ اور LMS اکاؤنٹ دونوں سے مستقل طور پر بلاک (Ban) کر دیا جائے گا، اور کسی بھی صورت میں فیس واپس (Refund) نہیں کی جائے گی۔', 5
) d WHERE NOT EXISTS (SELECT 1 FROM community_rules);

-- Dual WhatsApp links: official community + discussion group
SET @has1 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'batches' AND COLUMN_NAME = 'whatsapp_community_link');
SET @s1 := IF(@has1 = 0,
  'ALTER TABLE batches ADD COLUMN whatsapp_community_link VARCHAR(255) NULL DEFAULT NULL',
  'SELECT 1');
PREPARE st1 FROM @s1; EXECUTE st1; DEALLOCATE PREPARE st1;

SET @has2 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'whatsapp_community_link');
SET @s2 := IF(@has2 = 0,
  'ALTER TABLE courses ADD COLUMN whatsapp_community_link VARCHAR(255) NULL DEFAULT NULL',
  'SELECT 1');
PREPARE st2 FROM @s2; EXECUTE st2; DEALLOCATE PREPARE st2;

-- Ban / Block system (users table)
-- status me 'banned' add (inactive rakha gaya hai - backward compatible)
SET @has_status := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status');
SET @s3 := IF(@has_status = 1,
  'ALTER TABLE users MODIFY COLUMN status ENUM(''active'',''inactive'',''pending'',''banned'') NOT NULL DEFAULT ''active''',
  'SELECT 1');
PREPARE st3 FROM @s3; EXECUTE st3; DEALLOCATE PREPARE st3;

SET @has_br := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'ban_reason');
SET @s4 := IF(@has_br = 0,
  'ALTER TABLE users ADD COLUMN ban_reason TEXT NULL DEFAULT NULL, ADD COLUMN banned_at DATETIME NULL DEFAULT NULL, ADD COLUMN banned_by INT NULL DEFAULT NULL',
  'SELECT 1');
PREPARE st4 FROM @s4; EXECUTE st4; DEALLOCATE PREPARE st4;
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
-- DigitalMax LMS v29: remember_tokens (Remember Me 30-day login)

CREATE TABLE IF NOT EXISTS remember_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hash (token_hash),
  KEY idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
