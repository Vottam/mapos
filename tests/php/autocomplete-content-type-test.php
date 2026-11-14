<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Os.php');
foreach (['autoCompleteCliente', 'autoCompleteUsuario', 'autoCompleteTermoGarantia'] as $method) {
    $start = strpos((string) $controller, "public function {$method}()");
    if ($start === false) {
        fwrite(STDERR, "FAIL missing {$method}\n");
        exit(1);
    }
    $body = substr((string) $controller, $start, 400);
    if (!str_contains($body, "set_content_type('application/json')")) {
        fwrite(STDERR, "FAIL missing JSON content type for {$method}\n");
        exit(1);
    }
}
echo "PASS autocomplete JSON content-type regression\n";
