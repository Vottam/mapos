<?php

/**
 * Schema mínimo do runtime 5.2.2 consolidado.
 *
 * Alguns bancos legados já receberam estas estruturas por scripts de atualização
 * anteriores. Cada alteração é protegida por table_exists/field_exists para que
 * a migration seja segura tanto no banco base quanto no LIVE já atualizado.
 */
class Migration_add_rc_runtime_schema extends CI_Migration
{
    public function up()
    {
        $this->addColumnIfMissing('produtos_os', 'custo_unitario', [
            'type' => 'DECIMAL',
            'constraint' => '10,2',
            'null' => false,
            'default' => 0,
        ]);
        $this->addColumnIfMissing('produtos_os', 'custo_total', [
            'type' => 'DECIMAL',
            'constraint' => '10,2',
            'null' => false,
            'default' => 0,
        ]);
        $this->addColumnIfMissing('itens_de_vendas', 'custo_unitario', [
            'type' => 'DECIMAL',
            'constraint' => '10,2',
            'null' => false,
            'default' => 0,
        ]);
        $this->addColumnIfMissing('itens_de_vendas', 'custo_total', [
            'type' => 'DECIMAL',
            'constraint' => '10,2',
            'null' => false,
            'default' => 0,
        ]);
        $this->addColumnIfMissing('os', 'garantia_retorno', [
            'type' => 'TINYINT',
            'constraint' => 1,
            'null' => false,
            'default' => 0,
        ]);
        $this->addColumnIfMissing('os', 'garantia_origem_os_id', [
            'type' => 'INT',
            'constraint' => 11,
            'null' => true,
        ]);

        if (! $this->db->table_exists('custos_fixos')) {
            $this->db->query(
                'CREATE TABLE `custos_fixos` ('
                . '`idCustoFixo` INT NOT NULL AUTO_INCREMENT,'
                . '`titulo` VARCHAR(150) NOT NULL,'
                . '`categoria` VARCHAR(100) NOT NULL,'
                . '`valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,'
                . '`periodicidade` VARCHAR(20) NOT NULL DEFAULT \'mensal\','
                . '`dia_vencimento` TINYINT UNSIGNED NOT NULL DEFAULT 1,'
                . '`regra_vencimento` VARCHAR(20) NOT NULL DEFAULT \'mes_vencido\','
                . '`forma_pagamento` VARCHAR(80) NOT NULL,'
                . '`ativo` TINYINT(1) NOT NULL DEFAULT 1,'
                . '`data_inicio` DATE NULL,'
                . '`data_fim` DATE NULL,'
                . '`observacoes` TEXT NULL,'
                . '`usuarios_id` INT NULL,'
                . '`created_at` DATETIME NOT NULL,'
                . '`updated_at` DATETIME NOT NULL,'
                . '`competencias_geradas` TINYINT(1) NOT NULL DEFAULT 0,'
                . 'PRIMARY KEY (`idCustoFixo`),'
                . 'KEY `idx_custos_fixos_categoria` (`categoria`),'
                . 'KEY `idx_custos_fixos_ativo` (`ativo`),'
                . 'KEY `idx_custos_fixos_usuarios` (`usuarios_id`),'
                . 'CONSTRAINT `fk_custos_fixos_usuarios` FOREIGN KEY (`usuarios_id`) REFERENCES `usuarios` (`idUsuarios`) ON DELETE SET NULL ON UPDATE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } else {
            $this->addColumnIfMissing('custos_fixos', 'competencias_geradas', [
                'type' => 'TINYINT',
                'constraint' => 1,
                'null' => false,
                'default' => 0,
            ]);
        }

        if (! $this->db->table_exists('custos_fixos_competencias')) {
            $this->db->query(
                'CREATE TABLE `custos_fixos_competencias` ('
                . '`idCompetencia` INT NOT NULL AUTO_INCREMENT,'
                . '`custo_fixo_id` INT NOT NULL,'
                . '`competencia` DATE NOT NULL,'
                . '`valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,'
                . '`pago` TINYINT(1) NOT NULL DEFAULT 0,'
                . '`data_pagamento` DATE NULL,'
                . '`observacoes_pagamento` TEXT NULL,'
                . '`data_vencimento` DATE NULL,'
                . '`observacoes` TEXT NULL,'
                . '`created_at` DATETIME NOT NULL,'
                . '`updated_at` DATETIME NOT NULL,'
                . 'PRIMARY KEY (`idCompetencia`),'
                . 'UNIQUE KEY `uq_custo_competencia` (`custo_fixo_id`,`competencia`),'
                . 'KEY `idx_custo_fixo_id` (`custo_fixo_id`),'
                . 'KEY `idx_competencia` (`competencia`),'
                . 'CONSTRAINT `fk_custo_fixo_competencia` FOREIGN KEY (`custo_fixo_id`) REFERENCES `custos_fixos` (`idCustoFixo`) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
            );
        }
    }

    public function down()
    {
    }

    private function addColumnIfMissing(string $table, string $column, array $definition): void
    {
        if ($this->db->table_exists($table) && ! $this->db->field_exists($column, $table)) {
            $this->dbforge->add_column($table, [$column => $definition]);
        }
    }
}
