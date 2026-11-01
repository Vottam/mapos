<?php

class Migration_add_password_client extends CI_Migration
{
    public function up()
    {
        if (! $this->db->field_exists('senha', 'clientes')) {
            $this->db->query('ALTER TABLE `clientes` ADD `senha` VARCHAR(200) NOT NULL;');
        }

        if (! $this->db->field_exists('senha', 'clientes')) {
            throw new RuntimeException('Não foi possível reconciliar clientes.senha; veja o log da aplicação.');
        }

        if (! $this->db->table_exists('resets_de_senha')) {
            $this->db->query('CREATE TABLE `resets_de_senha` ( 
                `id` INT NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(200) NOT NULL , 
                `token` VARCHAR(255) NOT NULL , 
                `data_expiracao` DATETIME NOT NULL, 
                `token_utilizado` TINYINT NOT NULL,
                PRIMARY KEY (`id`))
              ENGINE = InnoDB
              DEFAULT CHARACTER SET = latin1;
        ');
        }

        foreach (['id', 'email', 'token', 'data_expiracao', 'token_utilizado'] as $campo) {
            if (! $this->db->field_exists($campo, 'resets_de_senha')) {
                throw new RuntimeException('A tabela resets_de_senha existe, mas falta a coluna ' . $campo . '.');
            }
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `clientes` DROP `senha`;');
        $this->db->query('DROP TABLE `resets_de_senha`;');
    }
}
