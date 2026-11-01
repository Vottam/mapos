<?php

class Migration_add_tipo_desconto_os_vendas extends CI_Migration
{
    public function up()
    {
        foreach (['os', 'vendas', 'lancamentos'] as $tabela) {
            if (! $this->db->field_exists('tipo_desconto', $tabela)) {
                $this->db->query('ALTER TABLE `' . $tabela . '` ADD `tipo_desconto` VARCHAR(8) NULL DEFAULT NULL');
            }

            if (! $this->db->field_exists('tipo_desconto', $tabela)) {
                throw new RuntimeException('Não foi possível reconciliar ' . $tabela . '.tipo_desconto.');
            }
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `os` DROP `tipo_desconto`');
        $this->db->query('ALTER TABLE `vendas` DROP `tipo_desconto`');
        $this->db->query('ALTER TABLE `lancamentos` DROP `tipo_desconto`');
    }
}
