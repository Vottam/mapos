<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_add_garantia_status_to_vendas_table extends CI_Migration
{

    public function up()
    {
        $garantia = $this->db->query("SHOW COLUMNS FROM `vendas` LIKE 'garantia'")->row();

        if (! $garantia) {
            $this->dbforge->add_column('vendas', [
                'garantia' => [
                    'type' => 'VARCHAR',
                    'constraint' => '45',
                    'null' => true,
                ],
            ]);
        } elseif (strtolower((string) $garantia->Type) !== 'varchar(45)') {
            // O staging legado possui INT com valores 0/30; a conversão para
            // VARCHAR(45) preserva esses valores e torna o schema compatível.
            $this->db->query('ALTER TABLE `vendas` MODIFY `garantia` VARCHAR(45) NULL');
        }

        $status = $this->db->query("SHOW COLUMNS FROM `vendas` LIKE 'status'")->row();

        if (! $status) {
            $this->dbforge->add_column('vendas', [
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => '45',
                    'null' => true,
                ],
            ]);
        }

        if (! $this->db->field_exists('garantia', 'vendas') || ! $this->db->field_exists('status', 'vendas')) {
            throw new RuntimeException('Não foi possível reconciliar vendas.garantia/status; veja o log da aplicação.');
        }
    }

    public function down()
    {
        // Remove o campo garantia
        $this->dbforge->drop_column('vendas', 'garantia');

        // Remove o campo status
        $this->dbforge->drop_column('vendas', 'status');
    }
}
