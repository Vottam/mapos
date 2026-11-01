<?php

class Migration_alter_charset_configuracoes extends CI_Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE `configuracoes` CHANGE `config` `config` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;');
        // A mensagem de idConfig=7 é uma personalização operacional do staging.
        // A reconciliação preserva o conteúdo existente e não aplica o texto padrão
        // da migration oficial sobre uma configuração presente.
    }

    public function down()
    {
        $this->db->query('DELETE FROM `configuracoes` WHERE `configuracoes`.`idConfig` = 7');
    }
}
