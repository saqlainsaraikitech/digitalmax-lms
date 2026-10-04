-- DigitalMax LMS v7 upgrade — duplicate Transaction ID rokna
-- (Sirf tab jab v6 ya purana version pehle se installed ho.)

-- Pehle check kar lein ke koi duplicate TID to nahi (agar hon to ye fail hoga):
-- SELECT trx_id, COUNT(*) c FROM payments WHERE trx_id IS NOT NULL AND trx_id != '' GROUP BY trx_id HAVING c > 1;

ALTER TABLE payments ADD UNIQUE KEY uniq_trx (trx_id);
