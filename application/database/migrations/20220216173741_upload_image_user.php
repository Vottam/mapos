<?php

class Migration_upload_image_user extends CI_Migration
{
    public function up()
    {
        if (! $this->db->field_exists('url_image_user', 'usuarios')) {
            $this->db->query('ALTER TABLE `usuarios` ADD `url_image_user` varchar(255) NULL');
        }

        if (! $this->db->field_exists('url_image_user', 'usuarios')) {
            throw new RuntimeException('Não foi possível reconciliar usuarios.url_image_user; veja o log da aplicação.');
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `usuarios` DROP `url_image_user`;');
    }
}
