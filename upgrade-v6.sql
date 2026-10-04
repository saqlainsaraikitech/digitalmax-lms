-- DigitalMax LMS v6 upgrade — single-device login
-- (Sirf tab chalana hai jab v5 ya us se purana version pehle se installed ho.
--  Nayi install par zaroorat nahi — install.php khud handle kar lega.)

ALTER TABLE users ADD COLUMN current_session_id VARCHAR(255) NULL DEFAULT NULL;
