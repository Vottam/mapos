-- Adiciona controle de pagamento por competência mensal de custos fixos
-- Cada competência pode ter status Pago/Pendente independente

ALTER TABLE `custos_fixos_competencias`
  ADD COLUMN `pago` TINYINT(1) NOT NULL DEFAULT 0 AFTER `valor`,
  ADD COLUMN `data_pagamento` DATE NULL AFTER `pago`,
  ADD COLUMN `observacoes_pagamento` TEXT NULL AFTER `data_pagamento`;
