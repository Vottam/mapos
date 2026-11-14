<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/application/views/os/formulario.php');
if (!str_contains($view, 'name="os_create_token"')) {
    fwrite(STDERR, "FAIL modern OS form does not render os_create_token\n");
    exit(1);
}
if (!str_contains($view, '$os_create_token ??')) {
    fwrite(STDERR, "FAIL modern OS form token is not sourced from controller data\n");
    exit(1);
}
echo "PASS modern OS create-token view regression\n";
