<?php

class Migration_add_config_control_print_2ways_os extends CI_Migration
{
    public function up()
    {
        $linha = $this->db->where('idConfig', 15)->get('configuracoes')->row();

        if ($linha && $linha->config !== 'control_2vias') {
            throw new RuntimeException('idConfig=15 já está ocupado por outra configuração.');
        }

        if (! $linha && $this->db->where('config', 'control_2vias')->count_all_results('configuracoes') === 0) {
            $this->db->query("INSERT INTO `configuracoes` (`idConfig`, `config`, `valor`) VALUES (15, 'control_2vias', 0);");
        }
    }

    public function down()
    {
        $this->db->query('DELETE FROM `configuracoes` WHERE `configuracoes`.`idConfig` = 15');
    }
}
