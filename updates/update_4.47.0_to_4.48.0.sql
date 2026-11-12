-- Tabela de competências mensais de custos fixos
-- Preserva histórico de valores por mês, independente de edição no cadastro principal

CREATE TABLE IF NOT EXISTS `custos_fixos_competencias` (
  `idCompetencia` int NOT NULL AUTO_INCREMENT,
  `custo_fixo_id` int NOT NULL,
  `competencia` date NOT NULL COMMENT 'Primeiro dia do mês de competência (YYYY-MM-01)',
  `valor` decimal(10,2) NOT NULL DEFAULT '0.00',
  `data_vencimento` date DEFAULT NULL,
  `observacoes` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`idCompetencia`),
  UNIQUE KEY `uq_custo_competencia` (`custo_fixo_id`, `competencia`),
  KEY `idx_custo_fixo_id` (`custo_fixo_id`),
  KEY `idx_competencia` (`competencia`),
  CONSTRAINT `fk_custo_fixo_competencia` FOREIGN KEY (`custo_fixo_id`) REFERENCES `custos_fixos` (`idCustoFixo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Adiciona referência à competência na tabela principal (para saber se já foi gerada)
ALTER TABLE `custos_fixos` ADD COLUMN `competencias_geradas` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 se competências mensais já foram geradas';
