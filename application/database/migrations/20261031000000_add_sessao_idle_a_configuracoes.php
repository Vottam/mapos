<?php

/**
 * Configuração de expiração por inatividade da sessão (MapOS 5.2.2).
 *
 * Adiciona as preferências de sessão à tabela configuracoes, mantendo o
 * comportamento anterior (expiração em 7200s) como padrão para instalações
 * que ainda não escolheram. A escolha do administrador fica em
 * configuracoes e é espelhada em application/cache/sessao-idle-policy.json
   para ser lida pelo config.php antes da Session e da Security iniciarem.
 */
class Migration_add_sessao_idle_a_configuracoes extends CI_Migration
{
    private $table = 'configuracoes';

    public function up()
    {
        $padroes = [
            'sessao_idle_mode' => 'timed',
            'sessao_idle_tempo' => '7200',
        ];

        foreach ($padroes as $config => $valor) {
            $existe = $this->db->where('config', $config)->count_all_results($this->table) > 0;

            if (! $existe) {
                $this->db->insert($this->table, ['config' => $config, 'valor' => $valor]);
            }
        }
    }

    public function down()
    {
        $this->db->where_in('config', ['sessao_idle_mode', 'sessao_idle_tempo'])->delete($this->table);
    }
}
