<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_add_data_entrega_to_os extends CI_Migration
{
    public function up()
    {
        if (! $this->db->field_exists('dataEntrega', 'os')) {
            $this->dbforge->add_column('os', [
                'dataEntrega' => [
                    'type' => 'DATE',
                    'null' => true,
                    'default' => null,
                    'after' => 'dataFinal',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->field_exists('dataEntrega', 'os')) {
            $this->dbforge->drop_column('os', 'dataEntrega');
        }
    }
}
