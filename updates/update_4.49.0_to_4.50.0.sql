-- Adiciona campo de regra de vencimento na tabela custos_fixos
-- Mês vencido (padrão): competência 06/2026 vence em 07/2026
-- Pagamento antecipado / mês corrente: competência 06/2026 vence em 06/2026

ALTER TABLE `custos_fixos`
  ADD COLUMN `regra_vencimento` VARCHAR(20) NOT NULL DEFAULT 'mes_vencido' COMMENT 'mes_vencido ou mes_corrente' AFTER `dia_vencimento`;
