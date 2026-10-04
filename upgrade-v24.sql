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
