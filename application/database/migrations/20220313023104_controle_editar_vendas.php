<?php

class Migration_controle_editar_vendas extends CI_Migration
{
    public function up()
    {
        $linha = $this->db->where('idConfig', 13)->get('configuracoes')->row();

        if ($linha && $linha->config !== 'control_edit_vendas') {
            throw new RuntimeException('idConfig=13 já está ocupado por outra configuração.');
        }

        if (! $linha && $this->db->where('config', 'control_edit_vendas')->count_all_results('configuracoes') === 0) {
            $this->db->query("INSERT INTO `configuracoes` (`idConfig`, `config`, `valor`) VALUES (13, 'control_edit_vendas', 1);");
        }
    }

    public function down()
    {
        $this->db->query('DELETE FROM `configuracoes` WHERE `configuracoes`.`idConfig` = 13');
    }
}
