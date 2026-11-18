<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Configuração de garantia comercial padrão por categoria e garantia
 * recebida do fornecedor por produto (tarefa MAPOS_531_CONFIGURABLE_WARRANTY).
 *
 * Regras:
 * - dados existentes preservados; nada é apagado;
 * - produtos/serviços com garantia_dias = 90 eram o padrão histórico
 *   hardcoded, então passam a HERDAR o padrão global (garantia_dias_custom = 0);
 *   prazos diferentes de 90 são tratados como personalizados (custom = 1);
 * - produtos sem informação de fornecedor continuam com os campos NULL
 *   (ausência de informação NÃO é prazo zero);
 * - idempotente: colunas existentes são ignoradas e as chaves de
 *   configuração só são criadas quando ausentes;
 * - a coluna configuracoes.config (VARCHAR(20) em bases legadas) é ampliada
 *   de forma não destrutiva para VARCHAR(64), para acomodar as chaves
 *   completas garantia_padrao_produto_dias / garantia_padrao_servico_dias.
 *
 * Os prazos globais ficam na tabela configuracoes
 * (garantia_padrao_produto_dias / garantia_padrao_servico_dias), com
 * fallback controlado para 90 quando ausentes ou inválidos.
 */
class Migration_add_supplier_warranty_and_defaults extends CI_Migration
{
    private function colunasProdutos(): array
    {
        return [
            'garantia_dias_custom' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 0],
            'supplier_warranty_days' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'supplier_warranty_start_date' => ['type' => 'DATE', 'null' => true],
            'supplier_warranty_pass_through' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 0],
        ];
    }

    public function up()
    {
        // 1. Coluna de chave de configuracoes ampliada (não destrutivo; só se curta).
        $info = $this->db->query(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS tamanho
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'configuracoes'
                AND COLUMN_NAME = 'config'"
        )->row();
        $tamanhoAtual = $info !== null ? (int) $info->tamanho : 0;
        if ($tamanhoAtual > 0 && $tamanhoAtual < 64) {
            // Preserva o tipo de texto aumentando só o tamanho (instantâneo no MySQL 8).
            $this->db->query('ALTER TABLE configuracoes MODIFY config VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
            $tamanhoVerificado = $this->db->query(
                "SELECT CHARACTER_MAXIMUM_LENGTH AS tamanho
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'configuracoes'
                    AND COLUMN_NAME = 'config'"
            )->row();
            if ($tamanhoVerificado === null || (int) $tamanhoVerificado->tamanho < 64) {
                throw new RuntimeException('A coluna configuracoes.config não pôde ser ampliada para 64.');
            }
        }

        foreach (['produtos' => $this->colunasProdutos(), 'servicos' => ['garantia_dias_custom' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 0]]] as $tabela => $colunas) {
            if (! $this->db->table_exists($tabela)) {
                throw new RuntimeException('Tabela esperada ausente na migration: ' . $tabela);
            }

            foreach ($colunas as $coluna => $definicao) {
                if ($this->db->field_exists($coluna, $tabela)) {
                    continue;
                }

                $this->dbforge->add_column($tabela, [$coluna => $definicao]);
                if (! $this->db->field_exists($coluna, $tabela)) {
                    throw new RuntimeException('A coluna não foi criada: ' . $tabela . '.' . $coluna);
                }
            }
        }

        // Distinção herança vs. personalização: 90 era o valor hardcoded.
        $this->db->query('UPDATE produtos SET garantia_dias_custom = 1 WHERE garantia_dias <> 90');
        $this->db->query('UPDATE servicos SET garantia_dias_custom = 1 WHERE garantia_dias <> 90');
        $this->db->query('UPDATE produtos SET garantia_dias_custom = 0 WHERE garantia_dias = 90');
        $this->db->query('UPDATE servicos SET garantia_dias_custom = 0 WHERE garantia_dias = 90');

        // Padrões globais de garantia comercial (configuracoes), só se ausentes.
        $this->garantirConfiguracao('garantia_padrao_produto_dias', '90');
        $this->garantirConfiguracao('garantia_padrao_servico_dias', '90');
    }

    private function garantirConfiguracao(string $chave, string $valor): void
    {
        $existente = $this->db->where('config', $chave)->limit(1)->get('configuracoes')->row();
        if ($existente !== null) {
            return;
        }

        $this->db->insert('configuracoes', ['config' => $chave, 'valor' => $valor]);
    }

    public function down()
    {
        foreach (array_reverse($this->colunasProdutos(), true) as $coluna => $definicao) {
            if ($this->db->table_exists('produtos') && $this->db->field_exists($coluna, 'produtos')) {
                $this->dbforge->drop_column('produtos', $coluna);
            }
        }

        if ($this->db->table_exists('servicos') && $this->db->field_exists('garantia_dias_custom', 'servicos')) {
            $this->dbforge->drop_column('servicos', 'garantia_dias_custom');
        }
        // As chaves de configuração são preservadas (valores do administrador).
    }
}
