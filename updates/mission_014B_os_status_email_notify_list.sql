ALTER TABLE configuracoes
  MODIFY COLUMN config VARCHAR(64) NOT NULL;

INSERT INTO configuracoes (config, valor)
SELECT 'os_status_email_notify_list', '["Orçamento","Finalizado","Faturado"]'
WHERE NOT EXISTS (
  SELECT 1
  FROM configuracoes
  WHERE config = 'os_status_email_notify_list'
);
