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
