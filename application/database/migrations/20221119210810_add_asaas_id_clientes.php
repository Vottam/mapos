<?php

class Migration_add_asaas_id_clientes extends CI_Migration
{
    public function up()
    {
        if (! $this->db->field_exists('asaas_id', 'clientes')) {
            $this->db->query('ALTER TABLE `clientes` ADD `asaas_id` VARCHAR(255) NULL DEFAULT NULL');
        }

        if ($this->db->field_exists('asaas_id', 'usuarios')) {
            $comDados = $this->db->where('asaas_id IS NOT NULL', null, false)
                ->where('asaas_id <>', '')
                ->count_all_results('usuarios');

            if ($comDados > 0) {
                throw new RuntimeException('usuarios.asaas_id ainda contém dados; revisão de preservação obrigatória antes da remoção.');
            }

            $this->db->query('ALTER TABLE `usuarios` DROP `asaas_id`');
        }

        if (! $this->db->field_exists('asaas_id', 'clientes') || $this->db->field_exists('asaas_id', 'usuarios')) {
            throw new RuntimeException('Não foi possível reconciliar os campos asaas_id.');
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `clientes` DROP `asaas_id`');
        $this->db->query('ALTER TABLE `usuarios` DROP `asaas_id`');
    }
}
