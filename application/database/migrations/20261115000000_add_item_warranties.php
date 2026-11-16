<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Garantia comercial individual por item, com compatibilidade explícita para
 * operações históricas: novos campos de vínculo e cabeçalho ficam NULL, sem
 * backfill; somente o catálogo recebe 90 dias como base inicial.
 *
 * A classificação durável/não durável foi removida da especificação. O
 * único dado de garantia no catálogo é o prazo numérico em dias.
 */
class Migration_add_item_warranties extends CI_Migration
{
    private const COLUNAS = [
        'produtos' => [
            'garantia_dias' => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 90],
        ],
        'servicos' => [
            'garantia_dias' => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 90],
        ],
        'itens_de_vendas' => [
            'garantia_dias_snapshot' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ],
        'produtos_os' => [
            'garantia_dias_snapshot' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ],
        'servicos_os' => [
            'garantia_dias_snapshot' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ],
        'vendas' => [
            'garantia_modelo' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
        ],
        'os' => [
            'garantia_modelo' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
        ],
    ];

    public function up()
    {
        foreach (self::COLUNAS as $tabela => $colunas) {
            if (! $this->db->table_exists($tabela)) {
                throw new RuntimeException('Tabela esperada ausente na migration de garantia: ' . $tabela);
            }

            foreach ($colunas as $coluna => $definicao) {
                if ($this->db->field_exists($coluna, $tabela)) {
                    continue;
                }

                // O DEFAULT explícito inicializa apenas o catálogo existente.
                // Snapshots de operações e o seletor de modo não têm DEFAULT.
                $this->dbforge->add_column($tabela, [$coluna => $definicao]);
                if ($this->db->field_exists($coluna, $tabela) === false) {
                    throw new RuntimeException('A coluna não foi criada: ' . $tabela . '.' . $coluna);
                }
            }
        }
    }

    public function down()
    {
        foreach (array_reverse(self::COLUNAS, true) as $tabela => $colunas) {
            foreach (array_reverse(array_keys($colunas)) as $coluna) {
                if ($this->db->table_exists($tabela) && $this->db->field_exists($coluna, $tabela)) {
                    $this->dbforge->drop_column($tabela, $coluna);
                }
            }
        }
    }
}
