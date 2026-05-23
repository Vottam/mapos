-- Missão 016C.3 -- Campos de retorno em garantia na OS
-- Compatível com MySQL 5.7 (sem IF NOT EXISTS em ADD COLUMN)
-- Verificar manualmente antes de:
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='os' AND COLUMN_NAME='garantia_retorno';
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='os' AND COLUMN_NAME='garantia_origem_os_id';

ALTER TABLE os
  ADD COLUMN garantia_retorno TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN garantia_origem_os_id INT NULL;
